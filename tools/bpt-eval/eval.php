<?php
/**
 * Оценка навыка «БП по ТЗ» на типовых задачах. Команды:
 *   php tools/bpt-eval/eval.php prepare   --run=<прогон>
 *   php tools/bpt-eval/eval.php reference <задача|all> --run=<прогон>
 *   php tools/bpt-eval/eval.php input     <задача> --run=<прогон>   (только review/modify)
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
        case 'input':
            // Входной шаблон для задач вида review/modify: агент получает только .bpt (как выгрузку
            // из дизайнера), а не спецификацию — иначе это ответ на задачу, а не исходные данные.
            $task = (string) ($positional[0] ?? '');
            if ($task === '') {
                throw new EvalException('нужна задача первым аргументом: input <задача> --run=<прогон>');
            }
            $checker = new CheckRunner(Stand::fromEnv(), $root, $run);
            $acceptance = Acceptance::load($checker->taskDir($task) . '/acceptance.yaml');
            if ($acceptance->input() === '') {
                throw new EvalException("{$task}: у задачи вида «{$acceptance->kind()}» нет входного шаблона");
            }
            $inputSpec = $checker->taskDir($task) . '/' . $acceptance->input();
            $snapshot = Snapshot::load("{$runDir}/portal.yaml");
            $compiled = (new Compiler(Catalog::load(), $snapshot, true))->compile(SpecReader::read($inputSpec));
            if ($compiled['errors']) {
                throw new EvalException("{$task}: входной шаблон не собрался — " . implode('; ', $compiled['errors']));
            }
            $taskDir = "{$runDir}/{$task}";
            if (!is_dir($taskDir) && !@mkdir($taskDir, 0777, true)) {
                throw new EvalException("не удалось создать каталог задачи: {$taskDir}");
            }
            BptFile::write("{$taskDir}/input.bpt", $compiled['bpt'], null, true);
            echo "{$task}: входной шаблон — {$taskDir}/input.bpt", PHP_EOL;
            exit(0);
        case 'check':
            $task = (string) ($positional[0] ?? '');
            $checker = new CheckRunner(Stand::fromEnv(), $root, $run);
            // У ревью результат агента — отчёт, а не спецификация: сверять его с дефектами будет
            // проверяющий, сюда попадает только факт наличия отчёта.
            $kind = Acceptance::load($checker->taskDir($task) . '/acceptance.yaml')->kind();
            $answer = $kind === 'review' ? 'review.md' : 'process.bizproc.yaml';
            $result = $checker->check($task, "{$runDir}/{$task}/{$answer}", "{$runDir}/{$task}");
            if ($kind === 'review') {
                printf("%s: отчёт ревью %s" . PHP_EOL, $task, $result['reason'] ?: 'на месте');
                exit(0);
            }
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
                // Эталоны — вне папки прогона: агент-исполнитель пишет решение в work/eval/<прогон>/<задача>/
                // и не должен видеть готовый эталон рядом со своей рабочей папкой.
                $result = $checker->check($task, $reference, "{$root}/work/eval/_reference/{$run}/{$task}");
                // Грязный после уборки стенд — тоже не «эталон прошёл», даже если сам процесс отработал
                $reasons = array_filter([$result['reason'], $result['cleanup_error']]);
                $ok = $result['compile'] === 'ok' && $result['import'] === 'ok' && !$reasons;
                $failed += $ok ? 0 : 1;
                printf("%s: %s%s\n", $task, $ok ? 'эталон прошёл' : 'ЭТАЛОН НЕ ПРОШЁЛ', $reasons ? ' — ' . implode('; ', $reasons) : '');
            }
            exit($failed ? 1 : 0);
        case 'usage':
            $task = (string) ($positional[0] ?? '');
            // Задача — позиционный аргумент; без проверки «--task=T02» ушёл бы в $opts, а расход
            // записался бы под пустым ключом и потерялся в отчёте
            if ($task === '') {
                throw new EvalException('нужна задача первым аргументом: usage <задача> --run=<прогон>');
            }
            $file = "{$runDir}/agents.json";
            // Report::readJson: битый файл — понятная ошибка с путём, а не молча пустой массив (иначе
            // расход всех прошлых задач прогона обнулился бы вместе с одной поломанной записью)
            $agents = is_file($file) ? Report::readJson($file) : [];
            $agents[$task] = ['tokens' => (int) ($opts['tokens'] ?? 0), 'tool_uses' => (int) ($opts['tools'] ?? 0),
                'duration_ms' => (int) ($opts['ms'] ?? 0)];
            $agentsWritten = file_put_contents($file, json_encode($agents, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            if ($agentsWritten === false) {
                throw new EvalException("не удалось записать {$file}");
            }
            exit(0);
        case 'report':
            $report = Report::fromRunDir($runDir, __DIR__ . '/tasks');
            $reportFile = "{$runDir}/report.md";
            $reportWritten = file_put_contents($reportFile, $report->toMarkdown());
            if ($reportWritten === false) {
                throw new EvalException("не удалось записать {$reportFile}");
            }
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
