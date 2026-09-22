<?php
/**
 * Сводка прогона: по задаче — сборка, импорт, сценарии, чек-лист, итог, расход агента, причина и
 * категория провала; по прогону — доля пройденных против порога. Сбои прогонщика (harness) в долю
 * не входят: их чинят и задачу перегоняют.
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
                $checklists[$task] = self::readJson($checklistFile);
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

    /** Разбор JSON-файла прогона (result.json/checklist.json/agents.json): битый файл — EvalException с путём, а не голый JsonException. */
    private static function readJson(string $file): array
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
                    $raised[mb_strtolower(trim((string) $item['text']))] = true;
                }
            }
            $items = isset($acceptances[$task]) ? $acceptances[$task]->checklist() : [];
            $required = array_filter($items, fn ($i) => $i['required']);
            $requiredRaised = count(array_filter($required, fn ($i) => isset($raised[mb_strtolower($i['text'])])));
            $compileOk = in_array($r['compile'] ?? 'fail', ['ok', 'skip'], true);
            $importOk = in_array($r['import'] ?? 'fail', ['ok', 'skip'], true);
            $rows[$task] = [
                'task' => $task,
                'compile' => $r['compile'] ?? 'fail',
                'import' => $r['import'] ?? 'fail',
                'scenarios' => "{$ok}/{$expected}",
                'checklist' => count($raised) . '/' . count($items),
                'passed' => $compileOk && $importOk && $ok === $expected && $requiredRaised === count($required),
                'category' => (string) ($r['category'] ?? ''),
                'reason' => (string) ($r['reason'] ?? ''),
                'note' => (string) ($r['note'] ?? ''),
                'tokens' => $agents[$task]['tokens'] ?? null,
                'minutes' => isset($agents[$task]['duration_ms']) ? round($agents[$task]['duration_ms'] / 60000, 1) : null,
            ];
        }
        return new self($rows, $run);
    }

    public function rows(): array { return $this->rows; }

    public function passRate(): float
    {
        $counted = array_filter($this->rows, fn ($r) => $r['category'] !== 'harness');
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
        $counted = array_filter($this->rows, fn ($r) => $r['category'] !== 'harness');
        $harness = count($this->rows) - count($counted);
        $passed = count(array_filter($counted, fn ($r) => $r['passed']));
        $lines[] = '';
        $lines[] = sprintf('**Пройдено: %d из %d (%d%%) — порог %d%% %s.**', $passed, count($counted),
            (int) round($this->passRate() * 100), (int) (self::THRESHOLD * 100),
            $this->thresholdReached() ? 'достигнут' : 'не достигнут');
        if ($harness) {
            $lines[] = "Не учтено задач со сбоем прогонщика: {$harness} — их перегоняют после исправления.";
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
