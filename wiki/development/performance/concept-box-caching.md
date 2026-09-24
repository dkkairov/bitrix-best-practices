---
title: "Кэш коробки: виды, классы, движки"
type: concept
module: performance
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / документация фреймворка (docs.1c-bitrix.ru, «Виды кеша») + коробка в Docker, main 26.750.0: состав движков в Cache::createCacheEngine, методы ManagedCache и TaggedCache, движок по умолчанию, момент записи управляемого кэша"
tags: [кэш, производительность, managed-cache, теги, redis, composite]
sources: []
related: ["[[recipe-cache-with-tags]]", "[[concept-composite-site]]", "[[checklist-box-performance]]", "[[entity-main-application]]", "[[recipe-cli-script-bootstrap]]"]
aliases: []
updated: "2026-09-24"
---

# Кэш коробки: виды, классы, движки

**TL;DR:** в коробке четыре независимых механизма кэша. Неуправляемый (`/bitrix/cache/`) живёт по
таймауту, управляемый (`/bitrix/managed_cache/`) сбрасывается при изменении данных, теги связывают
кэш с сущностями, композит отдаёт готовый HTML мимо PHP. Все берутся у объекта приложения, а не
создаются напрямую.

## Четыре механизма

| Механизм | Где лежит | Когда устаревает | Кто типично использует |
|---|---|---|---|
| **Неуправляемый** | `/bitrix/cache/` | по таймауту (`initCache($ttl, …)`) | свой код, кэш выборок и вёрстки |
| **Управляемый** | `/bitrix/managed_cache/` | по изменению данных — ядро само чистит | модули ядра, справочники |
| **Теги** | таблица тегов + каталог кэша | `clearByTag()` по имени сущности | кэш, зависящий от нескольких таблиц |
| **Композит** | статический HTML для веб-сервера | сброс страницы целиком | публичная часть сайта ([[concept-composite-site]]) |

Отдельно — **автокэширование компонентов**: штатные компоненты кэшируют вывод сами, управляется
параметром `CACHE_TYPE` и настройкой автокэша в админке.

## Как получать объекты кэша

```php
$app = \Bitrix\Main\Application::getInstance();

$cache   = $app->getCache();         // \Bitrix\Main\Data\Cache
$managed = $app->getManagedCache();  // \Bitrix\Main\Data\ManagedCache
$tagged  = $app->getTaggedCache();   // \Bitrix\Main\Data\TaggedCache
```

`Cache::createInstance()` тоже рабочий путь и даёт новый объект; управляемый и тегированный кэш
берём только у приложения — они общие на хит ([[entity-main-application]]).

| Класс | Методы |
|---|---|
| `Data\Cache` | `initCache($ttl, $id, $dir)`, `getVars()`, `startDataCache()`, `endDataCache($data)`, `abortDataCache()`, `clean($id, $dir)`, `cleanDir($dir)` |
| `Data\ManagedCache` | `read($ttl, $id, $tableId)`, `get($id)`, `set($id, $val)`, `setImmediate($id, $val)`, `getImmediate(...)`, `clean($id, $tableId)`, `cleanDir($tableId)`, `cleanAll()` |
| `Data\TaggedCache` | `startTagCache($dir)`, `registerTag($tag)`, `endTagCache()`, `abortTagCache()`, `clearByTag($tag)`, `deleteAllTags()` |

Применение — [[recipe-cache-with-tags]].

## Движки хранения

Движок задаёт секция `cache` в `/bitrix/.settings.php`:

```php
'cache' => ['value' => [
    'type' => 'redis',          // files | redis | memcached | memcache | apc | apcu
    'sid'  => '<префикс>',      // разделяет кэш нескольких сайтов на одном хранилище
    'use_lock' => true,         // блокирующий режим (файловый движок)
]],
```

- **По умолчанию — файловый кэш**: если `type` не задан, ядро берёт `files` (проверено на стенде,
  `Cache::createCacheEngine()` вернул `CacheEngineFiles`).
- **Свой движок** подключается массивом вместо строки: `['class_name' => …, 'extension' => …,
  'required_file' => …]`.
- **Неизвестный тип молча не падает**: ядро подставит `CacheEngineNone` и выдаст
  `E_USER_WARNING` «Cache engine is not found». То же при недоступном хранилище — `is not available`.
  Отсюда правило: после смены `type` проверяем лог ошибок, иначе сайт работает без кэша.
- `root_directory` (с `main` 24.100.0) уводит файловый кэш из корня сайта; `use_lock` (с 24.0.0)
  включает блокировку — при одновременных запросах кэш пересобирает один процесс, остальные ждут.
- `cache_flags` задаёт TTL по таблицам управляемого кэша.

> **Расхождение с документацией (2026-09-24).** Раздел «Виды кеша» называет среди механизмов
> `xcache`. В ядре 26.750.0 этого варианта в `Cache::createCacheEngine()` уже нет — остались
> `redis`, `memcached`, `memcache`, `apc`/`apcu`, `files`. Заодно в `main/lib/Data/` лежит
> `CacheEngineValKeyLight`, которого нет ни в документации, ни в switch: подключается только как
> свой класс через `class_name`.

## Подводные камни

- **Управляемый кэш пишется не сразу.** `ManagedCache::set()` кладёт значение в память, на диск оно
  уходит в `Application::terminate()` (его зовёт `end()`). Консольный скрипт, который завершился сам,
  управляемый кэш не сохранит — см. [[recipe-cli-script-bootstrap]]. Нужно сразу — `setImmediate()`.
- **`set()` без `read()` не делает ничего** — подробности и порядок вызовов в [[recipe-cache-with-tags]].
- **Руками файлы `/bitrix/managed_cache/` не удаляем** — ядро ведёт учёт записей; чистим через
  `cleanDir()`/`cleanAll()` или админку.
- **`sid` обязателен на общем хранилище.** Несколько сайтов или стендов на одном Redis без разных
  `sid` читают чужой кэш.
- **Кэш не спасает медленный запрос, он его прячет.** Сначала разбираем запрос
  ([[recipe-slow-query-diagnostics]]), потом кэшируем.

## Связанные страницы
- [[recipe-cache-with-tags]] — кэш выборки с тегами и инвалидацией
- [[concept-composite-site]] — композитный сайт
- [[recipe-slow-query-diagnostics]] — поиск медленных запросов
- [[checklist-box-performance]] — чек-лист производительности
- [[concept-box-scaling]] — кластер и шардинг

[← Производительность](_index-performance.md)
