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

    private array $errors = [];
    private array $warnings = [];
    private array $usedNames = [];

    public function __construct(
        private readonly Catalog $catalog,
        private readonly bool $strict = false,
    ) {
    }

    /** @return array{bpt: array, errors: string[], warnings: string[]} */
    public function compile(array $spec): array
    {
        $this->errors = [];
        $this->warnings = [];
        $this->usedNames = [];

        $kind = (string) ($spec['kind'] ?? 'designer');
        if (!in_array($kind, ['designer', 'robots'], true)) {
            $this->error('kind', "должно быть designer или robots, получено «{$kind}»");
            $kind = 'designer';
        }
        $name = trim((string) ($spec['name'] ?? ''));
        if ($name === '') {
            $this->error('name', 'у процесса должно быть имя');
        }
        if (!isset($spec['bizproc'])) {
            $this->warning('bizproc', 'не указана версия формата спецификации (bizproc: 1)');
        }

        $children = $this->buildSteps($spec['steps'] ?? [], 'steps', $kind);
        $root = [
            'Type'       => 'SequentialWorkflowActivity',
            'Name'       => 'Template',
            'Activated'  => 'Y',
            'Node'       => null,
            'Properties' => ['Title' => $kind === 'robots' ? Analyzer::AUTOMATION_TITLE : $name]
                + $this->catalog->defaults('SequentialWorkflowActivity'),
            'Children'   => $children,
        ];

        $this->warning('DOCUMENT_FIELDS', 'собрано без DOCUMENT_FIELDS: перед загрузкой проверьте'
            . ' поведение на тестовом портале');

        return [
            'bpt' => [
                'VERSION'         => 2,
                'TEMPLATE'        => [$root],
                'PARAMETERS'      => $this->definitions($spec['parameters'] ?? [], 'parameters'),
                'VARIABLES'       => $this->definitions($spec['variables'] ?? [], 'variables'),
                'CONSTANTS'       => $this->definitions($spec['constants'] ?? [], 'constants'),
                'DOCUMENT_FIELDS' => [],
            ],
            'errors'   => $this->errors,
            'warnings' => $this->warnings,
        ];
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
    private function buildSteps(mixed $steps, string $path, string $kind): array
    {
        if (!is_array($steps)) {
            $this->error($path, 'ожидается список шагов');
            return [];
        }
        $nodes = [];
        foreach (array_values($steps) as $i => $step) {
            $node = $this->buildStep($step, "{$path}[{$i}]", $kind);
            if ($node !== null) {
                $nodes[] = $node;
            }
        }
        return $nodes;
    }

    private function buildStep(mixed $step, string $path, string $kind): ?array
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

        $node = $this->makeNode($type, $props, $meta, $label, $path);
        $node['Children'] = $this->buildChildren($type, $meta, $label, $path, $kind);
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

        return [
            'Type'       => $type,
            'Name'       => $this->makeName($meta, $label, $path),
            'Activated'  => ($meta['off'] ?? false) ? 'N' : 'Y',
            'Node'       => null,
            'Properties' => $ordered,
            'Children'   => [],
        ];
    }

    /** Дети действия зависят от его формы вложенности в каталоге. */
    private function buildChildren(string $type, array $meta, string $label, string $path, string $kind): array
    {
        return match ($this->catalog->shape($type)) {
            'ifelse'           => $this->buildBranches($meta, $label, $path, $kind),
            'parallel'         => $this->buildParallel($meta, $label, $path, $kind),
            'loop', 'block'    => [$this->makeSequence($meta['steps'] ?? [], "{$path}.steps", $kind)],
            'waiting-branches' => [
                $this->makeSequence($meta['on_yes'] ?? [], "{$path}.on_yes", $kind),
                $this->makeSequence($meta['on_no'] ?? [], "{$path}.on_no", $kind),
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
    private function buildBranches(array $meta, string $label, string $path, string $kind): array
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
            $props = $this->conditionProps($branch, $branchPath, false);
            $props['Title'] = (string) ($branch['title'] ?? 'Ветка');
            $props['EditorComment'] = (string) ($branch['comment'] ?? '');
            $nodes[] = [
                'Type'       => 'IfElseBranchActivity',
                'Name'       => $this->makeName($branch, $branchPath, $branchPath),
                'Activated'  => ($branch['off'] ?? false) ? 'N' : 'Y',
                'Node'       => null,
                'Properties' => $props,
                'Children'   => $this->buildSteps($branch['steps'] ?? [], "{$branchPath}.steps", $kind),
            ];
        }
        return $nodes;
    }

    private function buildParallel(array $meta, string $label, string $path, string $kind): array
    {
        $branches = $meta['branches'] ?? null;
        if (!is_array($branches) || count($branches) < 2) {
            $this->error($label, 'у параллельного выполнения должно быть минимум две ветки (branches)');
            $branches = is_array($branches) ? $branches : [];
        }
        $nodes = [];
        foreach (array_values($branches) as $i => $branch) {
            $branchPath = "{$path}.branches[{$i}]";
            // Ветка — либо список шагов, либо объект {title, steps}
            $steps = is_array($branch) && array_is_list($branch) ? $branch : ($branch['steps'] ?? []);
            $title = is_array($branch) && !array_is_list($branch) ? ($branch['title'] ?? null) : null;
            $nodes[] = $this->makeSequence($steps, $branchPath, $kind, $title === null ? null : (string) $title);
        }
        return $nodes;
    }

    private function makeSequence(mixed $steps, string $path, string $kind, ?string $title = null): array
    {
        return [
            'Type'       => 'SequenceActivity',
            'Name'       => $this->makeName([], $path, $path),
            'Activated'  => 'Y',
            'Node'       => null,
            'Properties' => ['Title' => $title
                ?? ($kind === 'robots' ? 'Automation sequence' : 'Последовательность действий')],
            'Children'   => $this->buildSteps($steps, "{$path}.steps", $kind),
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
            $out[(string) $code] = Catalog::normalizeDefinition($definition);
        }
        return $out;
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
