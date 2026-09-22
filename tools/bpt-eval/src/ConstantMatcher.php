<?php
/**
 * Константы-пользователи шаблона → роли задачи. Агент может вынести роль в константу; прогонщик
 * должен её заполнить. Совпадение — по вхождению названия роли в название константы, а если там
 * роли нет — в описание (регистр, «ё» и знаки препинания не важны); при нескольких совпадениях —
 * самое длинное название роли.
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
            if ($best === null) {
                $unmatched[] = (string) $code;
            } else {
                $matched[(string) $code] = $best;
            }
        }
        return ['matched' => $matched, 'unmatched' => $unmatched];
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
