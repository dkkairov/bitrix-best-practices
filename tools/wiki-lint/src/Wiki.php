<?php
/** Загруженная вики: страницы, индексы имён и алиасов, входящие ссылки. */

declare(strict_types=1);

class Wiki
{
    /** @var array<string, Page> имя файла без .md => страница */
    public array $pages = [];
    /** @var array<string, list<string>> alias => имена страниц */
    public array $aliases = [];
    /** @var array<string, int> имя страницы => сколько ссылок на неё */
    public array $incoming = [];
    /** @var array<string, list<string>> путь служебного файла => его ссылки */
    public array $service = [];
    /** @var list<string> пути файлов с одинаковым именем */
    public array $duplicates = [];

    public function __construct(public readonly string $root)
    {
    }

    public static function load(string $root): self
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $wiki = new self($root);

        $seen = [];
        foreach (self::markdown($root . '/wiki') as $path) {
            if (str_contains(str_replace('\\', '/', $path), '/wiki/_templates/')) {
                continue;
            }
            $page = Page::load($path, $root);
            if (isset($seen[$page->name])) {
                $wiki->duplicates[] = $seen[$page->name] . ' / ' . $page->rel;
            }
            $seen[$page->name] = $page->rel;
            $wiki->pages[$page->name] = $page;
            foreach ($page->aliases as $alias) {
                $wiki->aliases[$alias][] = $page->name;
            }
        }

        // навигатор и журнал тоже дают входящие ссылки
        foreach (['index.md', 'log.md', 'README.md'] as $file) {
            if (is_file($root . '/' . $file)) {
                $wiki->service[$file] = Page::links((string)file_get_contents($root . '/' . $file));
            }
        }

        $wiki->countIncoming();

        return $wiki;
    }

    /** Имя, на которое указывает ссылка: сама страница или её alias. */
    public function resolve(string $link): ?string
    {
        if (isset($this->pages[$link])) {
            return $link;
        }

        return $this->aliases[$link][0] ?? null;
    }

    /** Страницы папки, кроме хаба. */
    public function folder(string $dir): array
    {
        return array_filter(
            $this->pages,
            static fn (Page $p): bool => $p->dir === $dir && !$p->isHub()
        );
    }

    /** @return list<string> папки с контентом */
    public function folders(): array
    {
        $dirs = [];
        foreach ($this->pages as $page) {
            $dirs[$page->dir] = true;
        }
        ksort($dirs);

        return array_keys($dirs);
    }

    private function countIncoming(): void
    {
        foreach ($this->pages as $name => $_) {
            $this->incoming[$name] = 0;
        }
        foreach ($this->pages as $page) {
            foreach (array_unique($page->links) as $link) {
                $target = $this->resolve($link);
                if ($target !== null && $target !== $page->name) {
                    $this->incoming[$target]++;
                }
            }
        }
        foreach ($this->service as $links) {
            foreach (array_unique($links) as $link) {
                $target = $this->resolve($link);
                if ($target !== null) {
                    $this->incoming[$target]++;
                }
            }
        }
    }

    /** @return list<string> */
    private static function markdown(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $out = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            /** @var SplFileInfo $file */
            if ($file->isFile() && $file->getExtension() === 'md') {
                $out[] = str_replace('\\', '/', $file->getPathname());
            }
        }
        sort($out);

        return $out;
    }
}
