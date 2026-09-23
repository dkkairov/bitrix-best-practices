<?php
/**
 * Константы-пользователи шаблона → роли задачи. Агент может вынести роль в константу; прогонщик
 * должен её заполнить. Совпадение — по вхождению названия роли в название константы, а если там
 * роли нет — в описание (регистр, «ё» и знаки препинания не важны); при нескольких совпадениях —
 * самое длинное название роли.
 *
 * Константа с уже заполненным значением по умолчанию (`Default` не пуст) в сопоставлении не
 * нуждается: агент мог сам подставить конкретного сотрудника (например, из снимка портала) — шаблон
 * с этим значением рабочий как есть, поэтому такая константа в `unmatched` не попадает, даже если
 * роль для неё не нашлась. Если роль всё же нашлась — константа идёт в `matched` независимо от
 * того, был ли уже заполнен `Default`: прогонщик подставляет роль поверх, чтобы сценарии шли к
 * нужному сотруднику именно этого прогона, а не к тому, кто был на портале в момент снимка.
 */

declare(strict_types=1);

final class ConstantMatcher
{
    /** @return array{matched: array<string, string>, unmatched: string[]} */
    public static function match(array $constants, Roles $roles): array
    {
        $matched = [];
        $unmatched = [];
        foreach ($constants as $code => $constant) {
            if (($constant['Type'] ?? '') !== 'user') {
                continue;
            }
            $best = self::bestRole((string) ($constant['Name'] ?? ''), $roles)
                ?? self::bestRole((string) ($constant['Description'] ?? ''), $roles);
            if ($best !== null) {
                $matched[(string) $code] = $best;
            } elseif (!self::hasDefault($constant)) {
                $unmatched[] = (string) $code;
            }
            // иначе: роль не нашлась, но Default уже заполнен — сопоставление не требуется
        }
        return ['matched' => $matched, 'unmatched' => $unmatched];
    }

    /** Есть ли у константы непустое значение по умолчанию (строка или список — Multiple). */
    private static function hasDefault(array $constant): bool
    {
        $default = $constant['Default'] ?? null;
        if (is_array($default)) {
            foreach ($default as $v) {
                if (trim((string) $v) !== '') {
                    return true;
                }
            }
            return false;
        }
        return trim((string) $default) !== '';
    }

    private static function bestRole(string $text, Roles $roles): ?string
    {
        $haystack = self::norm($text);
        $best = null;
        foreach ($roles->names() as $role) {
            if ($haystack !== '' && str_contains($haystack, self::norm($role))
                && ($best === null || mb_strlen($role) > mb_strlen($best))) {
                $best = $role;
            }
        }
        return $best;
    }

    private static function norm(string $s): string
    {
        $s = str_replace('ё', 'е', mb_strtolower($s));
        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s));
    }
}
