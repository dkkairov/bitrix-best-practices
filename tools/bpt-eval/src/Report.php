<?php
/**
 * Сводка прогона: по задаче — сборка, импорт, сценарии, чек-лист, итог, расход агента, причина и
 * категория провала; по прогону — доля пройденных против порога. В долю не входят (но видны в
 * таблице): сбои прогонщика (harness) — их чинят и задачу перегоняют; и задачи, у которых нет вовсе
 * данных чек-листа при обязательных пунктах — шаг «чек-лист» не выполнен, это не провал навыка.
 */

declare(strict_types=1);

final class Report
{
    public const THRESHOLD = 0.8;

    private function __construct(private readonly array $rows, private readonly string $run)
    {
    }

    public static function fromRunDir(string $runDir, string $tasksDir): self
    {
        $results = $checklists = $acceptances = [];
        foreach (glob(rtrim($runDir, '/\\') . '/*/result.json') ?: [] as $file) {
            $result = self::readJson($file);
            $task = (string) $result['task'];
            $results[$task] = $result;
            $checklistFile = dirname($file) . '/checklist.json';
            if (is_file($checklistFile)) {
                try {
                    $checklists[$task] = self::readJson($checklistFile);
                } catch (EvalException $e) {
                    // CHECKLIST_PROMPT.md требует валидный JSON, но проверяющий — дешёвая модель и
                    // иногда ошибается (см. пилот). Битый файл не должен ронять report для всего
                    // прогона: пункты задачи считаются неподнятыми, note — явная пометка причины.
                    $checklists[$task] = ['items' => [], 'broken' => true];
                }
            }
            $taskDirs = glob(rtrim($tasksDir, '/\\') . "/{$task}-*", GLOB_ONLYDIR) ?: [];
            if ($taskDirs) {
                $acceptances[$task] = Acceptance::load($taskDirs[0] . '/acceptance.yaml');
            }
        }
        $agentsFile = rtrim($runDir, '/\\') . '/agents.json';
        $agents = is_file($agentsFile) ? self::readJson($agentsFile) : [];
        ksort($results);
        return self::fromData($results, $checklists, $acceptances, $agents, basename(rtrim($runDir, '/\\')));
    }

    /**
     * Ключ пункта чек-листа. Проверяющий — модель: текст пункта он возвращает то дословно, то
     * обёрнутым в кавычки, то с заменой «ёлочек» на другие. Побуквенная сверка из-за этого
     * отмечала «не пройдена» задачи с полным чек-листом (прогон main-7), поэтому кавычки и
     * лишние пробелы при сопоставлении не учитываем.
     */
    private static function itemKey(string $text): string
    {
        $text = (string) preg_replace('/[«»„“”"\'‚‘’]/u', '', $text);
        $text = (string) preg_replace('/\s+/u', ' ', $text);
        return mb_strtolower(trim($text));
    }

    /** Разбор JSON-файла прогона (result.json/checklist.json/agents.json): битый файл — EvalException с путём, а не голый JsonException. */
    public static function readJson(string $file): array
    {
        try {
            return json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new EvalException("не удалось разобрать {$file}: " . $e->getMessage());
        }
    }

    public static function fromData(array $results, array $checklists, array $acceptances, array $agents, string $run): self
    {
        $rows = [];
        foreach ($results as $task => $r) {
            $scenarios = $r['scenarios'] ?? [];
            $ok = count(array_filter($scenarios, fn ($s) => $s['ok'] ?? false));
            // Знаменатель — сценарии задачи: упавший импорт или константы не должны давать «0 из 0»
            $expected = isset($acceptances[$task]) ? count($acceptances[$task]->scenarios()) : count($scenarios);
            $raised = [];
            foreach ($checklists[$task]['items'] ?? [] as $item) {
                if (($item['raised'] ?? false) && trim((string) ($item['quote'] ?? '')) !== '') {
                    $raised[self::itemKey((string) $item['text'])] = true;
                }
            }
            $items = isset($acceptances[$task]) ? $acceptances[$task]->checklist() : [];
            $required = array_filter($items, fn ($i) => $i['required']);
            $requiredRaised = count(array_filter($required, fn ($i) => isset($raised[self::itemKey($i['text'])])));
            $compileOk = in_array($r['compile'] ?? 'fail', ['ok', 'skip'], true);
            $importOk = in_array($r['import'] ?? 'fail', ['ok', 'skip'], true);
            $note = (string) ($r['note'] ?? '');
            $isHarness = ($r['category'] ?? '') === 'harness';
            // Данных чек-листа нет вовсе (файл не появился — шаг 5 порядка прогона забыли выполнить или
            // проверяющий не записал файл), при этом задача требует обязательные пункты: без этого
            // «нет» на выходе было бы без причины и заметки, как будто навык действительно не поднял
            // вопросы. Отличаем от битого JSON (тот уже даёт note ниже) — там данные есть, но не разобрались.
            $checklistMissing = !$isHarness && $required && !isset($checklists[$task]);
            if ($checklists[$task]['broken'] ?? false) {
                $note = $note !== '' ? "{$note}; чек-лист не разобран" : 'чек-лист не разобран';
            } elseif ($checklistMissing) {
                $note = $note !== '' ? "{$note}; чек-лист не проверен" : 'чек-лист не проверен';
            }
            $rows[$task] = [
                'task' => $task,
                'compile' => $r['compile'] ?? 'fail',
                'import' => $r['import'] ?? 'fail',
                'scenarios' => "{$ok}/{$expected}",
                'checklist' => count($raised) . '/' . count($items),
                'passed' => $compileOk && $importOk && $ok === $expected && $requiredRaised === count($required),
                'category' => (string) ($r['category'] ?? ''),
                'reason' => (string) ($r['reason'] ?? ''),
                'note' => $note,
                // как harness: сбой вне контроля навыка (тут — забытый шаг чек-листа) не входит в
                // знаменатель доли пройденных, но задача остаётся видна в таблице отчёта
                'counted' => !$isHarness && !$checklistMissing,
                'tokens' => $agents[$task]['tokens'] ?? null,
                'minutes' => isset($agents[$task]['duration_ms']) ? round($agents[$task]['duration_ms'] / 60000, 1) : null,
            ];
        }
        return new self($rows, $run);
    }

    public function rows(): array { return $this->rows; }

    public function passRate(): float
    {
        $counted = array_filter($this->rows, fn ($r) => $r['counted']);
        return $counted ? count(array_filter($counted, fn ($r) => $r['passed'])) / count($counted) : 0.0;
    }

    public function thresholdReached(): bool { return $this->passRate() >= self::THRESHOLD; }

    public function toMarkdown(): string
    {
        $mark = fn (string $v) => ['ok' => '✓', 'fail' => '✗', 'skip' => '—'][$v] ?? $v;
        $lines = ["# Оценка навыка «БП по ТЗ»: прогон {$this->run}", '',
            '| Задача | Сборка | Импорт | Сценарии | Чек-лист | Итог | Токены | Мин | Причина | Категория | Заметка |',
            '|--------|--------|--------|----------|----------|------|--------|-----|---------|-----------|---------|'];
        foreach ($this->rows as $r) {
            $lines[] = sprintf('| %s | %s | %s | %s | %s | %s | %s | %s | %s | %s | %s |', $r['task'],
                $mark($r['compile']), $mark($r['import']), $r['scenarios'], $r['checklist'],
                $r['passed'] ? '**пройдена**' : 'нет', $r['tokens'] ?? '—', $r['minutes'] ?? '—',
                $r['reason'] ?: '—', $r['category'] ?: '—', $r['note'] ?: '—');
        }
        $counted = array_filter($this->rows, fn ($r) => $r['counted']);
        $harness = count(array_filter($this->rows, fn ($r) => $r['category'] === 'harness'));
        $checklistMissing = count($this->rows) - count($counted) - $harness;
        $passed = count(array_filter($counted, fn ($r) => $r['passed']));
        $lines[] = '';
        $lines[] = sprintf('**Пройдено: %d из %d (%d%%) — порог %d%% %s.**', $passed, count($counted),
            (int) round($this->passRate() * 100), (int) (self::THRESHOLD * 100),
            $this->thresholdReached() ? 'достигнут' : 'не достигнут');
        if ($harness) {
            $lines[] = "Не учтено задач со сбоем прогонщика: {$harness} — их перегоняют после исправления.";
        }
        if ($checklistMissing) {
            $lines[] = "Не учтено задач без данных чек-листа: {$checklistMissing} — довыполнить шаг «чек-лист» (CHECKLIST_PROMPT.md) и перегнать report.";
        }
        $lines[] = 'Один прогон на задачу: порог оценён грубо.';

        // Слабые места: провалы по категориям — что чинить (spec — агент, tool — утилита, skill — навык)
        $byCategory = [];
        foreach ($this->rows as $r) {
            if (!$r['passed']) {
                $byCategory[$r['category'] ?: 'не разобрано'][] = $r['task'] . ($r['note'] ? " ({$r['note']})" : '');
            }
        }
        if ($byCategory) {
            $lines[] = '';
            $lines[] = '## Провалы по категориям';
            foreach ($byCategory as $category => $tasks) {
                $lines[] = "- {$category}: " . implode('; ', $tasks);
            }
        }
        return implode(PHP_EOL, $lines) . PHP_EOL;
    }
}
