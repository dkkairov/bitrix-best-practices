<?php
/**
 * bpt.php — работа с шаблонами бизнес-процессов Bitrix24 (.bpt).
 *
 * Тонкий CLI: разбор аргументов, вызов классов из src/, вывод.
 * Описание формата: wiki/modules/bizproc/concept-bizproc-bpt-format.md
 *
 * Требуется PHP >= 8.1 с расширениями zlib, json, mbstring.
 */

declare(strict_types=1);

foreach (glob(__DIR__ . '/src/*.php') ?: [] as $file) {
    require_once $file;
}

const USAGE = <<<'TXT'
Использование:
  php bpt.php decode  <file.bpt> [-o out.bpt.json] [--charset=windows-1251]
  php bpt.php encode  <file.json> -o <out.bpt> [--charset=windows-1251] [--force]
  php bpt.php check   <file.bpt>... [--charset=windows-1251]
  php bpt.php analyze <file.bpt|file.json>... [--json] [--charset=windows-1251]
  php bpt.php compact <file.bpt|file.json> [-o out.txt] [--json] [--charset=windows-1251]
  php bpt.php catalog [Тип|алиас] [--json] [--notes]
  php bpt.php compile <spec.yaml|spec.json> -o <out.bpt> [--portal=<снимок>] [--strict] [--force] [--with-document-fields]
  php bpt.php decompile <file.bpt> [-o spec.yaml] [--portal=<снимок>] [--keep-names] [--json]
  php bpt.php render <file.bpt|spec.yaml> [-o схема.md] [--portal=<снимок>]
  php bpt.php snapshot <file.bpt> [-o out.portal.yaml] [--json]

  catalog  каталог действий: таблица целиком или подробности одного типа (варианты значений,
           поведение); --notes: таблица «смысл значений и поведение» для вики
  compile  спецификация процесса -> .bpt; при ошибках файл не пишется
           --portal: снимок портала для плейсхолдеров {{вид:Название}}
           --strict: «сырые» ID портала в спецификации считать ошибкой
           --with-document-fields: положить в файл поля документа из снимка — импорт создаст
             на портале недостающие поля; по умолчанию раздел пуст и поля портала не трогаются
  decompile .bpt -> спецификация процесса (для библиотеки примеров и сверки)
            --portal: заменить идентификаторы на плейсхолдеры; --keep-names: сохранить имена действий
  render   схема процесса (Mermaid) для ревью; спецификация рисуется как черновик
  snapshot снимок портала из экспорта: поля и стадии из DOCUMENT_FIELDS
  decode   .bpt -> JSON без потерь (по умолчанию в stdout)
  encode   JSON -> .bpt
  check    обратимость .bpt -> JSON -> .bpt (сравнение serialize байт-в-байт)
  analyze  структура, привязки к порталу, проверки ссылок; код выхода 1 при ошибках
  compact  только логика — дерево «одна строка на действие» (--json: минифицированный JSON);
           без DOCUMENT_FIELDS и пустых значений. С ПОТЕРЯМИ — для чтения, не для encode.

  --charset  кодировка строк внутри .bpt, если портал не в UTF-8 (старая коробка)
TXT;

exit(main($argv));

function main(array $argv): int
{
    [$command, $files, $opts] = parseArgs(array_slice($argv, 1));
    if ($command === null || isset($opts['help'])) {
        fwrite(STDOUT, USAGE . PHP_EOL);
        return $command === null ? 2 : 0;
    }
    try {
        return match ($command) {
            'decode'  => cmdDecode($files, $opts),
            'encode'  => cmdEncode($files, $opts),
            'check'   => cmdCheck($files, $opts),
            'analyze' => cmdAnalyze($files, $opts),
            'compact' => cmdCompact($files, $opts),
            'catalog' => cmdCatalog($files, $opts),
            'compile' => cmdCompile($files, $opts),
            'decompile' => cmdDecompile($files, $opts),
            'render' => cmdRender($files, $opts),
            'snapshot' => cmdSnapshot($files, $opts),
            default   => usageError("неизвестная команда «{$command}»"),
        };
    } catch (BptException | JsonException $e) {
        fwrite(STDERR, 'Ошибка: ' . $e->getMessage() . PHP_EOL);
        return 1;
    }
}

function parseArgs(array $args): array
{
    $command = array_shift($args);
    $files = [];
    $opts = [];
    for ($i = 0; $i < count($args); $i++) {
        $arg = $args[$i];
        if ($arg === '-o') {
            $opts['out'] = $args[++$i] ?? usageError('после -o нужен путь');
        } elseif (str_starts_with($arg, '--')) {
            [$key, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, true);
            $opts[$key] = $value;
        } else {
            $files[] = $arg;
        }
    }
    return [$command, $files, $opts];
}

function usageError(string $message): never
{
    fwrite(STDERR, "Ошибка: {$message}" . PHP_EOL . PHP_EOL . USAGE . PHP_EOL);
    exit(2);
}

function requireFiles(array $files, int $min, ?int $max = null): void
{
    if (count($files) < $min || ($max !== null && count($files) > $max)) {
        usageError('неверное число файлов');
    }
}

function charset(array $opts): ?string
{
    $charset = $opts['charset'] ?? null;
    if ($charset === null || $charset === true) {
        return null;
    }
    if (!BptFile::isKnownCharset((string) $charset)) {
        usageError("неизвестная кодировка «{$charset}»");
    }
    return (string) $charset;
}

// ---------------------------------------------------------------- команды

function cmdDecode(array $files, array $opts): int
{
    requireFiles($files, 1, 1);
    emit(BptFile::toJson(BptFile::read($files[0], charset($opts))['data']), $opts['out'] ?? null);
    return 0;
}

function cmdEncode(array $files, array $opts): int
{
    requireFiles($files, 1, 1);
    $out = $opts['out'] ?? usageError('для encode нужен -o <out.bpt>');
    BptFile::write($out, BptFile::readJsonFile($files[0]), charset($opts), isset($opts['force']));
    fwrite(STDERR, "Записано: {$out}" . PHP_EOL);
    return 0;
}

function cmdCheck(array $files, array $opts): int
{
    requireFiles($files, 1);
    $failed = 0;
    foreach ($files as $file) {
        $charset = charset($opts);
        $bpt = BptFile::read($file, $charset);
        $rebuilt = BptFile::serializeFor(BptFile::fromJson(BptFile::toJson($bpt['data'])), $charset);
        if ($rebuilt === $bpt['serialized']) {
            fwrite(STDOUT, 'OK    ' . basename($file) . PHP_EOL);
            continue;
        }
        $failed++;
        $pos = strspn($rebuilt ^ $bpt['serialized'], "\0");
        fwrite(STDOUT, sprintf("DIFF  %s: расхождение с байта %d: …%s… / …%s…\n", basename($file), $pos,
            substr($bpt['serialized'], max(0, $pos - 20), 40), substr($rebuilt, max(0, $pos - 20), 40)));
    }
    return $failed ? 1 : 0;
}

function cmdAnalyze(array $files, array $opts): int
{
    requireFiles($files, 1);
    $analyzer = new Analyzer(Catalog::load());
    $reports = [];
    foreach ($files as $file) {
        $reports[] = $analyzer->analyze($file, BptFile::readAny($file, charset($opts)));
    }
    if (isset($opts['json'])) {
        fwrite(STDOUT, json_encode($reports, BptFile::JSON_OUT) . PHP_EOL);
    } else {
        foreach ($reports as $report) {
            printReport($report);
        }
        if (count($reports) > 1) {
            printCorpus($reports);
        }
    }
    foreach ($reports as $report) {
        if ($report['errors']) {
            return 1;
        }
    }
    return 0;
}

function cmdCompact(array $files, array $opts): int
{
    requireFiles($files, 1, 1);
    $data = BptFile::readAny($files[0], charset($opts))['data'];
    $logic = [
        'VERSION'    => $data['VERSION'] ?? null,
        'PARAMETERS' => BptFile::compactTree($data['PARAMETERS'] ?? []),
        'VARIABLES'  => BptFile::compactTree($data['VARIABLES'] ?? []),
        'CONSTANTS'  => BptFile::compactTree($data['CONSTANTS'] ?? []),
    ];
    if (isset($opts['json'])) {
        $logic['TEMPLATE'] = BptFile::compactTree($data['TEMPLATE']);
        emit(BptFile::flatJson($logic), $opts['out'] ?? null);
        return 0;
    }
    $lines = ['# compact: без DOCUMENT_FIELDS и пустых значений; только для чтения, не для encode'];
    foreach ($logic as $key => $value) {
        $lines[] = $key . ': ' . BptFile::flatJson($value);
    }
    $lines[] = 'TEMPLATE:';
    outline($data['TEMPLATE'][0], 0, $lines);
    emit(implode(PHP_EOL, $lines), $opts['out'] ?? null);
    return 0;
}

function cmdCatalog(array $files, array $opts): int
{
    $catalog = Catalog::load();
    $type = isset($files[0]) ? $catalog->resolveType($files[0]) : null;
    if (isset($opts['json'])) {
        emit($catalog->toJson($type), $opts['out'] ?? null);
        return 0;
    }
    if ($type === null && isset($opts['notes'])) {
        emit($catalog->notesToMarkdown(), $opts['out'] ?? null);
        return 0;
    }
    if ($type === null) {
        emit(sprintf("Каталог действий: версия %d, проверен %s\n\n%s",
            $catalog->version(), $catalog->verified(), $catalog->toMarkdown()), $opts['out'] ?? null);
        return 0;
    }
    $entry = $catalog->entry($type);
    $lines = [
        "{$type} — {$catalog->title($type)}" . (($entry['title_guess'] ?? false) ? ' (заголовок под вопросом)' : ''),
        'Алиас: ' . ($catalog->alias($type) ?? '— (служебный узел)')
            . '; вложенность: ' . $catalog->shape($type)
            . '; в корпусе: ' . ($entry['observed'] ?? 0),
    ];
    if ($catalog->isForbidden($type)) {
        $lines[] = 'ЗАПРЕЩЕНО генерировать: ' . $catalog->forbiddenReason($type);
    }
    if ($catalog->note($type) !== null) {
        $lines[] = 'Поведение: ' . $catalog->note($type);
    }
    $lines[] = 'Свойства:';
    foreach ($catalog->props($type) as $prop => $spec) {
        $lines[] = sprintf('  %-24s %-9s %s%s', $prop, $spec['type'],
            ($spec['required'] ?? false) ? 'обязательное'
                : (array_key_exists('default', $spec) ? 'по умолчанию: ' . BptFile::flatJson($spec['default']) : '—'),
            isset($spec['values']) ? '; допустимо: ' . implode(', ', $spec['values']) : '');
        if (isset($spec['options'])) {
            $lines[] = '      варианты: ' . implode('; ', array_map(
                fn ($value, $meaning) => "{$value} — {$meaning}", array_keys($spec['options']), $spec['options']));
        }
        if (isset($spec['required_keys'])) {
            $lines[] = '      обязательные ключи: ' . implode(', ', $spec['required_keys']);
        }
        if (isset($spec['note'])) {
            $lines[] = '      ' . $spec['note'];
        }
    }
    foreach ($catalog->requiredAny($type) as $group) {
        $lines[] = 'Нужно хотя бы одно из: ' . implode(', ', $group);
    }
    $returns = $catalog->returns($type);
    $lines[] = 'Возвращает: ' . ($returns ? implode(', ', $returns)
        : (is_string($entry['returns'] ?? null) ? "поля из свойства {$entry['returns']}" : '—'));
    if (!empty($entry['returns_more'])) {
        $lines[] = '  ещё результаты (курс 57, ядро): ' . implode(', ', $entry['returns_more']);
    }
    emit(implode(PHP_EOL, $lines), $opts['out'] ?? null);
    return 0;
}

function cmdCompile(array $files, array $opts): int
{
    requireFiles($files, 1, 1);
    $out = $opts['out'] ?? usageError('для compile нужен -o <out.bpt>');
    $result = (new Compiler(Catalog::load(), portalSnapshot($opts), isset($opts['strict']),
        isset($opts['with-document-fields'])))->compile(SpecReader::read($files[0]));
    printMessages($result['warnings'], '[ВНИМАНИЕ]');
    printMessages($result['errors'], '[ОШИБКА]');
    if ($result['errors']) {
        fwrite(STDERR, 'Файл не записан, ошибок: ' . count($result['errors']) . PHP_EOL);
        return 1;
    }
    BptFile::write($out, $result['bpt'], charset($opts), isset($opts['force']));
    fwrite(STDERR, "Записано: {$out}" . PHP_EOL);
    return 0;
}

function cmdDecompile(array $files, array $opts): int
{
    requireFiles($files, 1, 1);
    $bpt = BptFile::readAny($files[0], charset($opts))['data'];
    $result = (new Decompiler(Catalog::load(), portalSnapshot($opts), isset($opts['keep-names'])))->decompile($bpt);
    printMessages(array_unique($result['warnings']), '[ВНИМАНИЕ]');
    emit(isset($opts['json']) ? BptFile::toJson($result['spec']) : SpecReader::dump($result['spec']),
        $opts['out'] ?? null);
    return 0;
}

function cmdRender(array $files, array $opts): int
{
    requireFiles($files, 1, 1);
    try {
        $bpt = BptFile::readAny($files[0], charset($opts))['data'];
    } catch (BptException) {
        // Не шаблон — пробуем как спецификацию: рисуем черновик, ошибки показываем
        $result = (new Compiler(Catalog::load(), portalSnapshot($opts)))->compile(SpecReader::read($files[0]));
        printMessages($result['errors'], '[ЧЕРНОВИК]');
        $bpt = $result['bpt'];
    }
    emit((new Mermaid(Catalog::load()))->render($bpt), $opts['out'] ?? null);
    return 0;
}

function cmdSnapshot(array $files, array $opts): int
{
    requireFiles($files, 1, 1);
    $snapshot = Snapshot::fromBpt(BptFile::readAny($files[0], charset($opts))['data'], basename($files[0]));
    $data = $snapshot->toArray();
    if (isset($opts['json'])) {
        emit(BptFile::toJson($data), $opts['out'] ?? null);
    } else {
        emit(SpecReader::dump($data), $opts['out'] ?? null);
    }
    fwrite(STDERR, 'Поля и стадии заполнены из DOCUMENT_FIELDS; пользователей, группы,'
        . ' смарт-процессы и шаблоны впишите вручную.' . PHP_EOL);
    return 0;
}

function portalSnapshot(array $opts): ?Snapshot
{
    $portal = $opts['portal'] ?? null;
    if ($portal === null || $portal === true) {
        return null;
    }
    return Snapshot::load((string) $portal);
}

// ---------------------------------------------------------------- вывод

function printMessages(array $messages, string $prefix): void
{
    foreach ($messages as $message) {
        fwrite(STDERR, "{$prefix} {$message}" . PHP_EOL);
    }
}

function emit(string $content, ?string $out): void
{
    if ($out === null) {
        fwrite(STDOUT, $content . PHP_EOL);
        return;
    }
    BptFile::writeFileContents($out, $content . PHP_EOL);
    fwrite(STDERR, "Записано: {$out}" . PHP_EOL);
}

/** Строка дерева: «Тип Имя «Заголовок» [отключено] {свойства}». */
function outline(array $node, int $depth, array &$lines): void
{
    $props = BptFile::compactTree(is_array($node['Properties'] ?? null) ? $node['Properties'] : []);
    $title = (string) ($props['Title'] ?? '');
    unset($props['Title']);
    $line = str_repeat('  ', $depth) . ($node['Type'] ?? '?') . ' ' . ($node['Name'] ?? '')
        . ($title !== '' ? " «{$title}»" : '')
        . (($node['Activated'] ?? 'Y') === 'N' ? ' [отключено]' : '');
    if ($props) {
        $line .= ' ' . BptFile::flatJson($props);
    }
    $lines[] = $line;
    foreach ((array) ($node['Children'] ?? []) as $child) {
        if (is_array($child)) {
            outline($child, $depth + 1, $lines);
        }
    }
}

function printReport(array $r): void
{
    $out = [];
    $out[] = "== {$r['file']} ==";
    $out[] = "Корень: {$r['root_type']} «{$r['root_title']}»; VERSION " . var_export($r['version'], true);
    if ($r['serialized_bytes'] !== null) {
        $out[] = sprintf('Размер: %s Б .bpt, %s Б serialize; логика %s Б; DOCUMENT_FIELDS: %d полей, %d%% объёма',
            number_format((int) $r['compressed_bytes'], 0, '', ' '), number_format($r['serialized_bytes'], 0, '', ' '),
            number_format($r['logic_bytes'], 0, '', ' '), $r['document_fields'], $r['document_fields_share']);
    }
    $out[] = sprintf('Узлы: %d, глубина %d, отключено %d; параметры %d, переменные %d, константы %d',
        $r['nodes'], $r['max_depth'], $r['deactivated'], $r['parameters'], $r['variables'], $r['constants']);
    $out[] = 'Действия: ' . formatCounts($r['types']);
    if ($r['conditions']) {
        $out[] = 'Условия: ' . formatCounts($r['conditions']);
    }
    $out[] = 'Привязки к порталу:' . ($r['portal_bindings'] ? '' : ' не найдены');
    foreach ($r['portal_bindings'] as $key => $values) {
        $shown = array_slice($values, 0, 8);
        $more = count($values) > 8 ? ' … ещё ' . (count($values) - 8) : '';
        $out[] = sprintf('  %-13s %3d: %s%s', $key, count($values), implode(', ', $shown), $more);
    }
    foreach ($r['errors'] as $error) {
        $out[] = "[ОШИБКА] {$error}";
    }
    foreach ($r['warnings'] as $warning) {
        $out[] = "[ВНИМАНИЕ] {$warning}";
    }
    if (!$r['errors'] && !$r['warnings']) {
        $out[] = 'Проверки: замечаний нет';
    }
    fwrite(STDOUT, implode(PHP_EOL, $out) . PHP_EOL . PHP_EOL);
}

function printCorpus(array $reports): void
{
    $types = [];
    $nodes = 0;
    foreach ($reports as $r) {
        $nodes += $r['nodes'];
        foreach ($r['types'] as $type => $count) {
            $types[$type] = ($types[$type] ?? 0) + $count;
        }
    }
    arsort($types);
    $withErrors = count(array_filter($reports, fn ($r) => $r['errors']));
    $bound = count(array_filter($reports, fn ($r) => $r['portal_bindings']));
    fwrite(STDOUT, sprintf("== Итого: файлов %d, узлов %d, типов действий %d; с ошибками %d; с привязками к порталу %d ==\n%s\n",
        count($reports), $nodes, count($types), $withErrors, $bound, formatCounts($types)));
}

function formatCounts(array $counts): string
{
    $parts = [];
    foreach ($counts as $key => $count) {
        $parts[] = "{$key}×{$count}";
    }
    return implode(', ', $parts);
}
