<?php
/**
 * Запуск тестов: php tools/wiki-lint/tests/run.php [--filter=<часть имени>]
 * Код выхода: 0 — всё прошло, 1 — есть падения.
 */

declare(strict_types=1);

require_once __DIR__ . '/support.php';
foreach (glob(dirname(__DIR__) . '/src/*.php') ?: [] as $file) {
    require_once $file;
}
foreach (glob(__DIR__ . '/*_test.php') ?: [] as $file) {
    require_once $file;
}

$opts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/u', $arg, $m)) {
        $opts[$m[1]] = $m[2] ?? true;
    }
}
$filter = is_string($opts['filter'] ?? null) ? mb_strtolower($opts['filter']) : null;

$passed = $failed = $skipped = 0;
foreach ($GLOBALS['lint_tests'] as $name => $fn) {
    if ($filter !== null && !str_contains(mb_strtolower($name), $filter)) {
        $skipped++;
        continue;
    }
    try {
        $fn();
        $passed++;
        fwrite(STDOUT, "OK    {$name}" . PHP_EOL);
    } catch (Throwable $e) {
        $failed++;
        fwrite(STDOUT, "FAIL  {$name}" . PHP_EOL . '      ' . $e->getMessage() . PHP_EOL);
    }
}
cleanupTempDirs();

fwrite(STDOUT, sprintf(
    "\nПройдено: %d, упало: %d%s\n",
    $passed,
    $failed,
    $skipped ? ", пропущено: {$skipped}" : ''
));
exit($failed ? 1 : 0);
