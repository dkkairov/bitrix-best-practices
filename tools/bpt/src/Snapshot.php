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
        private readonly array $related = [],
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
        return new self($sections, $data['document_fields'] ?? [], $source, $data['document'] ?? null,
            self::relatedFrom($data['related'] ?? [], $source));
    }

    /** Поля и стадии берутся из DOCUMENT_FIELDS экспорта; людей и группы вписывают вручную. */
    public static function fromBpt(array $bpt, string $source = 'экспорт .bpt'): self
    {
        $documentFields = $bpt['DOCUMENT_FIELDS'] ?? [];
        $fields = [];
        $byName = [];
        foreach ($documentFields as $code => $field) {
            $name = trim((string) ($field['Name'] ?? $code));
            // Группируем по нормализованному названию: «Договор» и «договор » — одно и то же
            $byName[self::normalizeKey($name)][] = ['name' => $name, 'code' => (string) $code];
        }
        foreach ($byName as $group) {
            if (count($group) === 1) {
                $fields[$group[0]['name']] = $group[0]['code'];
                continue;
            }
            foreach ($group as $item) {   // одинаковые названия различаем кодом
                $fields["{$item['name']} [{$item['code']}]"] = $item['code'];
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
        if ($this->related) {
            $out['related'] = $this->related;
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
        if ($kind === 'field' && str_contains($name, '/')) {
            [$entity, $field] = array_map('trim', explode('/', $name, 2));
            foreach ($this->related as $title => $section) {
                if (self::normalizeKey((string) $title) !== self::normalizeKey($entity)) {
                    continue;
                }
                foreach ($section['fields'] as $fieldTitle => $code) {
                    if (self::normalizeKey((string) $fieldTitle) === self::normalizeKey($field)) {
                        return (string) $code;
                    }
                }
                throw new BptException("в снимке ({$this->source}) у «{$title}» нет поля «{$field}»; есть: "
                    . implode(', ', array_slice(array_keys($section['fields']), 0, 10)));
            }
        }
        $needle = self::normalizeKey($name);
        $exact = [];
        $matches = [];
        foreach ($this->sections[$kind] as $title => $value) {
            $title = (string) $title;
            if (self::normalizeKey($title) === $needle) {
                $exact[$title] = (string) $value;
                continue;
            }
            // «Воронка/Стадия» можно назвать просто «Стадия», если название однозначно
            $short = str_contains($title, '/') ? substr($title, strrpos($title, '/') + 1) : null;
            if ($short !== null && self::normalizeKey($short) === $needle) {
                $matches[$title] = (string) $value;
            }
        }
        if (count(array_unique($exact)) === 1) {
            return (string) reset($exact);
        }
        if (count($exact) > 1) {
            throw new BptException("«{$name}» ({$kind}) в снимке встречается несколько раз: "
                . implode(', ', array_keys($exact)) . ' — уточните название');
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

    /** Раздел related смарт-процесса по его ID (ID — из раздела smart по тому же названию). */
    public function relatedByTypeId(string $typeId): ?array
    {
        foreach ($this->related as $title => $section) {
            try {
                $id = $this->resolve('smart', (string) $title);
            } catch (BptException) {
                continue;
            }
            if ($id === $typeId) {
                return ['title' => (string) $title] + $section;
            }
        }
        return null;
    }

    /** related: {Смарт-процесс: {fields: {Название: код}, document_fields: {код: описание}}} — связанные документы. */
    private static function relatedFrom(mixed $related, string $source): array
    {
        if (!is_array($related)) {
            throw new BptException("{$source}: раздел related должен быть объектом «смарт-процесс: {fields: …}»");
        }
        $out = [];
        foreach ($related as $title => $section) {
            $fields = is_array($section) && is_array($section['fields'] ?? null) ? $section['fields'] : null;
            if ($fields === null) {
                throw new BptException("{$source}: related.{$title} — нужен объект fields: {Название поля: код}");
            }
            $out[(string) $title] = ['fields' => array_map('strval', $fields),
                'document_fields' => is_array($section['document_fields'] ?? null) ? $section['document_fields'] : []];
        }
        return $out;
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

    private static function normalizeKey(string $name): string
    {
        return mb_strtolower(trim($name));
    }
}
