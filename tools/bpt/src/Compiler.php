<?php
/**
 * Сборка .bpt из спецификации процесса.
 *
 * Шаг спецификации — объект с одним ключом: алиас или тип действия. Служебные ключи
 * (id, title, off, comment, name, raw, ветки) отделяются от свойств Bitrix; недостающие
 * свойства берутся из каталога. Формат спецификации — в tools/bpt/SPEC.md.
 */

declare(strict_types=1);

final class Compiler
{
    /** Служебные ключи шага — всё остальное считается свойствами действия. */
    private const META_KEYS = ['id', 'title', 'off', 'comment', 'name', 'raw',
        'on_yes', 'on_no', 'steps', 'branches', 'when', 'else'];

    /** Заголовок последовательности по умолчанию — так пишет дизайнер (экспорты 2026-09). */
    public const SEQUENCE_TITLE = 'Последовательность действий';

    private array $errors = [];
    private array $warnings = [];
    private array $usedNames = [];
    /** Логический id шага → имя действия, его тип и свойства (для проверки ссылок). */
    private array $steps = [];
    private array $nodeLabels = [];

    public function __construct(
        private readonly Catalog $catalog,
        private readonly ?Snapshot $snapshot = null,
        private readonly bool $strict = false,
    ) {
    }

    /** @return array{bpt: array, errors: string[], warnings: string[]} */
    public function compile(array $spec): array
    {
        $this->errors = [];
        $this->warnings = [];
        $this->usedNames = [];
        $this->steps = [];
        $this->nodeLabels = [];

        if (isset($spec['kind'])) {
            $this->warning('kind', 'ключ kind больше не используется: по .bpt шаблон роботов не отличить'
                . ' от шаблона дизайнера — удалите его');
        }
        $name = trim((string) ($spec['name'] ?? ''));
        if ($name === '') {
            $this->error('name', 'у процесса должно быть имя');
        }
        if (!isset($spec['bizproc'])) {
            $this->warning('bizproc', 'не указана версия формата спецификации (bizproc: 1)');
        }

        $this->checkSpecPortalIds($spec);
        $children = $this->buildSteps($spec['steps'] ?? [], 'steps');
        $root = [
            'Type'       => 'SequentialWorkflowActivity',
            'Name'       => 'Template',
            'Activated'  => 'Y',
            'Node'       => null,
            'Properties' => ['Title' => (string) ($spec['root_title'] ?? Analyzer::ROOT_TITLE)]
                + $this->catalog->defaults('SequentialWorkflowActivity'),
            'Children'   => $children,
        ];

        if (($this->snapshot?->documentFields() ?? []) === []) {
            $this->warning('DOCUMENT_FIELDS', 'собрано без DOCUMENT_FIELDS: перед загрузкой проверьте'
                . ' поведение на тестовом портале');
        }

        $bpt = [
            'VERSION'         => 2,
            'TEMPLATE'        => [$this->resolveReferences($root)],
            'PARAMETERS'      => $this->definitions($spec['parameters'] ?? [], 'parameters'),
            'VARIABLES'       => $this->definitions($spec['variables'] ?? [], 'variables'),
            'CONSTANTS'       => $this->definitions($spec['constants'] ?? [], 'constants'),
            'DOCUMENT_FIELDS' => $this->snapshot?->documentFields() ?? [],
        ];
        $this->runAnalyzer($bpt);

        return ['bpt' => $bpt, 'errors' => $this->errors, 'warnings' => $this->warnings];
    }

    /** Ссылки {=@id:Результат} превращаются в {=ИмяДействия:Результат}. */
    private function resolveReferences(array $node): array
    {
        $label = $this->nodeLabels[$node['Name']] ?? $node['Name'];
        $node['Properties'] = $this->replaceRefs($node['Properties'], $label);
        foreach ($node['Children'] as $i => $child) {
            $node['Children'][$i] = $this->resolveReferences($child);
        }
        return $node;
    }

    private function replaceRefs(mixed $value, string $label): mixed
    {
        if (is_string($value)) {
            return preg_replace_callback('/\{=@([^\s:}]+):([^\s>}]+)/u',
                fn (array $m) => $this->resolveRef($m[0], $m[1], $m[2], $label), $value);
        }
        if (!is_array($value)) {
            return $value;
        }
        $out = [];
        foreach ($value as $key => $item) {
            $newKey = is_string($key) ? $this->replaceRefs($key, $label) : $key;
            $out[$newKey] = $this->replaceRefs($item, $label);
        }
        return $out;
    }

    private function resolveRef(string $original, string $id, string $result, string $label): string
    {
        if (!isset($this->steps[$id])) {
            $this->error($label, "ссылка на несуществующий шаг «{$id}»"
                . ($this->steps ? '; известные id: ' . implode(', ', array_keys($this->steps)) : ''));
            return $original;
        }
        $step = $this->steps[$id];
        $returns = $this->catalog->returns($step['type'], $step['props']);
        if ($returns && !in_array($result, $returns, true)) {
            $this->warning($label, "действие {$step['type']} (шаг {$id}) обычно не возвращает «{$result}»"
                . '; известные результаты: ' . implode(', ', $returns));
        }
        return "{={$step['name']}:{$result}";
    }

    /** Проверки анализатора по собранному дереву: висячие ссылки, необъявленные переменные и прочее. */
    private function runAnalyzer(array $bpt): void
    {
        $report = (new Analyzer($this->catalog))->analyze('собранный шаблон', [
            'data' => $bpt, 'serialized' => null, 'compressed' => null,
        ]);
        foreach ($report['errors'] as $error) {
            $this->error('проверка', $error);
        }
        foreach ($report['warnings'] as $warning) {
            $this->warning('проверка', $warning);
        }
    }

    /**
     * «Сырые» идентификаторы портала ищем в самой спецификации: в собранном дереве они есть
     * всегда, в том числе подставленные из снимка.
     */
    private function checkSpecPortalIds(array $spec): void
    {
        $json = BptFile::flatJson(['steps' => $spec['steps'] ?? [], 'variables' => $spec['variables'] ?? [],
            'constants' => $spec['constants'] ?? [], 'parameters' => $spec['parameters'] ?? []]);
        foreach (Analyzer::PORTAL_PATTERNS as $kind => $pattern) {
            if (in_array($kind, ['urls', 'emails', 'globals'], true)) {
                continue;
            }
            preg_match_all($pattern, $json, $m);
            $found = array_values(array_unique($m[0]));
            if ($kind === 'uf_fields') {
                $found = array_values(array_diff($found, Analyzer::SYSTEM_UF));
            }
            if (!$found) {
                continue;
            }
            $message = "«сырые» идентификаторы портала ({$kind}): " . implode(', ', array_slice($found, 0, 5))
                . '. Лучше подставлять их из снимка: {{вид:Название}}';
            $this->strict ? $this->error('спецификация', $message) : $this->warning('спецификация', $message);
        }
    }

    /** Детерминированное имя действия: одна и та же спецификация даёт тот же файл. */
    public static function activityName(string $seed): string
    {
        $hash = md5($seed);
        $parts = [];
        for ($i = 0; $i < 4; $i++) {
            $parts[] = (int) hexdec(substr($hash, $i * 5, 5)) % 100000;
        }
        return 'A' . implode('_', $parts);
    }

    /** @return array[] узлы-действия */
    private function buildSteps(mixed $steps, string $path): array
    {
        if (!is_array($steps)) {
            $this->error($path, 'ожидается список шагов');
            return [];
        }
        $nodes = [];
        foreach (array_values($steps) as $i => $step) {
            $node = $this->buildStep($step, "{$path}[{$i}]");
            if ($node !== null) {
                $nodes[] = $node;
            }
        }
        return $nodes;
    }

    private function buildStep(mixed $step, string $path): ?array
    {
        if (!is_array($step) || count($step) !== 1) {
            $this->error($path, 'шаг должен быть объектом с одним действием, например «- change_stage: {…}»');
            return null;
        }
        $key = (string) array_key_first($step);
        $body = $step[$key];
        if ($body === null) {
            $body = [];
        }
        if (!is_array($body)) {
            $this->error($path, "у действия «{$key}» должны быть свойства в виде объекта");
            return null;
        }

        try {
            $type = $this->catalog->resolveType($key);
        } catch (BptException $e) {
            $this->error($path, $e->getMessage());
            return null;
        }
        if ($this->catalog->isForbidden($type)) {
            $this->error("{$path} ({$key})", 'запрещено генерировать: ' . $this->catalog->forbiddenReason($type));
            return null;
        }

        $label = "{$path} ({$key})";
        $props = [];
        $meta = [];
        foreach ($body as $prop => $value) {
            if (in_array((string) $prop, self::META_KEYS, true)) {
                $meta[(string) $prop] = $value;
            } else {
                $props[(string) $prop] = $value;
            }
        }
        if ($this->catalog->shape($type) === 'loop') {
            $props += $this->conditionProps($meta, $label, true);
        }
        $props = $this->substitute($props, $label);

        $node = $this->makeNode($type, $props, $meta, $label, $path);
        $node['Children'] = $this->buildChildren($type, $meta, $label, $path);
        return $node;
    }

    /** Узел действия: свойства из спецификации, остальное — из каталога. */
    private function makeNode(string $type, array $props, array $meta, string $label, string $path): array
    {
        $unknown = [];
        foreach (array_keys($props) as $prop) {
            if ($this->catalog->propType($type, (string) $prop) === null) {
                $unknown[] = $prop;
            }
        }
        if ($unknown) {
            $this->error($label, 'неизвестные свойства: ' . implode(', ', $unknown)
                . '. Список свойств: php bpt.php catalog ' . $type);
            $props = array_diff_key($props, array_flip($unknown));
        }

        $props = $this->catalog->normalizeProps($type, $props);
        if (isset($meta['raw']) && is_array($meta['raw'])) {
            $this->warning($label, 'свойства вне каталога передаются как есть: '
                . implode(', ', array_keys($meta['raw'])));
            $props += $meta['raw'];
        }

        foreach ($this->catalog->requiredProps($type) as $required) {
            $value = $props[$required] ?? null;
            if ($value === null || $value === '' || $value === []) {
                $this->error($label, "нет обязательного свойства {$required}");
            }
        }

        $ordered = [];
        foreach (array_keys($this->catalog->entry($type)['props']) as $prop) {
            if (array_key_exists($prop, $props)) {
                $ordered[$prop] = $props[$prop];
            } elseif (array_key_exists($prop, $this->catalog->defaults($type))) {
                $ordered[$prop] = $this->catalog->defaults($type)[$prop];
            }
        }
        foreach ($props as $prop => $value) {   // свойства из raw и общие
            $ordered[$prop] ??= $value;
        }
        $ordered['Title'] = isset($meta['title']) ? (string) $meta['title'] : $this->catalog->title($type);
        $ordered['EditorComment'] = isset($meta['comment']) ? (string) $meta['comment'] : '';

        $name = $this->makeName($meta, $label, $path);
        $this->nodeLabels[$name] = $label;
        if (isset($meta['id'])) {
            $id = (string) $meta['id'];
            if (isset($this->steps[$id])) {
                $this->error($label, "повтор id «{$id}»: он уже занят шагом {$this->steps[$id]['label']}");
            } else {
                $this->steps[$id] = ['name' => $name, 'type' => $type, 'props' => $ordered, 'label' => $label];
            }
        }

        return [
            'Type'       => $type,
            'Name'       => $name,
            'Activated'  => ($meta['off'] ?? false) ? 'N' : 'Y',
            'Node'       => null,
            'Properties' => $ordered,
            'Children'   => [],
        ];
    }

    /** Дети действия зависят от его формы вложенности в каталоге. */
    private function buildChildren(string $type, array $meta, string $label, string $path): array
    {
        return match ($this->catalog->shape($type)) {
            'ifelse'           => $this->buildBranches($meta, $label, $path),
            'parallel'         => $this->buildParallel($meta, $label, $path),
            'loop', 'block'    => [$this->makeSequence($meta['steps'] ?? [], "{$path}.steps")],
            'waiting-branches' => [
                $this->makeSequence($meta['on_yes'] ?? [], "{$path}.on_yes"),
                $this->makeSequence($meta['on_no'] ?? [], "{$path}.on_no"),
            ],
            default            => $this->noChildren($meta, $label),
        };
    }

    private function noChildren(array $meta, string $label): array
    {
        foreach (['steps', 'branches', 'on_yes', 'on_no'] as $key) {
            if (isset($meta[$key])) {
                $this->error($label, "у этого действия не бывает вложенных шагов ({$key})");
            }
        }
        return [];
    }

    /** Ветки условия: IfElseBranchActivity с условием и шагами напрямую, без последовательности. */
    private function buildBranches(array $meta, string $label, string $path): array
    {
        $branches = $meta['branches'] ?? null;
        if (!is_array($branches) || count($branches) < 2) {
            $this->error($label, 'у условия должно быть минимум две ветки (branches)');
            $branches = is_array($branches) ? $branches : [];
        }
        $nodes = [];
        foreach (array_values($branches) as $i => $branch) {
            $branchPath = "{$path}.branches[{$i}]";
            if (!is_array($branch)) {
                $this->error($branchPath, 'ветка должна быть объектом {title, when|else, steps}');
                continue;
            }
            $props = $this->substitute($this->conditionProps($branch, $branchPath, false), $branchPath);
            $props['Title'] = (string) ($branch['title'] ?? 'Ветка');
            $props['EditorComment'] = (string) ($branch['comment'] ?? '');
            $nodes[] = [
                'Type'       => 'IfElseBranchActivity',
                'Name'       => $this->makeName($branch, $branchPath, $branchPath),
                'Activated'  => ($branch['off'] ?? false) ? 'N' : 'Y',
                'Node'       => null,
                'Properties' => $props,
                'Children'   => $this->buildSteps($branch['steps'] ?? [], "{$branchPath}.steps"),
            ];
        }
        return $nodes;
    }

    private function buildParallel(array $meta, string $label, string $path): array
    {
        $branches = $meta['branches'] ?? null;
        if (!is_array($branches) || count($branches) < 2) {
            $this->error($label, 'у параллельного выполнения должно быть минимум две ветки (branches)');
            $branches = is_array($branches) ? $branches : [];
        }
        $nodes = [];
        foreach (array_values($branches) as $i => $branch) {
            $nodes[] = $this->makeSequence($branch, "{$path}.branches[{$i}]");
        }
        return $nodes;
    }

    /**
     * Последовательность действий. На входе либо список шагов, либо объект
     * {title, name, off, steps} — так сохраняются свои заголовки веток.
     */
    private function makeSequence(mixed $branch, string $path): array
    {
        $meta = [];
        $steps = $branch;
        if (is_array($branch) && !array_is_list($branch)) {
            $meta = $branch;
            $steps = $branch['steps'] ?? [];
        }
        $title = isset($meta['title']) ? (string) $meta['title'] : self::SEQUENCE_TITLE;
        return [
            'Type'       => 'SequenceActivity',
            'Name'       => $this->makeName($meta, $path, $path),
            'Activated'  => ($meta['off'] ?? false) ? 'N' : 'Y',
            'Node'       => null,
            'Properties' => ['Title' => $title],
            'Children'   => $this->buildSteps($steps, "{$path}.steps"),
        ];
    }

    /** Условие ветки или цикла: when: {fieldcondition: …} либо else: true. */
    private function conditionProps(array $meta, string $label, bool $required): array
    {
        $hasElse = (bool) ($meta['else'] ?? false);
        if ($hasElse && isset($meta['when'])) {
            $this->error($label, 'when и else нельзя указывать вместе');
            return ['truecondition' => '1'];
        }
        if ($hasElse) {
            return ['truecondition' => '1'];
        }
        if (!isset($meta['when'])) {
            if ($required) {
                $this->error($label, 'нет условия (when)');
            }
            return [];
        }
        $when = $meta['when'];
        if (!is_array($when) || count($when) !== 1) {
            $this->error($label, 'условие — объект с одним ключом: fieldcondition,'
                . ' propertyvariablecondition, mixedcondition или truecondition');
            return [];
        }
        $kind = (string) array_key_first($when);
        if ($this->catalog->propType('IfElseBranchActivity', $kind) === null) {
            $this->error($label, "неизвестный вид условия «{$kind}»");
            return [];
        }
        return [$kind => $this->catalog->normalizeValue($this->catalog->propType('IfElseBranchActivity', $kind), $when[$kind])];
    }

    private function makeName(array $meta, string $label, string $path): string
    {
        $name = isset($meta['name']) ? (string) $meta['name'] : null;
        if ($name === null) {
            $seed = $path . '|' . (string) ($meta['id'] ?? '');
            $name = self::activityName($seed);
            $salt = 0;
            while (isset($this->usedNames[$name])) {
                $name = self::activityName($seed . '|' . (++$salt));
            }
        }
        if (isset($this->usedNames[$name])) {
            $this->error($label, "имя действия {$name} уже занято ({$this->usedNames[$name]})");
        }
        $this->usedNames[$name] = $label;
        return $name;
    }

    /** Описания параметров, переменных и констант. */
    private function definitions(mixed $definitions, string $path): array
    {
        if (!is_array($definitions)) {
            $this->error($path, 'ожидается объект: код → описание поля');
            return [];
        }
        $out = [];
        foreach ($definitions as $code => $definition) {
            if (!is_array($definition)) {
                $this->error("{$path}.{$code}", 'описание поля должно быть объектом {Name, Type, …}');
                continue;
            }
            $out[(string) $code] = Catalog::normalizeDefinition($this->substitute($definition, "{$path}.{$code}"));
        }
        return $out;
    }

    /** Подстановка {{вид:Название}} из снимка портала. */
    private function substitute(mixed $value, string $label): mixed
    {
        if (!Snapshot::hasPlaceholder($value)) {
            return $value;
        }
        if ($this->snapshot === null) {
            $this->error($label, 'в спецификации есть плейсхолдеры {{…}}, но не задан снимок портала'
                . ' (--portal <файл>)');
            return $value;
        }
        $errors = [];
        $value = $this->snapshot->substitute($value, $label, $errors);
        array_push($this->errors, ...$errors);
        return $value;
    }

    private function error(string $where, string $message): void
    {
        $this->errors[] = "{$where}: {$message}";
    }

    private function warning(string $where, string $message): void
    {
        $this->warnings[] = "{$where}: {$message}";
    }
}
