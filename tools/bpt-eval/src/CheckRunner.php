<?php
/**
 * Проверка одной задачи: пересборка спецификации с --strict → импорт на стенд → константы → сценарии
 * → уборка. Результат — result.json в папке задачи. Категорию провала (spec/tool/skill/harness)
 * человек ставит при разборе; автоматически пишется только причина.
 */

declare(strict_types=1);

final class CheckRunner
{
    public function __construct(private readonly Stand $stand, private readonly string $root, private readonly string $run)
    {
    }

    public function taskDir(string $task): string
    {
        $dirs = glob("{$this->root}/tools/bpt-eval/tasks/{$task}-*", GLOB_ONLYDIR) ?: [];
        if (count($dirs) !== 1) {
            throw new EvalException("задача {$task}: папка tasks/{$task}-* не найдена или не одна");
        }
        return $dirs[0];
    }

    public function check(string $task, string $specFile, string $outDir): array
    {
        $acceptance = Acceptance::load($this->taskDir($task) . '/acceptance.yaml');
        $roles = Roles::load("{$this->root}/tools/bpt-eval/roles.yaml");
        if ($missing = $roles->missing($acceptance->roleNames())) {
            throw new EvalException("{$task}: в roles.yaml нет ролей " . implode(', ', $missing));
        }
        @mkdir($outDir, 0777, true);
        $result = ['task' => $task, 'run' => $this->run, 'spec' => $specFile, 'compile' => 'skip', 'compile_errors' => [],
            'import' => 'skip', 'import_error' => '', 'template_id' => null, 'constants_matched' => [],
            'constants_unmatched' => [], 'scenarios' => [], 'reason' => '', 'category' => '', 'note' => ''];
        if (!is_file($specFile)) {
            if ($acceptance->scenarios()) {   // задача со сценариями без решения — провал, а не «0 из 0»
                $result['compile'] = 'fail';
                $result['reason'] = 'нет спецификации';
            }
            return $this->save($outDir, $result);
        }

        $snapshot = Snapshot::load("{$this->root}/work/eval/{$this->run}/portal.yaml");
        $compiled = (new Compiler(Catalog::load(), $snapshot, true))->compile(SpecReader::read($specFile));
        if ($compiled['errors']) {
            $result['compile'] = 'fail';
            $result['compile_errors'] = $compiled['errors'];
            $result['reason'] = 'сборка';
            return $this->save($outDir, $result);
        }
        $result['compile'] = 'ok';
        $bptFile = "{$outDir}/eval.bpt";
        BptFile::write($bptFile, $compiled['bpt'], null, true);

        $import = $this->stand->run('import', ['bpt_b64' => base64_encode((string) file_get_contents($bptFile)),
            'name' => "EVAL {$this->run} {$task}", 'auto_execute' => $acceptance->start() === 'create' ? 1 : 0]);
        if (!$import['ok']) {
            $result['import'] = 'fail';
            $result['import_error'] = $import['message'];
            $result['reason'] = 'импорт';
            return $this->save($outDir, $result);
        }
        $result['import'] = 'ok';
        $result['template_id'] = $import['template_id'];

        try {
            $constants = ConstantMatcher::match($import['constants'], $roles);
            $result['constants_matched'] = $constants['matched'];
            $result['constants_unmatched'] = $constants['unmatched'];
            if ($constants['unmatched']) {
                $result['reason'] = 'константы без сопоставления: ' . implode(', ', $constants['unmatched']);
                return $this->save($outDir, $result);
            }
            if ($acceptance->scenarios()) {
                try {
                    $run = $this->stand->run('runner', ['task' => $task, 'template_id' => $import['template_id'],
                        'acceptance' => $acceptance->toArray(), 'roles' => $roles->toArray(), 'constants' => $constants['matched']]);
                } catch (EvalException $e) {   // упал сам прогонщик — не против навыка: чиним и перегоняем
                    $result['reason'] = 'прогонщик: ' . $e->getMessage();
                    $result['category'] = 'harness';
                    return $this->save($outDir, $result);
                }
                $result['scenarios'] = $run['scenarios'];
                $failed = array_filter($run['scenarios'], fn ($s) => !$s['ok']);
                $result['reason'] = $failed ? 'сценарий: ' . implode(', ', array_column($failed, 'name')) : '';
            }
        } finally {
            $this->stand->run('cleanup', ['template_id' => $import['template_id']]);
        }
        return $this->save($outDir, $result);
    }

    private function save(string $outDir, array $result): array
    {
        file_put_contents("{$outDir}/result.json", json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $result;
    }
}
