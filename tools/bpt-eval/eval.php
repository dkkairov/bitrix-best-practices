<?php
/**
 * Оценка навыка «БП по ТЗ» на типовых задачах. Команды:
 *   php tools/bpt-eval/eval.php prepare   --run=<прогон>
 *   php tools/bpt-eval/eval.php reference <задача|all> --run=<прогон>
 *   php tools/bpt-eval/eval.php check     <задача> --run=<прогон>
 *   php tools/bpt-eval/eval.php usage     <задача> --run=<прогон> --tokens=N --tools=N --ms=N
 *   php tools/bpt-eval/eval.php report    --run=<прогон>
 * Результаты — в work/eval/<прогон>/ (вне git). Стенд — BPT_EVAL_STAND (по умолчанию C:/docker/b24-test).
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
foreach (array_merge(glob("{$root}/tools/bpt/src/*.php") ?: [], glob(__DIR__ . '/src/*.php') ?: []) as $file) {
    require_once $file;
}

$args = array_slice($argv, 1);
$command = array_shift($args) ?? '';
$positional = [];
$opts = [];
foreach ($args as $arg) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/u', $arg, $m)) {
        $opts[$m[1]] = $m[2] ?? true;
    } else {
        $positional[] = $arg;
    }
}
$run = (string) ($opts['run'] ?? '');
if ($run === '' || !preg_match('/^[\w.-]+$/u', $run)) {
    fwrite(STDERR, "нужен --run=<прогон> (буквы, цифры, «-», «_», «.»)\n");
    exit(2);
}
$runDir = "{$root}/work/eval/{$run}";
@mkdir($runDir, 0777, true);

try {
    switch ($command) {
        case 'prepare':
            $stand = Stand::fromEnv();
            $setup = $stand->run('setup');
            $data = $stand->run('snapshot');
            $snapshot = Snapshot::fromBpt(['DOCUMENT_FIELDS' => $data['document_fields']], 'стенд')->toArray();
            $snapshot['users'] = $data['users'];
            $snapshot['groups'] = $data['groups'];
            $snapshot['smart'] = $data['smart'];
            $snapshot['related'] = [];
            foreach ($data['related_document_fields'] as $title => $fields) {
                // названия → коды — той же группировкой, что поля документа (одинаковые названия — с кодом)
                $snapshot['related'][$title] = ['fields' => Snapshot::fromBpt(['DOCUMENT_FIELDS' => $fields], $title)->section('field'),
                    'document_fields' => $fields];
            }
            file_put_contents("{$runDir}/portal.yaml", "# Снимок тестового стенда для оценки, прогон {$run}, "
                . date('Y-m-d H:i') . ". Настоящий, не синтетический.\n" . SpecReader::dump($snapshot));
            file_put_contents("{$runDir}/setup.json", json_encode($setup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            echo 'Стенд готов: ', ($setup['created'] ? implode(', ', $setup['created']) : 'всё уже было'), PHP_EOL;
            if ($setup['deactivated']) {
                echo 'Деактивированы чужие шаблоны с автозапуском: #', implode(', #', $setup['deactivated']), PHP_EOL;
            }
            echo "Снимок: {$runDir}/portal.yaml", PHP_EOL;
            exit(0);
        default:
            fwrite(STDERR, "неизвестная команда «{$command}»\n");
            exit(2);
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Ошибка: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
