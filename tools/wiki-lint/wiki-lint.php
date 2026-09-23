<?php
/**
 * wiki-lint — механические проверки вики (CLAUDE.md §4–§6, §9).
 *
 * Запуск из корня репозитория:
 *   php tools/wiki-lint/wiki-lint.php [--root=<путь>] [--json] [--only=<проверка>] [--strict] [--today=ГГГГ-ММ-ДД]
 *
 * Код выхода: 0 — ошибок схемы нет, 1 — есть (со --strict считаются и предупреждения).
 */

declare(strict_types=1);

foreach (glob(__DIR__ . '/src/*.php') ?: [] as $file) {
    require_once $file;
}

$opts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/u', $arg, $m)) {
        $opts[$m[1]] = $m[2] ?? true;
    } else {
        fwrite(STDERR, "Непонятный аргумент: {$arg}" . PHP_EOL);
        exit(2);
    }
}

if (isset($opts['help'])) {
    fwrite(STDOUT, <<<TXT
    wiki-lint — механические проверки вики.

      --root=<путь>      корень репозитория (по умолчанию — текущий каталог)
      --only=<проверка>  только одна: links, orphans, frontmatter, verified, naming,
                         structure, hubs, aliases, navigator, dates, secrets, coverage
      --json             вывод в JSON
      --strict           предупреждения тоже дают код выхода 1
      --today=ГГГГ-ММ-ДД дата для проверки «протухания» (по умолчанию сегодня)

    Что не проверяется машиной: противоречия по смыслу, устаревшие практики,
    несоответствие edition тексту, пробелы покрытия — это разбор глазами
    (команда /wiki:lint).

    TXT);
    exit(0);
}

$root = is_string($opts['root'] ?? null) ? $opts['root'] : getcwd();
$root = rtrim(str_replace('\\', '/', (string)$root), '/');
if (!is_dir($root . '/wiki')) {
    fwrite(STDERR, "В «{$root}» нет папки wiki — запускайте из корня репозитория или задайте --root." . PHP_EOL);
    exit(2);
}

$today = is_string($opts['today'] ?? null) ? $opts['today'] : date('Y-m-d');
$wiki = Wiki::load($root);
$checks = new Checks($wiki, $today);

$only = is_string($opts['only'] ?? null) ? $opts['only'] : null;
if ($only !== null) {
    if (!method_exists($checks, $only) || in_array($only, ['all'], true)) {
        fwrite(STDERR, "Нет такой проверки: {$only}" . PHP_EOL);
        exit(2);
    }
    $findings = $checks->{$only}();
} else {
    $findings = $checks->all();
}

$report = new Report($findings, $wiki);
fwrite(STDOUT, isset($opts['json']) ? $report->json() : $report->text());

$hasWarn = false;
foreach ($findings as $f) {
    $hasWarn = $hasWarn || $f->level === 'warn';
}

exit($report->hasErrors() || (isset($opts['strict']) && $hasWarn) ? 1 : 0);
