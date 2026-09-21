<?php
/**
 * Чтение и запись файлов шаблонов .bpt.
 *
 * Формат: gzcompress(serialize(array)) с ключами VERSION, TEMPLATE, PARAMETERS,
 * VARIABLES, CONSTANTS, DOCUMENT_FIELDS. Разбор — только с allowed_classes => false.
 */

declare(strict_types=1);

final class BptFile
{
    public const JSON_FLAT = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR;
    public const JSON_OUT = self::JSON_FLAT | JSON_PRETTY_PRINT;

    /** @return array{data: array, serialized: string, compressed: int} */
    public static function read(string $file, ?string $charset = null): array
    {
        $raw = self::readFile($file);
        $serialized = self::decompress($raw);
        if ($serialized === null) {
            throw new BptException("{$file}: не удалось распаковать (ожидается zlib/gzcompress)");
        }
        $data = @unserialize($serialized, ['allowed_classes' => false]);
        if (!is_array($data)) {
            throw new BptException("{$file}: содержимое не является сериализованным массивом PHP");
        }
        self::assertNoObjects($data, $file);
        self::assertTemplate($data, $file);
        if ($charset !== null) {
            $data = self::convertTree($data, $charset, 'UTF-8');
        } else {
            self::assertUtf8($data, $file);
        }
        return ['data' => $data, 'serialized' => $serialized, 'compressed' => strlen($raw)];
    }

    /** .bpt или ранее выгруженный JSON — вид определяется по содержимому. */
    public static function readAny(string $file, ?string $charset = null): array
    {
        $content = self::readFile($file);
        if (str_starts_with(ltrim($content), '{')) {
            $data = self::fromJson($content);
            self::assertTemplate($data, $file);
            return ['data' => $data, 'serialized' => null, 'compressed' => null];
        }
        return self::read($file, $charset);
    }

    public static function readJsonFile(string $file): array
    {
        return self::fromJson(self::readFile($file));
    }

    public static function write(string $file, array $data, ?string $charset = null, bool $force = false): void
    {
        if (file_exists($file) && !$force) {
            throw new BptException("файл {$file} уже существует (добавьте --force)");
        }
        self::assertTemplate($data, $file);
        self::writeFileContents($file, gzcompress(self::serializeFor($data, $charset), 9));
    }

    /** Сериализация в том виде, в каком она лежит внутри .bpt (для сверки обратимости). */
    public static function serializeFor(array $data, ?string $charset = null): string
    {
        return serialize($charset !== null ? self::convertTree($data, 'UTF-8', $charset) : $data);
    }

    public static function toJson(array $data): string
    {
        try {
            return json_encode($data, self::JSON_OUT);
        } catch (JsonException $e) {
            throw new BptException('не удалось перевести в JSON (' . $e->getMessage()
                . '). Если портал не в UTF-8, укажите --charset=windows-1251');
        }
    }

    public static function fromJson(string $json): array
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new BptException('JSON не содержит объект шаблона');
        }
        return $data;
    }

    public static function flatJson(mixed $value): string
    {
        return json_encode($value, self::JSON_FLAT);
    }

    public static function readFile(string $file): string
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new BptException("файл не найден или недоступен: {$file}");
        }
        return (string) file_get_contents($file);
    }

    public static function writeFileContents(string $file, string $content): void
    {
        if (file_put_contents($file, $content) === false) {
            throw new BptException("не удалось записать {$file}");
        }
    }

    /** Убирает служебные ключи и пустые значения — для чтения, не для сборки. */
    public static function compactTree(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        $isList = array_is_list($value);
        $out = [];
        foreach ($value as $key => $item) {
            if ($key === 'Node' || ($key === 'Activated' && $item === 'Y')) {
                continue;
            }
            $item = self::compactTree($item);
            if ($item === '' || $item === null || $item === []) {
                continue;
            }
            $out[$key] = $item;
        }
        return $isList ? array_values($out) : $out;
    }

    public static function convertTree(mixed $value, string $from, string $to): mixed
    {
        if (is_string($value)) {
            return mb_convert_encoding($value, $to, $from);
        }
        if (!is_array($value)) {
            return $value;
        }
        $out = [];
        foreach ($value as $key => $item) {
            $newKey = is_string($key) ? mb_convert_encoding($key, $to, $from) : $key;
            $out[$newKey] = self::convertTree($item, $from, $to);
        }
        return $out;
    }

    public static function isKnownCharset(string $charset): bool
    {
        return in_array(strtolower($charset), array_map('strtolower', mb_list_encodings()), true);
    }

    private static function decompress(string $raw): ?string
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

    private static function assertNoObjects(mixed $value, string $file): void
    {
        if (is_object($value)) {
            throw new BptException("{$file}: внутри есть сериализованный объект — такой файл не обрабатываем");
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                self::assertNoObjects($item, $file);
            }
        }
    }

    private static function assertUtf8(mixed $value, string $file): void
    {
        if (is_string($value)) {
            if (!mb_check_encoding($value, 'UTF-8')) {
                throw new BptException("{$file}: строки не в UTF-8 — укажите --charset=windows-1251");
            }
            return;
        }
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (is_string($key) && !mb_check_encoding($key, 'UTF-8')) {
                    throw new BptException("{$file}: строки не в UTF-8 — укажите --charset=windows-1251");
                }
                self::assertUtf8($item, $file);
            }
        }
    }

    private static function assertTemplate(array $data, string $file): void
    {
        $root = $data['TEMPLATE'][0] ?? null;
        if (!is_array($root) || !isset($root['Type'])) {
            throw new BptException("{$file}: нет корневого действия TEMPLATE[0] — это не шаблон БП");
        }
    }
}
