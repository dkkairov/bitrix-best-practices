<?php
/** Одна страница вики: frontmatter, тело, исходящие ссылки. */

declare(strict_types=1);

class Page
{
    /** @var array<string, string> */
    public array $fm = [];
    /** @var list<string> */
    public array $aliases = [];
    /** @var list<string> */
    public array $links = [];

    private function __construct(
        public readonly string $name,
        public readonly string $rel,
        public readonly string $dir,
        public readonly string $text,
        public readonly string $body,
    ) {
    }

    public static function load(string $path, string $root): self
    {
        $text = (string)file_get_contents($path);
        $rel = str_replace('\\', '/', substr($path, strlen($root) + 1));
        $name = basename($path, '.md');

        $body = $text;
        $fm = [];
        if (preg_match('/^---\R(.*?)\R---\R/su', $text, $m)) {
            $body = substr($text, strlen($m[0]));
            foreach (preg_split('/\R/u', $m[1]) ?: [] as $line) {
                if (preg_match('/^([a-zA-Z_]+):\s*(.*)$/u', $line, $kv)) {
                    $fm[$kv[1]] = trim($kv[2]);
                }
            }
        }

        $page = new self($name, $rel, dirname($rel), $text, $body);
        $page->fm = $fm;
        $page->aliases = self::quoted($fm['aliases'] ?? '');
        $page->links = self::links($text);

        return $page;
    }

    /** Значение frontmatter без кавычек. */
    public function get(string $key): string
    {
        return trim($this->fm[$key] ?? '', " \t\"'");
    }

    public function isHub(): bool
    {
        return str_starts_with($this->name, '_index');
    }

    /** Первое слово `type`: по нему строится префикс имени файла. */
    public function type(): string
    {
        $type = $this->get('type');

        return $type === '' ? '' : (preg_split('/\s+/u', $type)[0] ?? '');
    }

    /** Дата из поля `verified` (там дата и описание проверки). */
    public function verifiedDate(): ?string
    {
        return preg_match('/(\d{4}-\d{2}-\d{2})/u', $this->get('verified'), $m) ? $m[1] : null;
    }

    /** Ссылки `[[имя]]` вне блоков кода; подпись после `|` и экранирование `\|` отбрасываются. */
    public static function links(string $text): array
    {
        $clean = preg_replace('/```.*?```/su', '', $text) ?? $text;
        $clean = preg_replace('/`[^`\n]*`/u', '', $clean) ?? $clean;

        $out = [];
        foreach (preg_match_all('/\[\[([^\]\n]+)\]\]/u', $clean, $m) ? $m[1] : [] as $raw) {
            $key = trim(preg_split('/\\\\?\|/u', $raw)[0] ?? '');
            $key = trim(rtrim($key, '\\'));
            if ($key !== '') {
                $out[] = $key;
            }
        }

        return $out;
    }

    /** Строки в кавычках — так записаны aliases, sources и related. */
    private static function quoted(string $value): array
    {
        return preg_match_all('/"([^"]+)"|\'([^\']+)\'/u', $value, $m)
            ? array_values(array_filter(array_map(
                static fn (string $a, string $b): string => $a !== '' ? $a : $b,
                $m[1],
                $m[2]
            )))
            : [];
    }
}
