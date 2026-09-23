<?php
/** Отчёт о находках: текст для человека или JSON для машины. */

declare(strict_types=1);

class Report
{
    public const LEVELS = ['error' => '🔴', 'warn' => '🟡', 'info' => '🔵'];

    /** @param list<Finding> $findings */
    public function __construct(
        private readonly array $findings,
        private readonly Wiki $wiki,
    ) {
    }

    public function hasErrors(): bool
    {
        foreach ($this->findings as $f) {
            if ($f->level === 'error') {
                return true;
            }
        }

        return false;
    }

    public function text(): string
    {
        $lines = [$this->stats(), ''];

        foreach (array_keys(self::LEVELS) as $level) {
            $group = array_values(array_filter($this->findings, static fn (Finding $f): bool => $f->level === $level));
            if ($group === []) {
                continue;
            }
            $lines[] = self::LEVELS[$level] . ' ' . self::title($level) . ' — ' . count($group);
            foreach ($this->byCheck($group) as $check => $items) {
                $lines[] = "  [{$check}]";
                foreach ($items as $f) {
                    $lines[] = "    {$f->where}: {$f->message}";
                }
            }
            $lines[] = '';
        }

        if ($this->findings === []) {
            $lines[] = 'Проверки пройдены: замечаний нет.';
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    public function json(): string
    {
        $items = array_map(
            static fn (Finding $f): array => [
                'check' => $f->check,
                'level' => $f->level,
                'where' => $f->where,
                'message' => $f->message,
            ],
            $this->findings
        );

        return json_encode(
            ['stats' => $this->statsArray(), 'findings' => $items],
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        ) . PHP_EOL;
    }

    private function stats(): string
    {
        $s = $this->statsArray();

        return "Страниц: {$s['pages']} (контентных {$s['content']}, хабов {$s['hubs']}); "
            . "verified {$s['verified']}, draft {$s['draft']}, deprecated {$s['deprecated']}; "
            . "папок {$s['folders']}.";
    }

    /** @return array<string, int> */
    private function statsArray(): array
    {
        $hubs = $content = $verified = $draft = $deprecated = 0;
        foreach ($this->wiki->pages as $page) {
            $page->isHub() ? $hubs++ : $content++;
            match ($page->get('status')) {
                'verified' => $verified++,
                'draft' => $draft++,
                'deprecated' => $deprecated++,
                default => null,
            };
        }

        return [
            'pages' => count($this->wiki->pages),
            'content' => $content,
            'hubs' => $hubs,
            'verified' => $verified,
            'draft' => $draft,
            'deprecated' => $deprecated,
            'folders' => count($this->wiki->folders()),
            'findings' => count($this->findings),
        ];
    }

    /** @param list<Finding> $group */
    private function byCheck(array $group): array
    {
        $out = [];
        foreach ($group as $f) {
            $out[$f->check][] = $f;
        }
        ksort($out);

        return $out;
    }

    private static function title(string $level): string
    {
        return match ($level) {
            'error' => 'Ошибки схемы',
            'warn' => 'Требует внимания',
            default => 'К сведению',
        };
    }
}
