<?php
/**
 * Проверки задачи (acceptance.yaml): как стартует процесс, на каком элементе гоняем сценарии, какие
 * шаги и ожидания, какой чек-лист неоднозначностей. Формат — tools/bpt-eval/DESIGN.md §5.
 */

declare(strict_types=1);

final class Acceptance
{
    /** Вид задачи: сборка по ТЗ, ревью чужого шаблона, правка готового. */
    public const KINDS = ['build', 'review', 'modify'];
    public const STARTS = ['create', 'manual'];
    public const ACTIONS = ['approve', 'reject', 'review', 'provide', 'complete'];
    public const EXPECT_KEYS = ['stage', 'fields', 'history_contains', 'notify', 'task_created',
        'observers', 'process', 'no_task_for'];
    public const PROCESS_STATES = ['completed', 'running'];

    private function __construct(private readonly array $data)
    {
    }

    public static function load(string $file): self
    {
        if (!is_file($file)) {
            throw new EvalException("нет файла проверок: {$file}");
        }
        return self::fromArray(SpecReader::read($file), $file);
    }

    public static function fromArray(array $data, string $source): self
    {
        $errors = [];
        $out = [
            'task'       => self::str($data, 'task', $errors),
            'document'   => self::str($data, 'document', $errors),
            'start'      => (string) ($data['start'] ?? 'create'),
            'parameters' => is_array($data['parameters'] ?? []) ? ($data['parameters'] ?? []) : [],
            'item'       => is_array($data['item'] ?? []) ? ($data['item'] ?? []) : [],
            'kind'       => (string) ($data['kind'] ?? 'build'),
            'input'      => trim((string) ($data['input'] ?? '')),
            'scenarios'  => [],
            'checklist'  => [],
            'defects'    => [],
            'traps'      => [],
            'preserved'  => array_values(array_filter(array_map(
                fn ($t) => trim((string) $t), (array) ($data['preserved'] ?? [])))),
        ];
        if (!in_array($out['kind'], self::KINDS, true)) {
            $errors[] = "kind: «{$out['kind']}» — допустимо: " . implode(', ', self::KINDS);
        }
        if (!in_array($out['start'], self::STARTS, true)) {
            $errors[] = "start: «{$out['start']}» — допустимо: " . implode(', ', self::STARTS);
        }
        foreach (array_values((array) ($data['scenarios'] ?? [])) as $i => $scenario) {
            $out['scenarios'][] = self::scenario((array) $scenario, "scenarios[{$i}]", $errors);
        }
        foreach (array_values((array) ($data['checklist'] ?? [])) as $i => $item) {
            if (is_string($item) && trim($item) !== '') {
                $out['checklist'][] = ['text' => trim($item), 'required' => true];
            } elseif (is_array($item) && trim((string) ($item['text'] ?? '')) !== '') {
                $out['checklist'][] = ['text' => trim((string) $item['text']), 'required' => (bool) ($item['required'] ?? true)];
            } else {
                $errors[] = "checklist[{$i}]: нужна строка или {text, required}";
            }
        }
        foreach (['defects', 'traps'] as $key) {
            foreach (array_values((array) ($data[$key] ?? [])) as $i => $item) {
                $id = is_array($item) ? trim((string) ($item['id'] ?? '')) : '';
                $text = is_array($item) ? trim((string) ($item['text'] ?? '')) : '';
                if ($id === '' || $text === '') {
                    $errors[] = "{$key}[{$i}]: нужны id и text";
                    continue;
                }
                if (in_array($id, array_column(array_merge($out['defects'], $out['traps']), 'id'), true)) {
                    // Итог считается по id: повтор молча удвоил бы дефект или сделал ловушку
                    // неотличимой от дефекта
                    $errors[] = "{$key}[{$i}]: id «{$id}» уже занят";
                    continue;
                }
                $out[$key][] = ['id' => $id, 'text' => $text];
            }
        }
        if ($out['kind'] !== 'modify' && $out['preserved']) {
            $errors[] = 'preserved: только для вида «modify» — сохранять нечего';
        }
        // Вид задачи диктует, чем её вообще можно оценить: ревью — списком дефектов, правка —
        // сценариями на стенде. Без этой проверки задача молча превратилась бы в «0 из 0».
        if ($out['kind'] !== 'build' && $out['input'] === '') {
            $errors[] = "input: для вида «{$out['kind']}» нужен входной шаблон";
        }
        if ($out['kind'] === 'review' && !$out['defects']) {
            $errors[] = 'defects: ревью оценивается списком дефектов — он пуст';
        }
        if ($out['kind'] === 'review' && $out['scenarios']) {
            $errors[] = 'scenarios: у ревью нет своего процесса — сценарии не применимы';
        }
        if ($out['kind'] !== 'review' && ($out['defects'] || $out['traps'])) {
            $errors[] = 'defects/traps: только для вида «review»';
        }
        if ($out['kind'] === 'modify' && !$out['scenarios']) {
            $errors[] = 'scenarios: правка проверяется сценариями на стенде — их нет';
        }
        if ($out['kind'] !== 'review' && !$out['scenarios'] && !$out['checklist']) {
            $errors[] = 'нет ни сценариев, ни чек-листа';
        }
        if ($errors) {
            throw new EvalException("{$source}: " . implode('; ', $errors));
        }
        return new self($out);
    }

    public function task(): string { return $this->data['task']; }
    public function document(): string { return $this->data['document']; }
    public function start(): string { return $this->data['start']; }
    public function parameters(): array { return $this->data['parameters']; }
    public function item(): array { return $this->data['item']; }
    public function scenarios(): array { return $this->data['scenarios']; }
    public function checklist(): array { return $this->data['checklist']; }
    public function kind(): string { return $this->data['kind']; }
    public function input(): string { return $this->data['input']; }
    public function defects(): array { return $this->data['defects']; }
    public function traps(): array { return $this->data['traps']; }
    public function preserved(): array { return $this->data['preserved']; }
    public function toArray(): array { return $this->data; }

    /** @return string[] все роли, упомянутые в шагах и проверках, по алфавиту */
    public function roleNames(): array
    {
        $roles = [];
        foreach ($this->data['scenarios'] as $scenario) {
            foreach ($scenario['steps'] as $step) {
                $roles[] = $step['task_for'];
            }
            $expect = $scenario['expect'];
            foreach ($expect['notify'] ?? [] as $n) {
                $roles[] = $n['to'];
            }
            foreach ($expect['task_created'] ?? [] as $t) {
                $roles[] = $t['responsible'];
            }
            array_push($roles, ...($expect['observers'] ?? []), ...($expect['no_task_for'] ?? []));
        }
        $roles = array_values(array_unique($roles));
        sort($roles);
        return $roles;
    }

    private static function scenario(array $s, string $path, array &$errors): array
    {
        $out = ['name' => self::str($s, 'name', $errors, $path), 'steps' => [], 'expect' => [],
            // поля элемента сценария — поверх item задачи (T02: разные суммы)
            'item' => is_array($s['item'] ?? null) ? $s['item'] : []];
        foreach (array_values((array) ($s['steps'] ?? [])) as $i => $step) {
            $p = "{$path}.steps[{$i}]";
            $step = (array) $step;
            $do = (string) ($step['do'] ?? '');
            if (!in_array($do, self::ACTIONS, true)) {
                $errors[] = "{$p}.do: неизвестное действие «{$do}»; допустимо: " . implode(', ', self::ACTIONS);
            }
            $values = is_array($step['values'] ?? null) ? $step['values'] : [];
            if ($do === 'provide' && !$values) {
                $errors[] = "{$p}: для provide нужны values: {поле: значение}";
            }
            $out['steps'][] = [
                'task_for'       => self::str($step, 'task_for', $errors, $p),
                'do'             => $do,
                'comment'        => (string) ($step['comment'] ?? ''),
                'values'         => $values,
                'title_contains' => isset($step['title_contains']) ? (string) $step['title_contains'] : null,
                'optional'       => (bool) ($step['optional'] ?? false),
            ];
        }
        foreach ((array) ($s['expect'] ?? []) as $key => $value) {
            if (!in_array($key, self::EXPECT_KEYS, true)) {
                $errors[] = "{$path}.expect.{$key}: неизвестная проверка; допустимо: " . implode(', ', self::EXPECT_KEYS);
                continue;
            }
            if ($key === 'process' && !in_array($value, self::PROCESS_STATES, true)) {
                $errors[] = "{$path}.expect.process: допустимо " . implode(', ', self::PROCESS_STATES);
            }
            if (in_array($key, ['notify', 'task_created'], true)) {
                $need = $key === 'notify' ? 'to' : 'responsible';
                foreach (array_values((array) $value) as $j => $row) {
                    if (trim((string) ($row[$need] ?? '')) === '') {
                        $errors[] = "{$path}.expect.{$key}[{$j}]: нужен {$need}";
                    }
                }
            }
            $out['expect'][$key] = $value;
        }
        return $out;
    }

    private static function str(array $data, string $key, array &$errors, string $path = ''): string
    {
        $value = trim((string) ($data[$key] ?? ''));
        if ($value === '') {
            $errors[] = ltrim("{$path}.{$key}", '.') . ': обязательное поле';
        }
        return $value;
    }
}
