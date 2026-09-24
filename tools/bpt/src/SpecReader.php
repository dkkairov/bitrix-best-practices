<?php
/**
 * Чтение и запись спецификаций процессов: YAML (расширение yaml) или JSON.
 * Структура одна и та же; описание формата — в tools/bpt/SPEC.md.
 */

declare(strict_types=1);

final class SpecReader
{
    public static function hasYaml(): bool
    {
        return function_exists('yaml_parse');
    }

    public static function read(string $file): array
    {
        $text = BptFile::readFile($file);
        return self::parse($text, self::detectFormat($file, $text));
    }

    public static function parse(string $text, string $format): array
    {
        $spec = $format === 'yaml' ? self::parseYaml($text) : self::parseJson($text);
        if (!is_array($spec) || array_is_list($spec)) {
            throw new BptException('это не спецификация процесса: ожидается объект с полями bizproc, name, steps');
        }
        if ($format === 'yaml') {
            self::assertNoBooleanKeys($text);
        }
        return $spec;
    }

    /**
     * YAML 1.1 считает n, y, on, off, yes, no, true, false булевыми, а null и ~ — пустым значением.
     * Код переменной или константы с таким именем разбирается не в строку, а в ключ 0, 1 или '' —
     * причём «n» и «off» дают один и тот же ключ 0, то есть одно объявление затирает другое, молча.
     *
     * Проверяем исходный текст, а не разобранную структуру: единственный такой ключ даёт массив
     * [0 => ...], неотличимый от обычного списка. Ключи в кавычках ("n":) под правило не попадают —
     * это и есть способ записать такой код.
     */
    private static function assertNoBooleanKeys(string $text): void
    {
        $found = [];
        foreach (self::mappingLines($text) as $no => $line) {
            if (preg_match('/^\s*(?:-\s+)?(n|y|on|off|yes|no|true|false|null|~)\s*:(?:\s|$)/ui', $line, $m)) {
                $found[] = 'строка ' . $no . ': «' . trim($m[1]) . '»';
            }
        }
        if (!$found) {
            return;
        }
        throw new BptException('YAML считает эти ключи булевыми, код при разборе теряется'
            . ' (например, «n» и «off» дают один и тот же ключ) — возьмите их в кавычки, например "n":'
            . PHP_EOL . '  ' . implode(PHP_EOL . '  ', $found));
    }

    /**
     * Строки текста (номер => содержимое) за вычетом содержимого блочных скаляров: внутри
     * «EventText: |» лежит произвольный текст заказчика, и строка вида «no: ...» там не ключ.
     *
     * @return array<int, string>
     */
    private static function mappingLines(string $text): array
    {
        $lines = [];
        $blockIndent = null;
        foreach (preg_split('/\R/u', $text) ?: [] as $i => $line) {
            $indent = strlen($line) - strlen(ltrim($line, ' '));
            if ($blockIndent !== null) {
                if (trim($line) === '' || $indent > $blockIndent) {
                    continue;   // ещё внутри блока
                }
                $blockIndent = null;
            }
            $lines[$i + 1] = $line;
            if (preg_match('/:\s*[|>][+-]?\d*\s*$/u', $line)) {
                $blockIndent = $indent;
            }
        }
        return $lines;
    }

    /** YAML при наличии расширения, иначе JSON. */
    public static function dump(array $spec): string
    {
        if (!self::hasYaml()) {
            return BptFile::toJson($spec);
        }
        return (string) yaml_emit($spec, YAML_UTF8_ENCODING);
    }

    public static function detectFormat(string $file, string $text): string
    {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext === 'json') {
            return 'json';
        }
        if (in_array($ext, ['yaml', 'yml'], true)) {
            return 'yaml';
        }
        return str_starts_with(ltrim($text), '{') ? 'json' : 'yaml';
    }

    private static function parseYaml(string $text): mixed
    {
        if (!self::hasYaml()) {
            throw new BptException('нет расширения PHP yaml — сохраните спецификацию в JSON'
                . ' или включите расширение (см. tools/bpt/README.md)');
        }
        $error = null;
        set_error_handler(function (int $no, string $message) use (&$error): bool {
            $error = $message;
            return true;
        });
        try {
            $parsed = yaml_parse($text);
        } finally {
            restore_error_handler();
        }
        if ($parsed === false || $error !== null) {
            throw new BptException('не удалось разобрать YAML' . ($error !== null ? ": {$error}" : ''));
        }
        return $parsed;
    }

    private static function parseJson(string $text): mixed
    {
        try {
            return json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new BptException('не удалось разобрать JSON: ' . $e->getMessage());
        }
    }
}
