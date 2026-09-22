<?php
/**
 * Роли задач: название роли → кто это на стенде (группа, отдел, поле-роль проекта, сотрудник).
 * Файл roles.yaml хранит только имена — ID стенда определяет прогонщик на месте.
 */

declare(strict_types=1);

final class Roles
{
    public const KINDS = ['group', 'department', 'project_field', 'user'];

    private function __construct(private readonly array $roles)
    {
    }

    public static function load(string $file): self
    {
        if (!is_file($file)) {
            throw new EvalException("нет файла ролей: {$file}");
        }
        return self::fromArray(SpecReader::read($file), $file);
    }

    public static function fromArray(array $data, string $source): self
    {
        $roles = [];
        $errors = [];
        foreach ($data as $name => $spec) {
            $spec = is_array($spec) ? $spec : [];
            $kinds = array_keys($spec);
            if (count($kinds) !== 1) {
                $errors[] = "{$name}: нужен ровно один вид из " . implode(', ', self::KINDS);
                continue;
            }
            $kind = (string) $kinds[0];
            if (!in_array($kind, self::KINDS, true)) {
                $errors[] = "{$name}: неизвестный вид «{$kind}»; допустимо: " . implode(', ', self::KINDS);
                continue;
            }
            $target = trim((string) $spec[$kind]);
            if ($target === '') {
                $errors[] = "{$name}: пустое значение";
                continue;
            }
            $roles[(string) $name] = ['kind' => $kind, 'target' => $target];
        }
        if ($errors) {
            throw new EvalException("{$source}: " . implode('; ', $errors));
        }
        return new self($roles);
    }

    public function has(string $role): bool { return isset($this->roles[$role]); }
    public function kind(string $role): string { return $this->roles[$role]['kind']; }
    public function target(string $role): string { return $this->roles[$role]['target']; }
    public function names(): array { return array_keys($this->roles); }
    public function toArray(): array { return $this->roles; }

    /** @return string[] роли, которых нет в файле */
    public function missing(array $roles): array
    {
        return array_values(array_filter($roles, fn ($r) => !$this->has((string) $r)));
    }
}
