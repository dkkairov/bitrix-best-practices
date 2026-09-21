---
title: "Каталог php_interface: init.php, dbconn.php и соседи"
type: entity
module: core-d7
edition: box
status: verified
provenance: documented
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Ядро продукта, Страница (порядок выполнения), Свой код"
tags: [php_interface, init-php, dbconn, хуки, конфигурация]
sources: ["[[source-devbook-dev-rules]]", "[[source-devbook-core-d7]]"]
related: ["[[entity-local-directory]]", "[[concept-request-lifecycle]]", "[[recipe-d7-orm-event-subscription]]", "[[pattern-events-over-core-modification]]", "[[pattern-local-solution-structure]]"]
aliases: ["bitrix24-php-interface"]
updated: "2026-09-21"
---

# Каталог `php_interface`

**Что это:** служебный каталог точек расширения платформы. Существует в `/bitrix/` (ядро, трогать
нельзя) и в [[entity-local-directory|`/local/`]] (наш). **Приоритет `/local/` подтверждён только для
общего `init.php`:** на шаге 5 пайплайна подключается `/local/php_interface/init.php` или, если его
нет, `/bitrix/php_interface/init.php`; остальные файлы таблицы книга подключает только из
`/bitrix/php_interface/` ([Страница](https://bx24devbook.website.yandexcloud.net/Obsie_svedenia/Stranica.html#poradok-vypolnenia)).

> **Уточнено 2026-09-21 при сверке с книгой:** раньше страница говорила о приоритете всего
> `/local/php_interface/`; убрано утверждение, что `after_connect_d7.php` — «D7-вариант»
> `after_connect.php` (в книге его нет); уточнена роль `dbconn.php`.

## Состав

| Файл | Где | Когда подключается / зачем |
|---|---|---|
| `dbconn.php` | `/bitrix/` | **первым** в пайплайне: константы окружения, лимиты выполнения; раньше здесь задавались и переменные подключения к БД, теперь доступ к БД — в `/bitrix/.settings.php` |
| `dbconn_error.php` | `/bitrix/` | обработчик ошибки соединения с БД |
| `dbquery_error.php` | `/bitrix/` | обработчик ошибки SQL-запроса |
| `after_connect.php`, `after_connect_d7.php` | `/bitrix/` | сразу после подключения к БД (каждый — при наличии) |
| **`init.php`** | `/local/` или `/bitrix/` | общая инициализация; точка входа проекта |
| `<ID сайта>/init.php` | `/bitrix/` | посайтная инициализация, после определения `SITE_ID` |

Порядок и номера шагов — [[concept-request-lifecycle]]. Что класть в `/local/php_interface/` рядом с
`init.php` (`events.php`, `kernel.php`, `classes/`, `install/`…) — [[pattern-local-solution-structure]].

## Почему `init.php` так важен

Он подключается **до** `OnPageStart` и до авторизации пользователя. Отсюда два следствия:

1. это правильная точка для подписок на события — сами подписки книга выносит в `events.php`,
   подключаемый из `init.php` ([[pattern-events-over-core-modification]]);
2. **`$USER` там ещё не существует** — код, рассчитывающий на текущего пользователя, получит
   пустоту.

Модули вписывают в `init.php` свою загрузку — по маркерам, чтобы корректно снять при удалении
([[recipe-d7-orm-event-subscription]]).

## Подводные камни

- **`init.php` — общий файл портала**, его правят и люди, и модули. Перед заливкой делайте
  резервную копию и проверяйте линтом после ([[recipe-safe-module-deploy]]): синтаксическая
  ошибка здесь роняет весь портал.
- **Приоритет `/local/` подтверждён только для общего `init.php`.** Для посайтного
  (`<ID сайта>/init.php`) книга указывает только путь в `/bitrix/` — проверяйте на своей версии.
- **`/bitrix/php_interface/` формально запрещён к правке**, хотя описан как штатная точка
  расширения. Резолюция противоречия — в [[concept-change-invasiveness-hierarchy]]: назначение
  каталога штатное, правильная реализация — параллельный `/local/php_interface/`. Исключение по
  факту — `dbconn.php`: аналога в `/local/` у него нет, это файл конкретной среды (вне git).
- Логика не должна жить в `init.php` — только автозагрузчик и подключение файлов
  ([Свой код → init.php](https://bx24devbook.website.yandexcloud.net/Razrabotka/Struktura_papki_local/Svoj_kod.html#init-php)).
  Иначе файл превращается в свалку, которую боятся трогать.

## Открытые вопросы
- Чем `after_connect_d7.php` отличается от `after_connect.php` (книга только перечисляет оба).

## Связанное
- [[entity-local-directory]] — где должен лежать наш экземпляр
- [[concept-request-lifecycle]] — точные шаги подключения

[← Ядро D7](_index-core-d7.md)
