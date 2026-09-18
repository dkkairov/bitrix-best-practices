<?php
/**
 * Разбор .bpt в спецификацию процесса: обратная операция к Compiler.
 *
 * Свойства, равные значениям по умолчанию, опускаются; ссылки {=A…:Результат} переписываются
 * на логические id шагов; со снимком портала идентификаторы заменяются плейсхолдерами.
 */

declare(strict_types=1);

final class Decompiler
{
    private array $ids = [];        // имя действия → логический id
    private array $warnings = [];
    private int $counter = 0;

    public function __construct(
        private readonly Catalog $catalog,
        private readonly ?Snapshot $snapshot = null,
        private readonly bool $keepNames = false,
    ) {
    }

    /** @return array{spec: array, warnings: string[]} */
    public function decompile(array $bpt): array
    {
        $this->ids = [];
        $this->warnings = [];
        $this->counter = 0;

        $root = $bpt['TEMPLATE'][0];
        $kind = ($root['Properties']['Title'] ?? '') === Analyzer::AUTOMATION_TITLE ? 'robots' : 'designer';
        $this->assignIds($root, $bpt);

        $spec = ['bizproc' => 1, 'name' => (string) ($root['Properties']['Title'] ?? 'Процесс'), 'kind' => $kind];
        if ($kind === 'robots') {
            $spec['name'] = 'Роботы стадии';   // у шаблона роботов служебный заголовок, не имя процесса
        }
        foreach (['parameters' => 'PARAMETERS', 'variables' => 'VARIABLES', 'constants' => 'CONSTANTS'] as $key => $section) {
            if (!empty($bpt[$section])) {
                $spec[$key] = $this->rewrite($bpt[$section]);
            }
        }
        $spec['steps'] = $this->stepsFrom($root['Children'] ?? [], $kind);

        return ['spec' => $spec, 'warnings' => $this->warnings];
    }

    /** Логические id получают только те шаги, на которые есть ссылки. */
    private function assignIds(array $root, array $bpt): void
    {
        $json = BptFile::flatJson($bpt['TEMPLATE']);
        preg_match_all('/\{=(A\d+_\d+_\d+_\d+):/', $json, $m);
        $referenced = array_flip(array_unique($m[1]));
        $used = [];
        $walk = function (array $node) use (&$walk, $referenced, &$used): void {
            $name = (string) ($node['Name'] ?? '');
            if (isset($referenced[$name])) {
                $base = $this->catalog->has($node['Type']) ? ($this->catalog->alias($node['Type']) ?? 'step') : 'step';
                $id = $base . (++$this->counter);
                while (isset($used[$id])) {
                    $id = $base . (++$this->counter);
                }
                $used[$id] = true;
                $this->ids[$name] = $id;
            }
            foreach ($node['Children'] ?? [] as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };
        $walk($root);
    }

    /** @return array[] шаги спецификации */
    private function stepsFrom(array $children, string $kind): array
    {
        $steps = [];
        foreach ($children as $child) {
            if (is_array($child)) {
                $steps[] = $this->stepFrom($child, $kind);
            }
        }
        return $steps;
    }

    private function stepFrom(array $node, string $kind): array
    {
        $type = (string) $node['Type'];
        $known = $this->catalog->has($type);
        if (!$known) {
            $this->warnings[] = "тип {$type} не описан в каталоге — добавьте его, иначе сборка не пройдёт";
        }
        $alias = $known ? ($this->catalog->alias($type) ?? $type) : $type;
        $props = $node['Properties'] ?? [];
        $shape = $known ? $this->catalog->shape($type) : 'leaf';

        $body = [];
        if (isset($this->ids[$node['Name'] ?? ''])) {
            $body['id'] = $this->ids[$node['Name']];
        }
        if ($this->keepNames) {
            $body['name'] = (string) $node['Name'];
        }
        $title = $props['Title'] ?? null;
        if ($title !== null && (!$known || (string) $title !== $this->catalog->title($type))) {
            $body['title'] = $this->rewrite($title);
        }
        if (($props['EditorComment'] ?? '') !== '') {
            $body['comment'] = $this->rewrite($props['EditorComment']);
        }
        if (($node['Activated'] ?? 'Y') === 'N') {
            $body['off'] = true;
        }
        if ($shape === 'loop') {
            $when = $this->conditionFrom($props);
            if ($when !== null) {
                $body['when'] = $when;
            }
        }

        $raw = [];
        foreach ($props as $prop => $value) {
            $prop = (string) $prop;
            if (in_array($prop, ['Title', 'EditorComment'], true)) {
                continue;
            }
            if ($shape === 'loop' && $this->isConditionProp($prop)) {
                continue;   // ушло в when
            }
            if ($known && $this->catalog->propType($type, $prop) === null) {
                $raw[$prop] = $this->rewrite($value);
                continue;
            }
            $defaults = $known ? $this->catalog->defaults($type) : [];
            if (array_key_exists($prop, $defaults) && $defaults[$prop] === $value) {
                continue;   // значение по умолчанию — не пишем
            }
            $body[$prop] = $this->rewrite($value);
        }
        if ($raw) {
            $body['raw'] = $raw;
            $this->warnings[] = "{$type}: свойства вне каталога сохранены в raw: " . implode(', ', array_keys($raw));
        }

        $body += $this->childrenFrom($node, $shape, $kind);
        return [$alias => $body];
    }

    /** Дети действия в терминах спецификации: steps, branches, on_yes/on_no. */
    private function childrenFrom(array $node, string $shape, string $kind): array
    {
        $children = array_values(array_filter($node['Children'] ?? [], 'is_array'));
        switch ($shape) {
            case 'ifelse':
                $branches = [];
                foreach ($children as $branch) {
                    $item = [];
                    $title = $branch['Properties']['Title'] ?? null;
                    if ($title !== null) {
                        $item['title'] = $this->rewrite($title);
                    }
                    if (($branch['Properties']['EditorComment'] ?? '') !== '') {
                        $item['comment'] = $this->rewrite($branch['Properties']['EditorComment']);
                    }
                    if (($branch['Activated'] ?? 'Y') === 'N') {
                        $item['off'] = true;
                    }
                    if ($this->keepNames) {
                        $item['name'] = (string) $branch['Name'];
                    }
                    $when = $this->conditionFrom($branch['Properties'] ?? []);
                    if ($when !== null) {
                        if (array_key_first($when) === 'truecondition') {
                            $item['else'] = true;
                        } else {
                            $item['when'] = $when;
                        }
                    }
                    $item['steps'] = $this->stepsFrom($branch['Children'] ?? [], $kind);
                    $branches[] = $item;
                }
                return ['branches' => $branches];

            case 'parallel':
                return ['branches' => array_map(fn (array $seq) => $this->sequenceFrom($seq, $kind), $children)];

            case 'waiting-branches':
                return [
                    'on_yes' => $this->sequenceFrom($children[0] ?? [], $kind),
                    'on_no'  => $this->sequenceFrom($children[1] ?? [], $kind),
                ];

            case 'loop':
            case 'block':
                return ['steps' => $this->sequenceFrom($children[0] ?? [], $kind)];

            default:
                return [];
        }
    }

    /**
     * Последовательность: обычно просто список шагов, но если у неё свой заголовок
     * или имя нужно сохранить — объект {title, name, steps}.
     */
    private function sequenceFrom(array $sequence, string $kind): array
    {
        if ($sequence === []) {
            return [];
        }
        $steps = $this->stepsFrom($sequence['Children'] ?? [], $kind);
        $title = (string) ($sequence['Properties']['Title'] ?? '');
        $default = $kind === 'robots' ? 'Automation sequence' : 'Последовательность действий';
        if ($title === $default && !$this->keepNames) {
            return $steps;
        }
        $item = [];
        if ($title !== $default) {
            $item['title'] = $title;
        }
        if ($this->keepNames) {
            $item['name'] = (string) $sequence['Name'];
        }
        $item['steps'] = $steps;
        return $item;
    }

    private function isConditionProp(string $prop): bool
    {
        return str_ends_with(strtolower($prop), 'condition');
    }

    private function conditionFrom(array $props): ?array
    {
        foreach ($props as $prop => $value) {
            if ($this->isConditionProp((string) $prop)) {
                return [(string) $prop => $this->rewrite($value)];
            }
        }
        return null;
    }

    /** Ссылки на действия → {=@id:…}; известные идентификаторы портала → {{вид:Название}}. */
    private function rewrite(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = preg_replace_callback('/\{=(A\d+_\d+_\d+_\d+):/', function (array $m) {
                if (isset($this->ids[$m[1]])) {
                    return '{=@' . $this->ids[$m[1]] . ':';
                }
                $this->warnings[] = "ссылка на несуществующее действие {$m[1]} оставлена как есть";
                return $m[0];
            }, $value);
            return $this->snapshot === null ? $value : $this->toPlaceholders($value);
        }
        if (!is_array($value)) {
            return $value;
        }
        $out = [];
        foreach ($value as $key => $item) {
            $out[is_string($key) ? $this->rewrite($key) : $key] = $this->rewrite($item);
        }
        return $out;
    }

    private function toPlaceholders(string $value): string
    {
        foreach (Snapshot::KINDS as $kind => $section) {
            $name = $this->snapshot?->nameFor($kind, $value);
            if ($name !== null) {
                return "{{{$kind}:{$name}}}";   // значение целиком
            }
        }
        // Идентификаторы внутри текста: поля, стадии, люди и группы, но не числовые id
        foreach (['field', 'stage', 'user', 'group'] as $kind) {
            foreach ($this->snapshot?->section($kind) ?? [] as $name => $id) {
                if (is_string($id) && $id !== '' && str_contains($value, $id)) {
                    $value = str_replace($id, "{{{$kind}:{$name}}}", $value);
                }
            }
        }
        return $value;
    }
}
