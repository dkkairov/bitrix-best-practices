<?php
/**
 * Запуск тестов утилиты: php tools/bpt/tests/run.php [--filter=<часть имени>] [--corpus=<папка с .bpt>]
 * Код выхода: 0 — всё прошло, 1 — есть падения.
 */

declare(strict_types=1);

require_once __DIR__ . '/support.php';
foreach (glob(dirname(__DIR__) . '/src/*.php') ?: [] as $file) {
    require_once $file;
}

$opts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/u', $arg, $m)) {
        $opts[$m[1]] = $m[2] ?? true;
    }
}
setCorpusDir(is_string($opts['corpus'] ?? null) ? $opts['corpus'] : null);

foreach (glob(__DIR__ . '/*_test.php') ?: [] as $file) {
    require_once $file;
}

$filter = is_string($opts['filter'] ?? null) ? mb_strtolower($opts['filter']) : null;
$passed = $failed = $skipped = 0;
foreach ($GLOBALS['bpt_tests'] as $name => $fn) {
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
cleanupTempFiles();

fwrite(STDOUT, sprintf("\nПройдено: %d, упало: %d%s%s\n", $passed, $failed,
    $skipped ? ", пропущено: {$skipped}" : '',
    corpusFiles() ? ', корпус: ' . count(corpusFiles()) . ' файлов' : ', корпус не задан'));
exit($failed ? 1 : 0);
