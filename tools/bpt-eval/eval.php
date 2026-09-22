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

try {
    if (!is_dir($runDir) && !@mkdir($runDir, 0777, true)) {
        throw new EvalException("не удалось создать каталог прогона: {$runDir}");
    }
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
            $portalFile = "{$runDir}/portal.yaml";
            $portalWritten = file_put_contents($portalFile, "# Снимок тестового стенда для оценки, прогон {$run}, "
                . date('Y-m-d H:i') . ". Настоящий, не синтетический.\n" . SpecReader::dump($snapshot));
            if ($portalWritten === false) {
                throw new EvalException("не удалось записать {$portalFile}");
            }
            $setupFile = "{$runDir}/setup.json";
            $setupWritten = file_put_contents($setupFile, json_encode($setup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            if ($setupWritten === false) {
                throw new EvalException("не удалось записать {$setupFile}");
            }
            echo 'Стенд готов: ', ($setup['created'] ? implode(', ', $setup['created']) : 'всё уже было'), PHP_EOL;
            if ($setup['deactivated']) {
                echo 'Деактивированы чужие шаблоны с автозапуском: #', implode(', #', $setup['deactivated']), PHP_EOL;
            }
            echo "Снимок: {$runDir}/portal.yaml", PHP_EOL;
            exit(0);
        case 'check':
            $task = (string) ($positional[0] ?? '');
            $result = (new CheckRunner(Stand::fromEnv(), $root, $run))->check($task,
                "{$runDir}/{$task}/process.bizproc.yaml", "{$runDir}/{$task}");
            printf("%s: сборка %s, импорт %s, сценарии %d/%d%s\n", $task, $result['compile'], $result['import'],
                count(array_filter($result['scenarios'], fn ($s) => $s['ok'])), count($result['scenarios']),
                $result['reason'] ? " — {$result['reason']}" : '');
            if ($result['cleanup_error']) {   // не влияет на итог задачи, но стенд остался грязным — видно сразу
                echo "Внимание: {$result['cleanup_error']}\n";
            }
            exit(0);
        case 'reference':
            $checker = new CheckRunner(Stand::fromEnv(), $root, $run);
            $tasks = ($positional[0] ?? 'all') === 'all'
                ? array_map(fn ($d) => explode('-', basename($d))[0], glob("{$root}/tools/bpt-eval/tasks/*", GLOB_ONLYDIR) ?: [])
                : [(string) $positional[0]];
            $failed = 0;
            foreach ($tasks as $task) {
                $reference = $checker->taskDir($task) . '/reference.bizproc.yaml';
                if (!is_file($reference)) {
                    echo "{$task}: эталона нет — пропуск\n";
                    continue;
                }
                $result = $checker->check($task, $reference, "{$runDir}/_reference/{$task}");
                // Грязный после уборки стенд — тоже не «эталон прошёл», даже если сам процесс отработал
                $reasons = array_filter([$result['reason'], $result['cleanup_error']]);
                $ok = $result['compile'] === 'ok' && $result['import'] === 'ok' && !$reasons;
                $failed += $ok ? 0 : 1;
                printf("%s: %s%s\n", $task, $ok ? 'эталон прошёл' : 'ЭТАЛОН НЕ ПРОШЁЛ', $reasons ? ' — ' . implode('; ', $reasons) : '');
            }
            exit($failed ? 1 : 0);
        case 'usage':
            $task = (string) ($positional[0] ?? '');
            $file = "{$runDir}/agents.json";
            $agents = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
            $agents[$task] = ['tokens' => (int) ($opts['tokens'] ?? 0), 'tool_uses' => (int) ($opts['tools'] ?? 0),
                'duration_ms' => (int) ($opts['ms'] ?? 0)];
            file_put_contents($file, json_encode($agents, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            exit(0);
        case 'report':
            $report = Report::fromRunDir($runDir, __DIR__ . '/tasks');
            file_put_contents("{$runDir}/report.md", $report->toMarkdown());
            echo $report->toMarkdown();
            exit(0);
        default:
            fwrite(STDERR, "неизвестная команда «{$command}»\n");
            exit(2);
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Ошибка: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
