<?php
/**
 * Тесты утилиты оценки: php tools/bpt-eval/tests/run.php [--filter=<часть имени>]
 * Помощники и классы tools/bpt переиспользуются. Код выхода: 0 — всё прошло, 1 — есть падения.
 */

declare(strict_types=1);

$bpt = dirname(__DIR__, 2) . '/bpt';
require_once $bpt . '/tests/support.php';
foreach (array_merge(glob($bpt . '/src/*.php') ?: [], glob(dirname(__DIR__) . '/src/*.php') ?: []) as $file) {
    require_once $file;
}
foreach (glob(__DIR__ . '/*_test.php') ?: [] as $file) {
    require_once $file;
}

$filter = null;
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--filter=(.+)$/u', $arg, $m)) {
        $filter = mb_strtolower($m[1]);
    }
}
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
fwrite(STDOUT, sprintf("\nПройдено: %d, упало: %d%s\n", $passed, $failed, $skipped ? ", пропущено: {$skipped}" : ''));
exit($failed ? 1 : 0);
