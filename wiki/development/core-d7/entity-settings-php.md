---
title: ".settings.php и Config\\Configuration — конфигурация ядра"
type: entity
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / коробка в Docker, main 26.750.0: у Config\\Configuration есть getValue, setValue, getInstance, add, addReadonly, saveConfiguration, wnc; на стенде в конфигурации секции cache_flags, connections, cookies, crypto, exception_handling, messenger, pull, stafftrack; файлов .settings_extra.php нет ни в /bitrix, ни в /local; текст — документация фреймворка (docs.1c-bitrix.ru, «Конфигурация ядра»)"
tags: [конфигурация, settings, ядро, readonly, секции]
sources: []
related: ["[[entity-config-option]]", "[[entity-php-interface]]", "[[concept-box-caching]]", "[[concept-d7-logging]]", "[[concept-routing]]"]
aliases: []
updated: "2026-09-24"
---

# `.settings.php` и `Config\Configuration` — конфигурация ядра

**Что это:** главный конфигурационный файл D7 — `/bitrix/.settings.php`. Массив секций, у каждой
`value` и флаг `readonly`. Всё, что настраивается «на уровне площадки», живёт здесь: база, кэш,
сессии, логгеры, маршруты, шифрование.

**Не путать с [[entity-config-option|`Config\Option`]]:** `Option` — настройки модулей в базе,
меняются из админки; `.settings.php` — инфраструктура, которая должна читаться до всякой базы.

## Формат

```php
return [
    'cache' => [
        'value' => ['type' => 'redis', 'sid' => '<префикс>'],
        'readonly' => false,
    ],
    'connections' => [
        'value' => [/* параметры БД */],
        'readonly' => true,
    ],
];
```

`readonly => true` означает «после инициализации не менять»: так защищены подключение к базе и
криптография. `false` оставляют там, где настройку меняют на ходу — обработка ошибок, HTTP-клиент.

## Секции

| Секция | Про что |
|---|---|
| `connections` | подключение к БД — обязательная |
| `cache`, `cache_flags` | механизм кэша и TTL таблиц ([[concept-box-caching]]) |
| `session` | режим и хранилище сессий ([[concept-split-session-modes]]) |
| `loggers` | PSR-3-логгеры ([[concept-d7-logging]]) |
| `routing` | файлы маршрутов ([[concept-routing]]) |
| `exception_handling` | `debug`, `handled_errors_types`, `exception_errors_types`, `log` |
| `crypto` | ключи шифрования (`crypto_key`) |
| `smtp` | SMTP-подключения (с `main` 21.900.0) |
| `pull` | push-сервер |
| `http_client_options` | параметры HTTP-клиента ([[recipe-http-client]]) |
| `services` | сервисы контейнера ([[concept-service-locator]]) |
| `messenger` | очереди ([[concept-messenger-queues]]) |
| `default_language` | язык по умолчанию |

На нашем стенде реально заданы только `cache_flags`, `connections`, `cookies`, `crypto`,
`exception_handling`, `messenger`, `pull`, `stafftrack` — остальные секции работают на значениях по
умолчанию, и это нормальное состояние свежей коробки.

## API

```php
use Bitrix\Main\Config\Configuration;

$cache = Configuration::getValue('cache');            // чтение секции

$config = Configuration::getInstance();
$config->add('http_client_options', ['socketTimeout' => 5]);
$config->addReadonly('crypto', [...]);
$config->saveConfiguration();
```

`setValue()` пишет и сохраняет сразу; `wnc()` создаёт файл заново — то есть **перезаписывает всё**,
вызывать его в рабочем проекте нельзя.

## Куда писать свои настройки

- **`.settings_extra.php`** рядом с основным файлом: ядро объединяет их, а `.settings.php` остаётся
  вендорским. На стенде такого файла нет — его заводят по необходимости.
- С `main` 24.100.0 конфиги поддерживаются и в **`/local/`** — тогда настройки проекта лежат вместе
  с остальным кодом проекта ([[pattern-local-solution-structure]]).
- Старое ядро продолжает читать `/bitrix/php_interface/dbconn.php` ([[entity-php-interface]]).

## Ловушки

- **Секреты.** `connections` и `crypto` содержат пароль базы и ключ шифрования: файл не попадает в
  git, а в вики и в примеры переносится только со заглушками.
- **`readonly` не запрещает править файл руками** — он запрещает ядру менять секцию в рантайме.
- **Ошибка синтаксиса в `.settings.php` кладёт портал целиком** — правим с копией под рукой.
- **`debug => true` на проде** показывает пути и запросы пользователям; включаем точечно
  ([[recipe-box-debugging]]).

## Связанные страницы
- [[entity-config-option]] — настройки модулей в базе
- [[entity-php-interface]] — `dbconn.php` и соседи
- [[recipe-box-debugging]] — `exception_handling` на практике

[← Ядро D7](_index-core-d7.md)
