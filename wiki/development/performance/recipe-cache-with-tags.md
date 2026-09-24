---
title: "Кэшировать выборку с тегами и сбрасывать по изменению"
type: recipe
module: performance
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / коробка в Docker, main 26.750.0: прогон скрипта — сборка, чтение из кэша, сброс по тегу, порядок read/set управляемого кэша, момент записи; текст — документация фреймворка (docs.1c-bitrix.ru, «Виды кеша»)"
tags: [кэш, теги, managed-cache, инвалидация, производительность]
sources: []
related: ["[[concept-box-caching]]", "[[concept-d7-orm-query]]", "[[concept-orm-datamanager-events]]", "[[recipe-cli-script-bootstrap]]"]
aliases: []
updated: "2026-09-24"
---

# Кэшировать выборку с тегами и сбрасывать по изменению

**Результат:** тяжёлая выборка считается один раз, а не по таймауту: кэш живёт, пока не изменились
данные, от которых он зависит.

**Когда применять:** список, агрегат или дерево, которое собирается несколькими запросами и меняется
редко. Для простого «посчитать раз в час» хватает обычного кэша без тегов.

## Шаг 1. Кэш с регистрацией тега

```php
use Bitrix\Main\Application;
use Bitrix\Main\Data\Cache;

function getTopBooks(): array
{
    $cache = Cache::createInstance();
    $dir   = '/vendor/books';
    $key   = 'top_books';

    if ($cache->initCache(86400, $key, $dir))
    {
        return $cache->getVars();
    }

    if ($cache->startDataCache())
    {
        $tagged = Application::getInstance()->getTaggedCache();
        $tagged->startTagCache($dir);
        $tagged->registerTag('vendor_book_list');   // имя тега придумываем сами
        $tagged->endTagCache();

        $data = BookTable::getList([...])->fetchAll();

        $cache->endDataCache($data);
        return $data;
    }

    return [];
}
```

- `initCache()` вернул `true` — данные есть, берём `getVars()` и уходим.
- Блок `startTagCache` / `registerTag` / `endTagCache` обязателен **внутри** сборки: тег
  привязывается к каталогу `$dir`.
- Что-то пошло не так во время сборки — `abortDataCache()`, иначе в кэш попадёт мусор.

## Шаг 2. Сброс по изменению данных

```php
Application::getInstance()->getTaggedCache()->clearByTag('vendor_book_list');
```

Вызов вешаем на события сущности — `OnAfterAdd`, `OnAfterUpdate`, `OnAfterDelete`
([[concept-orm-datamanager-events]]). Тогда кэш живёт сутки, но обновляется в ту же секунду, когда
кто-то тронул книгу.

## Шаг 3. Управляемый кэш — когда сущность своя

Для справочников удобнее `ManagedCache`: ядро само чистит его по имени таблицы.

```php
$mc = Application::getInstance()->getManagedCache();

if ($mc->read(86400, 'top_books', BookTable::getTableName()))
{
    return $mc->get('top_books');
}

$data = BookTable::getList([...])->fetchAll();
$mc->set('top_books', $data);      // порядок важен: см. ниже
return $data;
```

## Проверено на стенде (main 26.750.0)

| Что проверяли | Результат |
|---|---|
| первый вызов, кэша нет | собрано заново, данные записаны |
| второй вызов | отдано из кэша, те же данные |
| `clearByTag()` и третий вызов | собрано заново — тег работает |
| `set()` **без** предшествующего `read()` | ничего не записано, `get()` вернул `false` |
| `read()` → `set()` → `get()` в одном хите | значение отдаётся из памяти |
| два запуска консольного скрипта подряд | во втором `read()` снова `false` |
| движок при пустой секции `cache` | `CacheEngineFiles` |

## Подводные камни

- **`set()` работает только после `read()`.** В ядре `ManagedCache::set()` присваивает значение,
  лишь если для этого ключа уже был `read()`; `setImmediate()` при том же условии просто выходит.
  Без чтения запись молча теряется — именно это и показал стенд.
- **Запись откладывается до конца хита.** Управляемый кэш уходит на диск в
  `Application::terminate()`. Консольный скрипт, который завершается сам, ничего не сохранит —
  завершаем ядро штатно ([[recipe-cli-script-bootstrap]]) или пишем через `setImmediate()`.
- **Тег без `endTagCache()` не сохранится** — регистрация тега закрывается явно.
- **Каталог кэша — часть ключа.** `clearByTag()` чистит каталоги, где тег был зарегистрирован;
  разложили один набор данных по разным каталогам — тег придётся регистрировать в каждом.
- **Не кэшируем персональные данные в общий каталог.** Ключ должен включать то, от чего зависит
  выборка: язык, сайт, группу пользователя.

## Связанные страницы
- [[concept-box-caching]] — виды кэша, движки, настройки
- [[concept-orm-datamanager-events]] — куда вешать сброс тега
- [[concept-d7-orm-query]] — сама выборка

[← Производительность](_index-performance.md)
