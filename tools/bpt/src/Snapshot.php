<?php
/**
 * Снимок портала: названия → идентификаторы (поля, стадии, пользователи, группы,
 * смарт-процессы, шаблоны). Подставляется в плейсхолдеры {{вид:Название}} спецификации.
 *
 * Снимок содержит данные клиента, поэтому в git не кладётся (см. .gitignore).
 * Поля и стадии можно вытащить из любого экспорта: bpt.php snapshot file.bpt
 */

declare(strict_types=1);

final class Snapshot
{
    public const KINDS = ['field' => 'fields', 'stage' => 'stages', 'user' => 'users',
        'group' => 'groups', 'smart' => 'smart', 'template' => 'templates'];

    private function __construct(
        private readonly array $sections,
        private readonly array $documentFields,
        private readonly string $source,
        private readonly ?string $document = null,
    ) {
    }

    public static function load(string $file): self
    {
        return self::fromArray(SpecReader::read($file), $file);
    }

    public static function fromArray(array $data, string $source): self
    {
        $sections = [];
        foreach (self::KINDS as $kind => $section) {
            $values = $data[$section] ?? [];
            if (!is_array($values)) {
                throw new BptException("{$source}: раздел {$section} должен быть объектом «название: значение»");
            }
            $sections[$kind] = array_map(
                fn ($value) => is_scalar($value) ? (string) $value : $value,
                $values
            );
        }
        return new self($sections, $data['document_fields'] ?? [], $source, $data['document'] ?? null);
    }

    /** Поля и стадии берутся из DOCUMENT_FIELDS экспорта; людей и группы вписывают вручную. */
    public static function fromBpt(array $bpt, string $source = 'экспорт .bpt'): self
    {
        $documentFields = $bpt['DOCUMENT_FIELDS'] ?? [];
        $fields = [];
        $byName = [];
        foreach ($documentFields as $code => $field) {
            $name = trim((string) ($field['Name'] ?? $code));
            $byName[$name][] = (string) $code;
        }
        foreach ($byName as $name => $codes) {
            if (count($codes) === 1) {
                $fields[$name] = $codes[0];
                continue;
            }
            foreach ($codes as $code) {   // одинаковые названия различаем кодом
                $fields["{$name} [{$code}]"] = $code;
            }
        }
        $stages = [];
        foreach (($documentFields['STAGE_ID']['Options'] ?? []) as $code => $title) {
            $stages[(string) $title] = (string) $code;
        }
        return new self(
            ['field' => $fields, 'stage' => $stages, 'user' => [], 'group' => [], 'smart' => [], 'template' => []],
            $documentFields,
            $source,
            $bpt['DOCUMENT_TYPE'] ?? null,
        );
    }

    public function toArray(): array
    {
        $out = $this->document !== null ? ['document' => $this->document] : [];
        foreach (self::KINDS as $kind => $section) {
            $out[$section] = $this->sections[$kind];
        }
        if ($this->documentFields) {
            $out['document_fields'] = $this->documentFields;
        }
        return $out;
    }

    public function documentFields(): array
    {
        return $this->documentFields;
    }

    /** Название → идентификатор. Регистр и лишние пробелы не важны; стадию можно звать кратко. */
    public function resolve(string $kind, string $name): string
    {
        if (!isset(self::KINDS[$kind])) {
            throw new BptException("неизвестный вид плейсхолдера «{$kind}»; известные: "
                . implode(', ', array_keys(self::KINDS)));
        }
        $needle = $this->normalizeName($name);
        $matches = [];
        foreach ($this->sections[$kind] as $title => $value) {
            $title = (string) $title;
            if ($this->normalizeName($title) === $needle) {
                return (string) $value;
            }
            // «Воронка/Стадия» можно назвать просто «Стадия», если название однозначно
            $short = str_contains($title, '/') ? substr($title, strrpos($title, '/') + 1) : null;
            if ($short !== null && $this->normalizeName($short) === $needle) {
                $matches[$title] = (string) $value;
            }
        }
        if (count($matches) === 1) {
            return (string) reset($matches);
        }
        if (count($matches) > 1) {
            throw new BptException("«{$name}» ({$kind}) встречается несколько раз: "
                . implode(', ', array_keys($matches)) . ' — укажите полное название');
        }
        $known = array_slice(array_keys($this->sections[$kind]), 0, 10);
        throw new BptException("в снимке ({$this->source}) нет {$kind} «{$name}»"
            . ($known ? '; есть: ' . implode(', ', $known) : '; раздел пуст'));
    }

    /** @return array<string, string> раздел снимка: название → идентификатор */
    public function section(string $kind): array
    {
        return $this->sections[$kind] ?? [];
    }

    /** Обратный поиск: идентификатор → название (для разбора шаблона в спецификацию). */
    public function nameFor(string $kind, string $value): ?string
    {
        foreach ($this->sections[$kind] ?? [] as $title => $candidate) {
            if ((string) $candidate === $value) {
                return (string) $title;
            }
        }
        return null;
    }

    /** Заменяет {{вид:Название}} во всех строках и ключах; ошибки складывает в $errors. */
    public function substitute(mixed $value, string $path, array &$errors): mixed
    {
        if (is_string($value)) {
            return preg_replace_callback('/\{\{([a-zA-Zа-яА-Я_]+):([^}]+)\}\}/u', function (array $m) use ($path, &$errors) {
                try {
                    return $this->resolve(strtolower($m[1]), $m[2]);
                } catch (BptException $e) {
                    $errors[] = "{$path}: " . $e->getMessage();
                    return $m[0];
                }
            }, $value);
        }
        if (!is_array($value)) {
            return $value;
        }
        $out = [];
        foreach ($value as $key => $item) {
            $newKey = is_string($key) ? $this->substitute($key, $path, $errors) : $key;
            $out[$newKey] = $this->substitute($item, $path, $errors);
        }
        return $out;
    }

    public static function hasPlaceholder(mixed $value): bool
    {
        if (is_string($value)) {
            return (bool) preg_match('/\{\{[a-zA-Zа-яА-Я_]+:[^}]+\}\}/u', $value);
        }
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $key => $item) {
            if ((is_string($key) && self::hasPlaceholder($key)) || self::hasPlaceholder($item)) {
                return true;
            }
        }
        return false;
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(trim($name));
    }
}
