<?php
/**
 * Помощники тестов: регистрация, проверки, временные файлы, доступ к корпусу.
 * Корпус (реальные .bpt) в репозитории не хранится — путь передаётся ключом --corpus.
 */

declare(strict_types=1);

$GLOBALS['bpt_tests'] = [];
$GLOBALS['bpt_temp_files'] = [];
$GLOBALS['bpt_corpus_dir'] = null;

function test(string $name, callable $fn): void
{
    $GLOBALS['bpt_tests'][$name] = $fn;
}

function assertSame(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(trim($msg . ' ожидалось: ' . shortJson($expected)
            . ', получено: ' . shortJson($actual)));
    }
}

function assertTrue(bool $condition, string $msg): void
{
    if (!$condition) {
        throw new RuntimeException($msg);
    }
}

function assertThrows(callable $fn, string $needle, string $msg = ''): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        if (!str_contains($e->getMessage(), $needle)) {
            throw new RuntimeException("{$msg}: в ошибке нет «{$needle}»: {$e->getMessage()}");
        }
        return;
    }
    throw new RuntimeException("{$msg}: ошибка не возникла");
}

function shortJson(mixed $value): string
{
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return mb_strlen((string) $json) > 400 ? mb_substr((string) $json, 0, 400) . '…' : (string) $json;
}

function tmpPath(string $suffix): string
{
    $path = sys_get_temp_dir() . '/bpt_test_' . bin2hex(random_bytes(4)) . $suffix;
    $GLOBALS['bpt_temp_files'][] = $path;
    return $path;
}

function cleanupTempFiles(): void
{
    foreach ($GLOBALS['bpt_temp_files'] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    $GLOBALS['bpt_temp_files'] = [];
}

/** Собирает .bpt из фикстуры во временный файл и возвращает путь. */
function fixtureBpt(): string
{
    $path = tmpPath('.bpt');
    file_put_contents($path, gzcompress(serialize(fixtureData()), 9));
    return $path;
}

function fixtureData(): array
{
    return require __DIR__ . '/fixtures/sample_template.php';
}

/** Минимальная спецификация — общий помощник для тестов сборщика, разбора и схемы. */
function minimalSpec(?array $steps = null): array
{
    return [
        'bizproc' => 1,
        'name'    => 'Тест',
        'kind'    => 'robots',
        'steps'   => $steps ?? [['change_stage' => ['TargetStatus' => 'DT1000_10:CLIENT']]],
    ];
}

/** Плоский список всех действий дерева. */
function allActivities(array $node, array $collected = []): array
{
    $collected[] = $node;
    foreach ($node['Children'] ?? [] as $child) {
        if (is_array($child)) {
            $collected = allActivities($child, $collected);
        }
    }
    return $collected;
}

function setCorpusDir(?string $dir): void
{
    $GLOBALS['bpt_corpus_dir'] = $dir;
}

/** Файлы корпуса; пустой список — тесты корпуса тихо пропускаются. */
function corpusFiles(): array
{
    $dir = $GLOBALS['bpt_corpus_dir'];
    if ($dir === null) {
        return [];
    }
    if (!is_dir($dir)) {
        throw new RuntimeException("папка корпуса не найдена: {$dir}");
    }
    $files = glob(rtrim($dir, '/\\') . '/*.bpt') ?: [];
    sort($files);
    return $files;
}
