---
title: "Каталог php_interface: init.php, dbconn.php и соседи"
type: entity
module: core-d7
edition: box
status: verified
provenance: documented
verified: "2026-06-01 / документация Bitrix Framework (dev.1c-bitrix.ru)"
tags: [php_interface, init-php, dbconn, хуки, конфигурация]
sources: []
related: ["[[entity-local-directory]]", "[[concept-request-lifecycle]]", "[[recipe-d7-orm-event-subscription]]", "[[pattern-events-over-core-modification]]"]
aliases: ["bitrix24-php-interface"]
updated: "2026-09-18"
---

# Каталог `php_interface`

**Что это:** служебный каталог точек расширения платформы. Существует в двух экземплярах —
в `/bitrix/` (ядро, трогать нельзя) и в [[entity-local-directory|`/local/`]] (наш, с приоритетом).

## Состав

| Файл | Когда подключается / зачем |
|---|---|
| `dbconn.php` | **первым** в пайплайне: параметры соединения с БД, глобальные лимиты выполнения |
| `dbconn_error.php` | обработчик ошибки соединения с БД |
| `dbquery_error.php` | обработчик ошибки SQL-запроса |
| `after_connect.php` | сразу после подключения к БД |
| `after_connect_d7.php` | D7-вариант предыдущего |
| **`init.php`** | общая инициализация; **типичное место регистрации обработчиков событий** |
| `<ID сайта>/init.php` | посайтная инициализация, после определения `SITE_ID` |

Порядок и номера шагов — [[concept-request-lifecycle]].

## Почему `init.php` так важен

Он подключается **до** `OnPageStart` и до авторизации пользователя. Отсюда два следствия:

1. это правильное место для `addEventHandler` ([[pattern-events-over-core-modification]]);
2. **`$USER` там ещё не существует** — код, рассчитывающий на текущего пользователя, получит
   пустоту.

Модули вписывают в `init.php` свою загрузку — по маркерам, чтобы корректно снять при удалении
([[recipe-d7-orm-event-subscription]]).

## Подводные камни

- **`init.php` — общий файл портала**, его правят и люди, и модули. Перед заливкой делайте
  резервную копию и проверяйте линтом после ([[recipe-safe-module-deploy]]): синтаксическая
  ошибка здесь роняет весь портал.
- **Приоритет `/local/` подтверждён для общего `init.php`.** Для посайтного
  (`<ID сайта>/init.php`) документация этого не утверждает — проверяйте на своей версии.
- **`/bitrix/php_interface/` формально запрещён к правке**, хотя описан как штатная точка
  расширения. Резолюция противоречия — в [[concept-change-invasiveness-hierarchy]]: назначение
  каталога штатное, правильная реализация — параллельный `/local/php_interface/`.
- Логика не должна жить в `init.php` — только подключение и регистрация. Иначе файл превращается
  в свалку, которую боятся трогать.

## Открытые вопросы
- Чем `after_connect_d7.php` отличается от `after_connect.php`.

## Связанное
- [[entity-local-directory]] — где должен лежать наш экземпляр
- [[concept-request-lifecycle]] — точные шаги подключения

[← Ядро D7](_index-core-d7.md)
