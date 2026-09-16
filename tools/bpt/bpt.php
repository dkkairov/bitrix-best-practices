<?php
/**
 * bpt.php — разбор, сборка и анализ шаблонов бизнес-процессов Bitrix24 (.bpt).
 *
 * Формат .bpt: gzcompress(serialize(array)) с ключами VERSION, TEMPLATE, PARAMETERS,
 * VARIABLES, CONSTANTS, DOCUMENT_FIELDS. Тип документа в файле не хранится.
 * Описание формата: wiki/modules/bizproc/concept-bizproc-bpt-format.md
 *
 * Требуется PHP >= 8.1 с расширениями zlib, json, mbstring.
 * Разбор — только unserialize(..., ['allowed_classes' => false]): объекты не создаются.
 */

declare(strict_types=1);

const USAGE = <<<'TXT'
Использование:
  php bpt.php decode  <file.bpt> [-o out.bpt.json] [--charset=windows-1251]
  php bpt.php encode  <file.json> -o <out.bpt> [--charset=windows-1251] [--force]
  php bpt.php check   <file.bpt>... [--charset=windows-1251]
  php bpt.php analyze <file.bpt|file.json>... [--json] [--charset=windows-1251]
  php bpt.php compact <file.bpt|file.json> [-o out.txt] [--json] [--charset=windows-1251]

  decode   .bpt -> JSON без потерь (по умолчанию в stdout)
  encode   JSON -> .bpt
  check    обратимость .bpt -> JSON -> .bpt (сравнение serialize байт-в-байт)
  analyze  структура, привязки к порталу, проверки ссылок; код выхода 1 при ошибках
  compact  только логика — дерево «одна строка на действие» (--json: минифицированный JSON);
           без DOCUMENT_FIELDS и пустых значений. С ПОТЕРЯМИ — для чтения, не для encode.

  --charset  кодировка строк внутри .bpt, если портал не в UTF-8 (старая коробка)
TXT;

const AUTOMATION_TITLE = 'Bizproc Automation template';

/** Действия, которые нельзя генерировать/переносить без отдельного решения. */
const FORBIDDEN_TYPES = [
    'CodeActivity' => 'выполнение PHP-кода (только коробка)',
];

/** Действия-ожидания: без таймаута процесс может «висеть» бесконечно. */
const WAITING_TYPES = [
    'ApproveActivity', 'ReviewActivity', 'RequestInformationActivity',
    'RequestInformationOptionalActivity',
];

/** Привязки к конкретному порталу, которые ищем в логике шаблона. */
const PORTAL_PATTERNS = [
    'users'        => '/\buser_\d+\b/',
    'groups'       => '/\bgroup_[a-z]+\d+\b/',
    'uf_fields'    => '/\bUF_[A-Z0-9_]+\b/',
    'smart_types'  => '/\bDYNAMIC_\d+\b/',
    'parent_links' => '/\bPARENT_ID_\d+\b/',
    'stages'       => '/\b(?:DT\d+_\d+|C\d+):[A-Z0-9_]+\b/',
    'iblocks'      => '/\biblock_\d+\b/',
    'globals'      => '/\{=Global[A-Za-z]*:[^}]+\}/',
    'urls'         => '#https?://[^\s"\'<>{}]+#',
    'emails'       => '/[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}/',
];

/** Системные UF-поля, одинаковые на всех порталах, — не считаем привязкой. */
const SYSTEM_UF = ['UF_CRM_TASK', 'UF_TASK_WEBDAV_FILES', 'UF_DEPARTMENT', 'UF_PHONE_INNER'];

/** Свойства, где «голое» число почти наверняка ID пользователя. */
const USER_PROP_RE = '/user|responsible|auditor|accomplice|approver|voter|created_by|modified_by/i';

const JSON_FLAT = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
    | JSON_THROW_ON_ERROR;
const JSON_OUT = JSON_FLAT | JSON_PRETTY_PRINT;

final class BptException extends RuntimeException
{
}

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
        $a = $args[$i];
        if ($a === '-o') {
            $opts['out'] = $args[++$i] ?? usageError('после -o нужен путь');
        } elseif (str_starts_with($a, '--')) {
            [$k, $v] = array_pad(explode('=', substr($a, 2), 2), 2, true);
            $opts[$k] = $v;
        } else {
            $files[] = $a;
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

// ---------------------------------------------------------------- команды

function cmdDecode(array $files, array $opts): int
{
    requireFiles($files, 1, 1);
    $bpt = readBpt($files[0], charset($opts));
    emit(toJson($bpt['data']), $opts['out'] ?? null);
    return 0;
}

function cmdEncode(array $files, array $opts): int
{
    requireFiles($files, 1, 1);
    $out = $opts['out'] ?? usageError('для encode нужен -o <out.bpt>');
    if (file_exists($out) && !isset($opts['force'])) {
        throw new BptException("файл {$out} уже существует (добавьте --force)");
    }
    $data = fromJson(readInput($files[0]));
    assertTemplate($data, $files[0]);
    $charset = charset($opts);
    $serialized = serialize($charset ? convertTree($data, 'UTF-8', $charset) : $data);
    writeOutput($out, gzcompress($serialized, 9));
    fwrite(STDERR, "Записано: {$out}" . PHP_EOL);
    return 0;
}

function cmdCheck(array $files, array $opts): int
{
    requireFiles($files, 1);
    $failed = 0;
    foreach ($files as $file) {
        $charset = charset($opts);
        $bpt = readBpt($file, $charset);
        $back = fromJson(toJson($bpt['data']));
        if ($charset) {
            $back = convertTree($back, 'UTF-8', $charset);
        }
        $serialized = serialize($back);
        if ($serialized === $bpt['serialized']) {
            fwrite(STDOUT, 'OK    ' . basename($file) . PHP_EOL);
            continue;
        }
        $failed++;
        $pos = strspn($serialized ^ $bpt['serialized'], "\0");
        fwrite(STDOUT, sprintf("DIFF  %s: расхождение с байта %d: …%s… / …%s…\n", basename($file), $pos,
            substr($bpt['serialized'], max(0, $pos - 20), 40), substr($serialized, max(0, $pos - 20), 40)));
    }
    return $failed ? 1 : 0;
}

function cmdAnalyze(array $files, array $opts): int
{
    requireFiles($files, 1);
    $reports = [];
    foreach ($files as $file) {
        $reports[] = analyze($file, readAny($file, charset($opts)));
    }
    if (isset($opts['json'])) {
        fwrite(STDOUT, json_encode($reports, JSON_OUT) . PHP_EOL);
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
    $data = readAny($files[0], charset($opts))['data'];
    $logic = [
        'VERSION'    => $data['VERSION'] ?? null,
        'PARAMETERS' => compactTree($data['PARAMETERS'] ?? []),
        'VARIABLES'  => compactTree($data['VARIABLES'] ?? []),
        'CONSTANTS'  => compactTree($data['CONSTANTS'] ?? []),
    ];
    if (isset($opts['json'])) {
        $logic['TEMPLATE'] = compactTree($data['TEMPLATE']);
        emit(json_encode($logic, JSON_FLAT), $opts['out'] ?? null);
        return 0;
    }
    $lines = ['# compact: без DOCUMENT_FIELDS и пустых значений; только для чтения, не для encode'];
    foreach ($logic as $key => $value) {
        $lines[] = $key . ': ' . json_encode($value, JSON_FLAT);
    }
    $lines[] = 'TEMPLATE:';
    outline($data['TEMPLATE'][0], 0, $lines);
    emit(implode(PHP_EOL, $lines), $opts['out'] ?? null);
    return 0;
}

/** Строка дерева: «Тип Имя «Заголовок» [отключено] {свойства}». */
function outline(array $node, int $depth, array &$lines): void
{
    $props = compactTree(is_array($node['Properties'] ?? null) ? $node['Properties'] : []);
    $title = (string) ($props['Title'] ?? '');
    unset($props['Title']);
    $line = str_repeat('  ', $depth) . ($node['Type'] ?? '?') . ' ' . ($node['Name'] ?? '')
        . ($title !== '' ? " «{$title}»" : '')
        . (($node['Activated'] ?? 'Y') === 'N' ? ' [отключено]' : '');
    if ($props) {
        $line .= ' ' . json_encode($props, JSON_FLAT);
    }
    $lines[] = $line;
    foreach ((array) ($node['Children'] ?? []) as $child) {
        if (is_array($child)) {
            outline($child, $depth + 1, $lines);
        }
    }
}

// ---------------------------------------------------------------- чтение/запись

/** @return array{data: array, serialized: string, compressed: int} */
function readBpt(string $file, ?string $charset): array
{
    $raw = readInput($file);
    $serialized = decompress($raw);
    if ($serialized === null) {
        throw new BptException("{$file}: не удалось распаковать (ожидается zlib/gzcompress)");
    }
    $data = @unserialize($serialized, ['allowed_classes' => false]);
    if (!is_array($data)) {
        throw new BptException("{$file}: содержимое не является сериализованным массивом PHP");
    }
    assertNoObjects($data, $file);
    assertTemplate($data, $file);
    if ($charset) {
        $data = convertTree($data, $charset, 'UTF-8');
    }
    return ['data' => $data, 'serialized' => $serialized, 'compressed' => strlen($raw)];
}

/** .bpt или ранее выгруженный JSON (определяется по содержимому). */
function readAny(string $file, ?string $charset): array
{
    $content = readInput($file);
    if (str_starts_with(ltrim($content), '{')) {
        $data = fromJson($content);
        assertTemplate($data, $file);
        return ['data' => $data, 'serialized' => null, 'compressed' => null];
    }
    return readBpt($file, $charset);
}

function decompress(string $raw): ?string
{
    foreach (['gzuncompress', 'gzinflate', 'zlib_decode'] as $fn) {
        $out = @$fn($raw);
        if (is_string($out)) {
            return $out;
        }
    }
    // На случай экспорта без сжатия (сервер без zlib)
    return str_starts_with($raw, 'a:') ? $raw : null;
}

function readInput(string $file): string
{
    if (!is_file($file) || !is_readable($file)) {
        throw new BptException("файл не найден или недоступен: {$file}");
    }
    return (string) file_get_contents($file);
}

function writeOutput(string $file, string $content): void
{
    if (file_put_contents($file, $content) === false) {
        throw new BptException("не удалось записать {$file}");
    }
}

function emit(string $content, ?string $out): void
{
    if ($out === null) {
        fwrite(STDOUT, $content . PHP_EOL);
        return;
    }
    writeOutput($out, $content . PHP_EOL);
    fwrite(STDERR, "Записано: {$out}" . PHP_EOL);
}

function toJson(array $data): string
{
    try {
        return json_encode($data, JSON_OUT);
    } catch (JsonException $e) {
        throw new BptException('не удалось перевести в JSON (' . $e->getMessage()
            . '). Если портал не в UTF-8, укажите --charset=windows-1251');
    }
}

function fromJson(string $json): array
{
    $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new BptException('JSON не содержит объект шаблона');
    }
    return $data;
}

function charset(array $opts): ?string
{
    $charset = $opts['charset'] ?? null;
    if ($charset === null || $charset === true) {
        return null;
    }
    if (!in_array(strtolower($charset), array_map('strtolower', mb_list_encodings()), true)) {
        usageError("неизвестная кодировка «{$charset}»");
    }
    return $charset;
}

function convertTree(mixed $value, string $from, string $to): mixed
{
    if (is_string($value)) {
        return mb_convert_encoding($value, $to, $from);
    }
    if (!is_array($value)) {
        return $value;
    }
    $out = [];
    foreach ($value as $k => $v) {
        $key = is_string($k) ? mb_convert_encoding($k, $to, $from) : $k;
        $out[$key] = convertTree($v, $from, $to);
    }
    return $out;
}

function assertNoObjects(mixed $value, string $file): void
{
    if (is_object($value)) {
        throw new BptException("{$file}: внутри есть сериализованный объект — такой файл не обрабатываем");
    }
    if (is_array($value)) {
        foreach ($value as $v) {
            assertNoObjects($v, $file);
        }
    }
}

function assertTemplate(array $data, string $file): void
{
    $root = $data['TEMPLATE'][0] ?? null;
    if (!is_array($root) || !isset($root['Type'])) {
        throw new BptException("{$file}: нет корневого действия TEMPLATE[0] — это не шаблон БП");
    }
}

// ---------------------------------------------------------------- анализ

function analyze(string $file, array $bpt): array
{
    $data = $bpt['data'];
    $root = $data['TEMPLATE'][0];
    $logic = [
        'TEMPLATE'   => $data['TEMPLATE'],
        'PARAMETERS' => $data['PARAMETERS'] ?? [],
        'VARIABLES'  => $data['VARIABLES'] ?? [],
        'CONSTANTS'  => $data['CONSTANTS'] ?? [],
    ];
    $logicJson = json_encode($logic, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $docFields = $data['DOCUMENT_FIELDS'] ?? [];
    $docFieldsBytes = strlen(json_encode($docFields, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

    $stats = ['types' => [], 'nodes' => 0, 'depth' => 0, 'deactivated' => 0, 'conditions' => [],
        'names' => [], 'waiting_no_timeout' => [], 'while' => 0, 'forbidden' => [],
        'raw_user_ids' => [], 'set_vars' => [], 'requested' => [], 'doc_fields_used' => [],
        'propvar_used' => []];
    walk($root, 0, $stats);

    // Привязки к порталу — только по логике, не по метаданным DOCUMENT_FIELDS
    $portal = [];
    foreach (PORTAL_PATTERNS as $key => $re) {
        preg_match_all($re, $logicJson, $m);
        $found = array_values(array_unique($m[0]));
        if ($key === 'uf_fields') {
            $found = array_values(array_diff($found, SYSTEM_UF));
        }
        if ($found) {
            sort($found);
            $portal[$key] = $found;
        }
    }
    if ($stats['raw_user_ids']) {
        $portal['raw_user_ids'] = array_values(array_unique($stats['raw_user_ids']));
    }

    // Проверки ссылок
    $errors = [];
    $warnings = [];
    $dupes = array_keys(array_filter(array_count_values($stats['names']), fn ($n) => $n > 1));
    if ($dupes) {
        $errors[] = 'повторяющиеся имена действий: ' . implode(', ', $dupes);
    }
    preg_match_all('/\{=(A\d+_\d+_\d+_\d+):/', $logicJson, $m);
    $dangling = array_values(array_diff(array_unique($m[1]), $stats['names']));
    if ($dangling) {
        $errors[] = 'ссылки на несуществующие действия: ' . implode(', ', $dangling);
    }
    $declared = [
        'Variable' => array_merge(array_keys($data['VARIABLES'] ?? []), $stats['requested']),
        'Constant' => array_keys($data['CONSTANTS'] ?? []),
        'Template' => array_keys($data['PARAMETERS'] ?? []),
    ];
    foreach ($declared as $kind => $names) {
        preg_match_all('/\{=' . $kind . ':([A-Za-z0-9_]+)/', $logicJson, $m);
        $used = array_map(fn ($n) => preg_replace('/_printable$/i', '', $n), $m[1]);
        if ($kind === 'Variable') {
            $used = array_merge($used, $stats['set_vars']);
        }
        $missing = array_values(array_diff(array_unique($used), $names));
        if ($missing) {
            $errors[] = "необъявленные {$kind}: " . implode(', ', $missing);
        }
    }
    // propertyvariablecondition проверяет параметры, переменные и константы шаблона
    $missing = array_values(array_diff(array_unique($stats['propvar_used']), array_merge(...array_values($declared))));
    if ($missing) {
        $errors[] = 'условия по необъявленным параметрам/переменным: ' . implode(', ', $missing);
    }
    foreach ($stats['forbidden'] as $type) {
        $errors[] = "запрещённое действие {$type}: " . FORBIDDEN_TYPES[$type];
    }
    if ($docFields) {
        preg_match_all('/\{=Document:([A-Za-z0-9_.]+)/', $logicJson, $m);
        $used = array_merge(
            array_map(fn ($n) => preg_replace('/_printable$/i', '', $n), $m[1]),
            $stats['doc_fields_used']
        );
        $unknown = array_values(array_diff(array_unique($used), array_keys($docFields)));
        if ($unknown) {
            $warnings[] = 'поля документа, которых нет в DOCUMENT_FIELDS (удалены или переименованы?): '
                . implode(', ', $unknown);
        }
    }
    foreach ($stats['waiting_no_timeout'] as $w) {
        $warnings[] = "ожидание без таймаута: {$w}";
    }
    if ($stats['while']) {
        $warnings[] = "циклы WhileActivity: {$stats['while']} — проверьте условие выхода";
    }

    arsort($stats['types']);
    arsort($stats['conditions']);
    $total = strlen($logicJson) + $docFieldsBytes;
    return [
        'file'             => basename($file),
        'kind'             => ($root['Properties']['Title'] ?? '') === AUTOMATION_TITLE ? 'robots' : 'designer',
        'root_type'        => $root['Type'],
        'version'          => $data['VERSION'] ?? null,
        'compressed_bytes' => $bpt['compressed'],
        'serialized_bytes' => $bpt['serialized'] !== null ? strlen($bpt['serialized']) : null,
        'logic_bytes'      => strlen($logicJson),
        'document_fields'  => count($docFields),
        'document_fields_share' => $total ? round(100 * $docFieldsBytes / $total) : 0,
        'nodes'            => $stats['nodes'],
        'max_depth'        => $stats['depth'],
        'deactivated'      => $stats['deactivated'],
        'parameters'       => count($data['PARAMETERS'] ?? []),
        'variables'        => count($data['VARIABLES'] ?? []),
        'constants'        => count($data['CONSTANTS'] ?? []),
        'types'            => $stats['types'],
        'conditions'       => $stats['conditions'],
        'portal_bindings'  => $portal,
        'errors'           => $errors,
        'warnings'         => $warnings,
    ];
}

function walk(array $node, int $depth, array &$s): void
{
    $type = (string) ($node['Type'] ?? '?');
    $props = is_array($node['Properties'] ?? null) ? $node['Properties'] : [];
    $name = (string) ($node['Name'] ?? '');
    $label = trim($type . ' ' . $name . ' «' . ($props['Title'] ?? '') . '»');

    $s['types'][$type] = ($s['types'][$type] ?? 0) + 1;
    $s['nodes']++;
    $s['depth'] = max($s['depth'], $depth);
    $s['names'][] = $name;
    if (($node['Activated'] ?? 'Y') === 'N') {
        $s['deactivated']++;
    }
    if (isset(FORBIDDEN_TYPES[$type])) {
        $s['forbidden'][] = $type;
    }
    if ($type === 'WhileActivity') {
        $s['while']++;
    }
    if (in_array($type, WAITING_TYPES, true) && in_array((string) ($props['TimeoutDuration'] ?? ''), ['', '0'], true)) {
        $s['waiting_no_timeout'][] = $label;
    }
    foreach ($props as $key => $value) {
        if (is_string($key) && str_ends_with(strtolower($key), 'condition')) {
            $s['conditions'][$key] = ($s['conditions'][$key] ?? 0) + 1;
            collectConditionFields($key, $value, $s);
        }
    }
    if ($type === 'SetFieldActivity' && is_array($props['FieldValue'] ?? null)) {
        array_push($s['doc_fields_used'], ...array_map('strval', array_keys($props['FieldValue'])));
    }
    if ($type === 'SetVariableActivity' && is_array($props['VariableValue'] ?? null)) {
        array_push($s['set_vars'], ...array_map('strval', array_keys($props['VariableValue'])));
    }
    foreach ((array) ($props['RequestedInformation'] ?? []) as $field) {
        if (is_array($field) && isset($field['Name'])) {
            $s['requested'][] = (string) $field['Name'];
        }
    }
    collectRawUserIds($props, $name, $s);

    foreach ((array) ($node['Children'] ?? []) as $child) {
        if (is_array($child)) {
            walk($child, $depth + 1, $s);
        }
    }
}

/**
 * Что проверяют условия: fieldcondition и propertyvariablecondition — списки
 * [имя, оператор, значение, связка]; mixedcondition — объекты {object, field, operator, value, joiner}.
 */
function collectConditionFields(string $kind, mixed $value, array &$s): void
{
    if (!is_array($value)) {
        return;
    }
    foreach ($value as $cond) {
        if (!is_array($cond)) {
            continue;
        }
        if ($kind === 'fieldcondition' && isset($cond[0])) {
            $s['doc_fields_used'][] = (string) $cond[0];
        } elseif ($kind === 'propertyvariablecondition' && isset($cond[0])) {
            $s['propvar_used'][] = (string) $cond[0];
        } elseif ($kind === 'mixedcondition' && ($cond['object'] ?? '') === 'Document') {
            $s['doc_fields_used'][] = (string) ($cond['field'] ?? '');
        }
    }
}

function collectRawUserIds(array $props, string $activity, array &$s, string $path = ''): void
{
    foreach ($props as $key => $value) {
        $keyPath = $path === '' ? (string) $key : "{$path}.{$key}";
        if (is_array($value)) {
            if (is_string($key) && preg_match(USER_PROP_RE, $key)) {
                foreach ($value as $item) {
                    if (is_string($item) && ctype_digit($item) || is_int($item)) {
                        $s['raw_user_ids'][] = "{$keyPath}={$item} ({$activity})";
                    }
                }
            }
            collectRawUserIds($value, $activity, $s, $keyPath);
        } elseif (is_string($key) && preg_match(USER_PROP_RE, $key)
            && (is_int($value) || (is_string($value) && ctype_digit($value) && $value !== '0'))) {
            $s['raw_user_ids'][] = "{$keyPath}={$value} ({$activity})";
        }
    }
}

function compactTree(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }
    $isList = array_is_list($value);
    $out = [];
    foreach ($value as $k => $v) {
        if ($k === 'Node' || ($k === 'Activated' && $v === 'Y')) {
            continue;
        }
        $v = compactTree($v);
        if ($v === '' || $v === null || $v === []) {
            continue;
        }
        $out[$k] = $v;
    }
    return $isList ? array_values($out) : $out;
}

// ---------------------------------------------------------------- вывод

function printReport(array $r): void
{
    $kind = $r['kind'] === 'robots' ? 'роботы (автоматизация стадий)' : 'шаблон дизайнера БП';
    $out = [];
    $out[] = "== {$r['file']} ==";
    $out[] = "Вид: {$kind}; корень {$r['root_type']}; VERSION " . var_export($r['version'], true);
    if ($r['serialized_bytes'] !== null) {
        $out[] = sprintf('Размер: %s Б .bpt, %s Б serialize; логика %s Б; DOCUMENT_FIELDS: %d полей, %d%% объёма',
            number_format($r['compressed_bytes'], 0, '', ' '), number_format($r['serialized_bytes'], 0, '', ' '),
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
    foreach ($r['errors'] as $e) {
        $out[] = "[ОШИБКА] {$e}";
    }
    foreach ($r['warnings'] as $w) {
        $out[] = "[ВНИМАНИЕ] {$w}";
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
        foreach ($r['types'] as $t => $n) {
            $types[$t] = ($types[$t] ?? 0) + $n;
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
    foreach ($counts as $k => $n) {
        $parts[] = "{$k}×{$n}";
    }
    return implode(', ', $parts);
}
