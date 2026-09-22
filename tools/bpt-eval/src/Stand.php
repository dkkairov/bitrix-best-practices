<?php
/**
 * Связь с тестовым стендом: скрипт stand/<имя>.php вместе с входными данными и общей частью
 * уходит в контейнер php одним потоком через stdin; ответ — JSON в stdout.
 */

declare(strict_types=1);

final class Stand
{
    public function __construct(private readonly string $dir)
    {
    }

    public static function fromEnv(): self
    {
        return new self((string) (getenv('BPT_EVAL_STAND') ?: 'C:/docker/b24-test'));
    }

    public static function compose(string $script, array $input): string
    {
        $standDir = dirname(__DIR__) . '/stand';
        $parts = [];
        foreach (["{$standDir}/_bootstrap.php", "{$standDir}/{$script}.php"] as $file) {
            if (!is_file($file)) {
                throw new EvalException("нет скрипта стенда: {$file}");
            }
            $parts[] = preg_replace('/^<\?php\s*/', '', (string) file_get_contents($file));
        }
        $payload = base64_encode(json_encode($input, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return "<?php\n\$GLOBALS['EVAL_INPUT'] = json_decode(base64_decode('{$payload}'), true);\n"
            . implode("\n", $parts);
    }

    public function run(string $script, array $input = []): array
    {
        $cmd = ['docker', 'compose', 'exec', '-T', '--user=bitrix', 'php', 'php'];
        // stderr — во временный файл, а не в pipe: два pipe (stdout+stderr), которые родитель читает
        // последовательно, — стандартная ловушка proc_open (дочерний процесс может заполнить буфер
        // второго потока, пока первый ещё не вычитан, и всё зависнет без исключения и диагностики).
        $errFile = tempnam(sys_get_temp_dir(), 'bpteval');
        if ($errFile === false) {
            throw new EvalException('не удалось создать временный файл для stderr стенда');
        }
        try {
            $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $errFile, 'w']], $pipes, $this->dir);
            if (!is_resource($proc)) {
                throw new EvalException("не удалось запустить docker compose в {$this->dir}");
            }
            fwrite($pipes[0], self::compose($script, $input));
            fclose($pipes[0]);
            $out = (string) stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $code = proc_close($proc);
            $err = (string) file_get_contents($errFile);
            $json = json_decode(trim($out), true);
            if ($code !== 0 || !is_array($json)) {
                throw new EvalException("стенд, {$script}: код {$code}; " . mb_substr(trim($err . ' ' . $out), 0, 1500));
            }
            if (isset($json['error'])) {
                throw new EvalException("стенд, {$script}: {$json['error']}");
            }
            return $json;
        } finally {
            @unlink($errFile);
        }
    }
}
