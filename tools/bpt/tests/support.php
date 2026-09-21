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

/**
 * Приводит дерево к сравнимому виду: значения по умолчанию проставлены, служебный Node убран,
 * имена действий заменены на позиции (и в ссылках тоже), ключи отсортированы.
 */
function canonicalTree(array $bpt, Catalog $catalog): array
{
    $positions = [];
    $index = function (array $node, string $pos) use (&$index, &$positions): void {
        $positions[(string) ($node['Name'] ?? '')] = $pos;
        foreach (array_values(array_filter($node['Children'] ?? [], 'is_array')) as $i => $child) {
            $index($child, "{$pos}.{$i}");
        }
    };
    $index($bpt['TEMPLATE'][0], '0');

    $canon = function (array $node, string $pos) use (&$canon, $catalog, $positions): array {
        $type = (string) $node['Type'];
        $props = is_array($node['Properties'] ?? null) ? $node['Properties'] : [];
        if ($catalog->has($type)) {
            $props += $catalog->defaults($type);
        }
        $props = canonicalValue(replaceNamesWithPositions($props, $positions));
        ksort($props);
        $children = [];
        foreach (array_values(array_filter($node['Children'] ?? [], 'is_array')) as $i => $child) {
            $children[] = $canon($child, "{$pos}.{$i}");
        }
        return [
            'Type'       => $type,
            'Name'       => $pos,
            'Activated'  => (string) ($node['Activated'] ?? 'Y'),
            'Properties' => $props,
            'Children'   => $children,
        ];
    };
    return $canon($bpt['TEMPLATE'][0], '0');
}

/** Параметры, переменные и константы в сравнимом виде. */
function canonicalDefinitions(array $bpt): array
{
    $out = [];
    foreach (['PARAMETERS', 'VARIABLES', 'CONSTANTS'] as $section) {
        foreach ($bpt[$section] ?? [] as $code => $definition) {
            $definition = is_array($definition) ? Catalog::normalizeDefinition($definition) : $definition;
            if (is_array($definition)) {
                ksort($definition);
            }
            $out[$section][(string) $code] = $definition;
        }
    }
    return $out;
}

function replaceNamesWithPositions(mixed $value, array $positions): mixed
{
    if (is_string($value)) {
        return preg_replace_callback('/\{=(A\d+_\d+_\d+_\d+):/',
            fn (array $m) => isset($positions[$m[1]]) ? "{=#{$positions[$m[1]]}:" : $m[0], $value);
    }
    if (!is_array($value)) {
        return $value;
    }
    $out = [];
    foreach ($value as $key => $item) {
        $out[is_string($key) ? replaceNamesWithPositions($key, $positions) : $key]
            = replaceNamesWithPositions($item, $positions);
    }
    return $out;
}

/** Рекурсивная сортировка ключей отображений; порядок списков сохраняется. */
function canonicalValue(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }
    $isList = array_is_list($value);
    $out = [];
    foreach ($value as $key => $item) {
        $out[$key] = canonicalValue($item);
    }
    if (!$isList) {
        ksort($out);
    }
    return $out;
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
