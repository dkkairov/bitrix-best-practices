<?php
/** Помощники тестов: регистрация, проверки, сборка временной вики из массива файлов. */

declare(strict_types=1);

$GLOBALS['lint_tests'] = [];
$GLOBALS['lint_temp_dirs'] = [];

function test(string $name, callable $fn): void
{
    $GLOBALS['lint_tests'][$name] = $fn;
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

function shortJson(mixed $value): string
{
    $json = json_encode($value, JSON_UNESCAPED_UNICODE);

    return mb_strlen((string)$json) > 300 ? mb_substr((string)$json, 0, 300) . '…' : (string)$json;
}

/**
 * Собирает временный «репозиторий» из карты «путь => содержимое» и возвращает его корень.
 * @param array<string, string> $files
 */
function makeWiki(array $files): string
{
    $root = sys_get_temp_dir() . '/wiki-lint-' . bin2hex(random_bytes(6));
    foreach ($files as $rel => $content) {
        $path = $root . '/' . $rel;
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $content);
    }
    $GLOBALS['lint_temp_dirs'][] = $root;

    return $root;
}

/** Страница с заполненным ядром frontmatter; через $fm поля переопределяются. */
function page(string $title, string $type, array $fm = [], string $body = ''): string
{
    $fields = array_merge([
        'title' => '"' . $title . '"',
        'type' => $type,
        'module' => 'core-d7',
        'edition' => 'box',
        'status' => 'verified',
        'verified' => '"2026-09-01 / тест"',
        'updated' => '"2026-09-01"',
    ], $fm);

    $lines = ['---'];
    foreach ($fields as $key => $value) {
        if ($value !== null) {
            $lines[] = $key . ': ' . $value;
        }
    }
    $lines[] = '---';
    $lines[] = '';
    $lines[] = '# ' . $title;
    $lines[] = '';
    $lines[] = $body;

    return implode("\n", $lines) . "\n";
}

/** @return list<Finding> */
function runCheck(string $root, string $check, string $today = '2026-09-23'): array
{
    $checks = new Checks(Wiki::load($root), $today);

    return $checks->{$check}();
}

/** @param list<Finding> $findings */
function messages(array $findings): array
{
    return array_map(static fn (Finding $f): string => $f->where . ': ' . $f->message, $findings);
}

function cleanupTempDirs(): void
{
    foreach ($GLOBALS['lint_temp_dirs'] as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            /** @var SplFileInfo $file */
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($dir);
    }
    $GLOBALS['lint_temp_dirs'] = [];
}
