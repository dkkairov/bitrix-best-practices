<?php
/** Механические проверки вики по CLAUDE.md §4–§6, §9. Каждая возвращает список находок. */

declare(strict_types=1);

class Finding
{
    public function __construct(
        public readonly string $check,
        public readonly string $level,   // error | warn | info
        public readonly string $where,
        public readonly string $message,
    ) {
    }
}

class Checks
{
    public const CORE_FRONTMATTER = ['title', 'type', 'module', 'edition', 'status', 'updated'];
    public const TYPE_PREFIX = [
        'checklist' => 'checklist-',
        'recipe' => 'recipe-',
        'pattern' => 'pattern-',
        'antipattern' => 'antipattern-',
        'concept' => 'concept-',
        'entity' => 'entity-',
        'source-summary' => 'source-',
        'index' => '_index',
    ];
    /** Папки, где страницы лежат плоско (CLAUDE.md §4.5). */
    public const FLAT_DIRS = [
        '#^wiki/(modules|development)/[a-z0-9-]+$#',
        '#^wiki/(glossary|sources)$#',
        '#^wiki/cross-cutting/(playbooks|patterns|antipatterns)$#',
    ];
    /** Имена без префикса-типа — это норма. */
    public const NO_PREFIX = ['sources-backlog'];

    public function __construct(
        private readonly Wiki $wiki,
        private readonly string $today,
        private readonly int $staleDays = 183,
    ) {
    }

    /** @return list<Finding> */
    public function all(): array
    {
        return array_merge(
            $this->brokenLinks(),
            $this->orphans(),
            $this->frontmatter(),
            $this->verified(),
            $this->naming(),
            $this->structure(),
            $this->hubs(),
            $this->aliases(),
            $this->navigator(),
            $this->dates(),
            $this->secrets(),
            $this->coverage(),
        );
    }

    /** §5: каждая [[ссылка]] обязана резолвиться. */
    public function brokenLinks(): array
    {
        $out = [];
        foreach ($this->wiki->pages as $page) {
            foreach (array_unique($page->links) as $link) {
                if ($this->wiki->resolve($link) === null) {
                    $out[] = new Finding('links', 'error', $page->rel, "битая ссылка [[{$link}]]");
                }
            }
        }
        foreach ($this->wiki->service as $file => $links) {
            foreach (array_unique($links) as $link) {
                if ($this->wiki->resolve($link) === null) {
                    $out[] = new Finding('links', 'error', $file, "битая ссылка [[{$link}]]");
                }
            }
        }

        return $out;
    }

    /** §5: у контент-страницы должна быть хотя бы одна входящая ссылка. */
    public function orphans(): array
    {
        $out = [];
        foreach ($this->wiki->pages as $name => $page) {
            if ($page->isHub() || in_array($name, self::NO_PREFIX, true)) {
                continue;
            }
            if (($this->wiki->incoming[$name] ?? 0) === 0) {
                $out[] = new Finding('orphans', 'error', $page->rel, 'нет входящих ссылок');
            }
        }

        return $out;
    }

    /** §4.2: ядро frontmatter обязательно. */
    public function frontmatter(): array
    {
        $out = [];
        foreach ($this->wiki->pages as $page) {
            $missing = [];
            foreach (self::CORE_FRONTMATTER as $key) {
                if ($page->get($key) === '') {
                    $missing[] = $key;
                }
            }
            if ($missing !== []) {
                $out[] = new Finding('frontmatter', 'error', $page->rel, 'нет полей: ' . implode(', ', $missing));
            }

            $edition = $page->get('edition');
            if ($edition !== '' && !in_array($edition, ['cloud', 'box', 'both'], true)) {
                $out[] = new Finding('frontmatter', 'error', $page->rel, "edition = «{$edition}»");
            }
            if ($edition === 'cloud' && str_starts_with($page->dir, 'wiki/development/')) {
                $out[] = new Finding('frontmatter', 'warn', $page->rel, 'страница раздела разработки помечена cloud');
            }

            $status = $page->get('status');
            if ($status !== '' && !in_array($status, ['draft', 'verified', 'deprecated'], true)) {
                $out[] = new Finding('frontmatter', 'error', $page->rel, "status = «{$status}»");
            }
        }

        return $out;
    }

    /** §4.2 и §6: verified обязателен при status: verified и не должен протухать. */
    public function verified(): array
    {
        $out = [];
        $limit = date('Y-m-d', strtotime($this->today . ' -' . $this->staleDays . ' days'));

        foreach ($this->wiki->pages as $page) {
            if ($page->get('status') !== 'verified') {
                continue;
            }
            $raw = $page->get('verified');
            if ($raw === '') {
                $out[] = new Finding('verified', 'warn', $page->rel, 'status: verified, но поле verified пустое');
                continue;
            }
            $date = $page->verifiedDate();
            if ($date === null) {
                $out[] = new Finding('verified', 'warn', $page->rel, 'в verified нет даты');
            } elseif ($date < $limit) {
                $out[] = new Finding('verified', 'warn', $page->rel, "проверено давно: {$date}");
            }
        }

        return $out;
    }

    /** §4.1: имя файла — <тип>-слаг, префикс соответствует type, имя уникально. */
    public function naming(): array
    {
        $out = [];
        foreach ($this->wiki->pages as $name => $page) {
            if (in_array($name, self::NO_PREFIX, true)) {
                continue;
            }
            if (!preg_match('/^[a-z0-9_-]+$/u', $name)) {
                $out[] = new Finding('naming', 'error', $page->rel, 'имя не kebab-case латиницей');
            }
            $type = $page->type();
            $prefix = self::TYPE_PREFIX[$type] ?? null;
            if ($prefix === null) {
                $out[] = new Finding('naming', 'error', $page->rel, "неизвестный type: «{$type}»");
            } elseif (!str_starts_with($name, $prefix)) {
                $out[] = new Finding('naming', 'error', $page->rel, "type: {$type} — ожидался префикс «{$prefix}»");
            }
        }
        foreach ($this->wiki->duplicates as $pair) {
            $out[] = new Finding('naming', 'error', $pair, 'имя файла не уникально');
        }

        return $out;
    }

    /** §4.5: страницы лежат плоско в папке своего раздела. */
    public function structure(): array
    {
        $out = [];
        foreach ($this->wiki->pages as $page) {
            $ok = $page->dir === 'wiki';
            foreach (self::FLAT_DIRS as $pattern) {
                $ok = $ok || preg_match($pattern, $page->dir) === 1;
            }
            if (!$ok) {
                $out[] = new Finding('structure', 'error', $page->rel, "страница лежит в «{$page->dir}»");
            }
        }

        return $out;
    }

    /** §5: хаб папки перечисляет все её страницы. */
    public function hubs(): array
    {
        $out = [];
        foreach ($this->wiki->folders() as $dir) {
            if (in_array($dir, ['wiki', 'wiki/glossary', 'wiki/sources'], true)) {
                continue;
            }
            $pages = $this->wiki->folder($dir);
            $hub = null;
            foreach ($this->wiki->pages as $page) {
                if ($page->dir === $dir && $page->isHub()) {
                    $hub = $page;
                    break;
                }
            }
            if ($hub === null) {
                if ($pages !== []) {
                    $out[] = new Finding('hubs', 'error', $dir, 'нет хаба _index-*');
                }
                continue;
            }
            if ($pages === []) {
                $out[] = new Finding('hubs', 'warn', $hub->rel, 'хаб без страниц');
                continue;
            }
            $missing = [];
            foreach ($pages as $name => $_) {
                if (!str_contains($hub->text, $name)) {
                    $missing[] = $name;
                }
            }
            if ($missing !== []) {
                $out[] = new Finding('hubs', 'error', $hub->rel, 'не перечислены: ' . implode(', ', $missing));
            }
        }

        return $out;
    }

    /** §4.5: alias уникален и не совпадает с именем другой страницы. */
    public function aliases(): array
    {
        $out = [];
        foreach ($this->wiki->aliases as $alias => $owners) {
            if (count($owners) > 1) {
                $out[] = new Finding('aliases', 'error', implode(', ', $owners), "alias «{$alias}» у нескольких страниц");
            }
            if (isset($this->wiki->pages[$alias])) {
                $out[] = new Finding('aliases', 'error', $owners[0], "alias «{$alias}» совпадает с именем страницы");
            }
        }

        return $out;
    }

    /** index.md — навигатор: в нём должна быть каждая контент-страница. */
    public function navigator(): array
    {
        $index = $this->wiki->service['index.md'] ?? null;
        if ($index === null) {
            return [new Finding('navigator', 'warn', 'index.md', 'файл не найден')];
        }
        $text = (string)file_get_contents($this->wiki->root . '/index.md');

        $out = [];
        foreach ($this->wiki->pages as $name => $page) {
            if ($page->isHub() || in_array($name, self::NO_PREFIX, true)) {
                continue;
            }
            if (!str_contains($text, $name)) {
                $out[] = new Finding('navigator', 'error', $page->rel, 'страницы нет в index.md');
            }
        }

        return $out;
    }

    /** Даты не должны спорить друг с другом. */
    public function dates(): array
    {
        $out = [];
        foreach ($this->wiki->pages as $page) {
            $updated = $page->get('updated');
            $verified = $page->verifiedDate();
            if ($updated !== '' && $updated > $this->today) {
                $out[] = new Finding('dates', 'warn', $page->rel, "updated в будущем: {$updated}");
            }
            if ($updated !== '' && $verified !== null && $verified > $updated) {
                $out[] = new Finding('dates', 'warn', $page->rel, "verified {$verified} новее updated {$updated}");
            }
        }

        return $out;
    }

    /** §4.4: секреты и привязки к клиентским порталам в репозиторий не попадают. */
    public function secrets(): array
    {
        $patterns = [
            '#/rest/\d+/[a-z0-9]{8,}#i' => 'похоже на вебхук',
            '#(password|passwd|api_key|secret|token)\s*[:=]\s*[\'"][A-Za-z0-9_\-]{12,}#i' => 'похоже на секрет в коде',
            '#-----BEGIN [A-Z ]*PRIVATE KEY-----#' => 'приватный ключ',
        ];

        $out = [];
        foreach ($this->wiki->pages as $page) {
            foreach ($patterns as $pattern => $what) {
                if (preg_match($pattern, $page->text)) {
                    $out[] = new Finding('secrets', 'error', $page->rel, $what);
                }
            }
        }

        return $out;
    }

    /** Покрытие: сколько страниц в папках (информационно). */
    public function coverage(): array
    {
        $out = [];
        foreach ($this->wiki->folders() as $dir) {
            $count = count($this->wiki->folder($dir));
            if ($count > 0 && $count < 3 && !in_array($dir, ['wiki'], true)) {
                $out[] = new Finding('coverage', 'info', $dir, "страниц: {$count} — раздел почти пустой");
            }
        }

        return $out;
    }
}
