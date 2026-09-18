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
        return $spec;
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
