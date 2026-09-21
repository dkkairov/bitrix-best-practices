<?php
/**
 * Схема процесса в Mermaid: человеку смотреть логику удобнее, чем YAML.
 * Формы узлов зависят от формы вложенности действия в каталоге.
 */

declare(strict_types=1);

final class Mermaid
{
    private const MAX_LABEL = 60;

    private int $counter = 0;
    private array $lines = [];

    public function __construct(private readonly Catalog $catalog)
    {
    }

    /** Возвращает markdown с блоком mermaid. */
    public function render(array $bpt): string
    {
        $this->counter = 0;
        $this->lines = [];

        $root = $bpt['TEMPLATE'][0];
        $start = $this->id();
        $this->lines[] = "{$start}([\"Старт\"])";
        $end = $this->flow($root['Children'] ?? [], $start, 0);
        $finish = $this->id();
        $this->lines[] = "{$finish}([\"Конец\"])";
        $this->connect($end, $finish);

        return "```mermaid\nflowchart TD\n    " . implode("\n    ", $this->lines)
            . "\n    classDef off stroke-dasharray: 4 3, color:#888\n```\n";
    }

    /** Строит цепочку шагов; возвращает узлы-выходы для связи со следующим шагом. */
    private function flow(array $steps, string|array $from, int $depth): string|array
    {
        $current = $from;
        foreach (array_values(array_filter($steps, 'is_array')) as $node) {
            $current = $this->step($node, $current, $depth);
        }
        return $current;
    }

    private function step(array $node, string|array $from, int $depth): string|array
    {
        $type = (string) $node['Type'];
        $shape = $this->catalog->has($type) ? $this->catalog->shape($type) : 'leaf';
        $title = $this->label((string) ($node['Properties']['Title'] ?? $type));
        $off = ($node['Activated'] ?? 'Y') === 'N';
        $id = $this->id();

        switch ($shape) {
            case 'ifelse':
                $this->lines[] = "{$id}{\"{$title}\"}";
                $this->connect($from, $id, $off);
                $exits = [];
                foreach (array_values(array_filter($node['Children'] ?? [], 'is_array')) as $branch) {
                    $branchLabel = $this->label((string) ($branch['Properties']['Title'] ?? 'ветка'));
                    $exits[] = $this->flow($branch['Children'] ?? [], [$id, $branchLabel], $depth + 1);
                }
                return $this->flatten($exits, $id);

            case 'parallel':
                $this->lines[] = "{$id}{{\"{$title}\"}}";
                $this->connect($from, $id, $off);
                $exits = [];
                foreach (array_values(array_filter($node['Children'] ?? [], 'is_array')) as $sequence) {
                    $exits[] = $this->flow($sequence['Children'] ?? [], $id, $depth + 1);
                }
                $join = $this->id();
                $this->lines[] = "{$join}{{\"Слияние\"}}";
                foreach ($this->flattenList($exits, $id) as $exit) {
                    $this->connect($exit, $join);
                }
                return $join;

            case 'loop':
                $this->lines[] = "{$id}{\"Цикл: {$title}\"}";
                $this->connect($from, $id, $off);
                $inner = $this->flow($node['Children'][0]['Children'] ?? [], $id, $depth + 1);
                foreach ($this->flattenList([$inner], $id) as $exit) {
                    $this->connect($exit, $id, false, 'повтор');
                }
                return $id;

            case 'block':
                $this->lines[] = "subgraph {$id}[\"{$title}\"]";
                $inner = $this->flow($node['Children'][0]['Children'] ?? [], $from, $depth + 1);
                $this->lines[] = 'end';
                return $inner === $from ? $from : $inner;

            case 'waiting-branches':
                $this->lines[] = "{$id}[/\"{$title}\"/]";
                $this->connect($from, $id, $off);
                $children = array_values(array_filter($node['Children'] ?? [], 'is_array'));
                $yes = $this->flow($children[0]['Children'] ?? [], [$id, 'да'], $depth + 1);
                $no = $this->flow($children[1]['Children'] ?? [], [$id, 'нет'], $depth + 1);
                return $this->flatten([$yes, $no], $id);

            case 'waiting':
                $this->lines[] = "{$id}[/\"{$title}\"/]";
                $this->connect($from, $id, $off);
                return $id;

            default:
                $this->lines[] = "{$id}[\"{$title}\"]";
                $this->connect($from, $id, $off);
                if ($off) {
                    $this->lines[] = "class {$id} off";
                }
                return $id;
        }
    }

    /** @param string|array $from узел или пара [узел, подпись стрелки] */
    private function connect(string|array $from, string $to, bool $dashed = false, string $label = ''): void
    {
        foreach ($this->flattenList([$from], null) as $source) {
            $edgeLabel = $label;
            if (is_array($source)) {
                [$source, $edgeLabel] = $source;
            }
            $arrow = $dashed ? '-.->' : '-->';
            $this->lines[] = $edgeLabel !== ''
                ? "{$source} {$arrow}|{$edgeLabel}| {$to}"
                : "{$source} {$arrow} {$to}";
        }
    }

    /** Ветки, которые ничего не содержат, возвращают сам узел ветвления. */
    private function flatten(array $exits, string $fallback): array
    {
        $list = $this->flattenList($exits, $fallback);
        return $list ?: [$fallback];
    }

    private function flattenList(array $values, ?string $fallback): array
    {
        $out = [];
        foreach ($values as $value) {
            if (is_string($value)) {
                $out[] = $value;
            } elseif (is_array($value) && count($value) === 2 && is_string($value[0] ?? null) && is_string($value[1] ?? null)) {
                $out[] = $value;   // пара [узел, подпись]
            } elseif (is_array($value)) {
                $out = array_merge($out, $this->flattenList($value, $fallback));
            }
        }
        return $out;
    }

    private function id(): string
    {
        return 'n' . (++$this->counter);
    }

    private function label(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if (mb_strlen($text) > self::MAX_LABEL) {
            $text = mb_substr($text, 0, self::MAX_LABEL - 1) . '…';
        }
        return str_replace(['"', '[', ']', '{', '}', '|'], ['«', '(', ')', '(', ')', '/'], $text);
    }
}
