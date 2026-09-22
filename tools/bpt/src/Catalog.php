<?php
/**
 * Каталог действий: какие типы бывают, их алиасы, свойства, значения по умолчанию,
 * формы вложенности и возвращаемые значения. Данные — в catalog/activities.php.
 */

declare(strict_types=1);

final class Catalog
{
    private static ?self $instance = null;

    private array $aliases = [];

    private function __construct(private readonly array $data)
    {
        foreach ($this->data['activities'] as $type => $entry) {
            if (isset($entry['alias'])) {
                $this->aliases[$entry['alias']] = $type;
            }
        }
    }

    public static function load(?string $path = null): self
    {
        if ($path !== null) {
            return new self(require $path);
        }
        return self::$instance ??= new self(require dirname(__DIR__) . '/catalog/activities.php');
    }

    public function version(): int
    {
        return (int) $this->data['version'];
    }

    public function verified(): string
    {
        return (string) $this->data['verified'];
    }

    /** Алиас или тип → тип. Неизвестное — ошибка с подсказкой. */
    public function resolveType(string $aliasOrType): string
    {
        if (isset($this->data['activities'][$aliasOrType])) {
            return $aliasOrType;
        }
        if (isset($this->aliases[$aliasOrType])) {
            return $this->aliases[$aliasOrType];
        }
        $known = array_keys($this->aliases);
        sort($known);
        throw new BptException("неизвестное действие «{$aliasOrType}»; известные: " . implode(', ', $known));
    }

    public function has(string $type): bool
    {
        return isset($this->data['activities'][$type]);
    }

    public function entry(string $type): array
    {
        if (!$this->has($type)) {
            throw new BptException("неизвестное действие «{$type}»");
        }
        return $this->data['activities'][$type];
    }

    public function shape(string $type): string
    {
        return (string) $this->entry($type)['shape'];
    }

    public function alias(string $type): ?string
    {
        return $this->entry($type)['alias'] ?? null;
    }

    public function title(string $type): string
    {
        return (string) $this->entry($type)['title'];
    }

    public function isForbidden(string $type): bool
    {
        return isset($this->entry($type)['forbidden']);
    }

    public function forbiddenReason(string $type): ?string
    {
        return $this->entry($type)['forbidden'] ?? null;
    }

    /** Все свойства типа вместе с общими. */
    public function props(string $type): array
    {
        return $this->entry($type)['props'] + $this->data['common'];
    }

    public function propType(string $type, string $prop): ?string
    {
        return $this->props($type)[$prop]['type'] ?? null;
    }

    public function propSpec(string $type, string $prop): ?array
    {
        return $this->props($type)[$prop] ?? null;
    }

    /** Значения по умолчанию (без Title — он берётся отдельно методом title()). */
    public function defaults(string $type): array
    {
        $defaults = [];
        foreach ($this->props($type) as $prop => $spec) {
            if (array_key_exists('default', $spec)) {
                $defaults[$prop] = $spec['default'];
            }
        }
        return $defaults;
    }

    /**
     * Допустимые значения свойства — если каталог их знает (из проверки ядра ValidateProperties).
     * @return string[]|null
     */
    public function allowedValues(string $type, string $prop): ?array
    {
        return $this->props($type)[$prop]['values'] ?? null;
    }

    /** @return string[] */
    public function requiredProps(string $type): array
    {
        $required = [];
        foreach ($this->props($type) as $prop => $spec) {
            if ($spec['required'] ?? false) {
                $required[] = $prop;
            }
        }
        return $required;
    }

    /**
     * Что действие возвращает для ссылок {=A…:Результат}.
     * Для «Получить информацию об элементе» список задаётся свойством ReturnFields.
     */
    public function returns(string $type, array $props = []): array
    {
        $returns = $this->entry($type)['returns'] ?? [];
        if (!is_string($returns)) {
            return $returns;
        }
        // Список полей задаётся свойством: список — сами значения, отображение — его ключи
        $source = $props[$returns] ?? [];
        if (!is_array($source)) {
            return [];
        }
        return array_map('strval', array_is_list($source) ? $source : array_keys($source));
    }

    public function types(): array
    {
        return array_keys($this->data['activities']);
    }

    public function aliases(): array
    {
        return $this->aliases;
    }

    /**
     * Приводит значения свойств к виду Bitrix: YAML отдаёт Y/N/yes как булевы,
     * числа — как числа, а в шаблонах всё это строки.
     */
    public function normalizeProps(string $type, array $props): array
    {
        $out = [];
        foreach ($props as $prop => $value) {
            $out[$prop] = $this->normalizeValue($this->propType($type, (string) $prop), $value);
        }
        return $out;
    }

    public function normalizeValue(?string $propType, mixed $value): mixed
    {
        if ($propType === 'defs') {
            return is_array($value) ? array_map(
                fn ($item) => is_array($item) ? self::normalizeDefinition($item) : $item,
                $value
            ) : $value;
        }
        return self::normalizeScalars($value);
    }

    /** Описание поля: Required и Multiple в Bitrix — строки «1» и «0». */
    public static function normalizeDefinition(array $definition): array
    {
        $out = [];
        foreach ($definition as $key => $value) {
            if (in_array($key, ['Required', 'Multiple'], true)) {
                $out[$key] = is_bool($value) ? ($value ? '1' : '0') : (is_scalar($value) ? (string) $value : $value);
                continue;
            }
            $out[$key] = in_array($key, ['Options', 'Default'], true) ? $value : self::normalizeScalars($value);
        }
        return $out;
    }

    private static function normalizeScalars(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value ? 'Y' : 'N';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (!is_array($value)) {
            return $value;   // строки и null остаются как есть
        }
        $out = [];
        foreach ($value as $key => $item) {
            $out[$key] = self::normalizeScalars($item);
        }
        return $out;
    }

    /** Таблица каталога для вики и для чтения человеком. */
    public function toMarkdown(): string
    {
        $lines = [
            '| Тип | Алиас | Вложенность | Свойства | Возвращает | В корпусе |',
            '|-----|-------|-------------|----------|------------|-----------|',
        ];
        foreach ($this->data['activities'] as $type => $entry) {
            $props = [];
            foreach ($entry['props'] as $prop => $spec) {
                $mark = ($spec['required'] ?? false) ? '!' : '';
                $default = array_key_exists('default', $spec)
                    ? '=' . (is_array($spec['default']) ? '[]' : (string) $spec['default'])
                    : '';
                $props[] = "`{$prop}`{$mark}{$default}";
            }
            $returns = $entry['returns'] ?? [];
            $returns = is_string($returns) ? "по `{$returns}`" : implode(', ', $returns);
            $title = $entry['title'] . (($entry['title_guess'] ?? false) ? ' *(заголовок под вопросом)*' : '');
            $lines[] = sprintf('| `%s`<br>%s | %s | %s | %s | %s | %d |',
                $type, $title, isset($entry['alias']) ? "`{$entry['alias']}`" : '—',
                $entry['shape'], $props ? implode(', ', $props) : '—', $returns !== '' ? $returns : '—',
                $entry['observed'] ?? 0);
        }
        return implode(PHP_EOL, $lines);
    }

    public function toJson(?string $type = null): string
    {
        $data = $type === null
            ? ['version' => $this->version(), 'verified' => $this->verified(),
               'common' => $this->data['common'], 'activities' => $this->data['activities']]
            : [$type => $this->entry($type)];
        return json_encode($data, BptFile::JSON_OUT);
    }
}
