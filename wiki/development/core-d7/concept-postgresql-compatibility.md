---
title: "Код, совместимый с PostgreSQL"
type: concept
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / документация фреймворка (docs.1c-bitrix.ru, «Как писать код, совместимый с PostgreSQL», «Поддержка PostgreSQL в модулях») + коробка в Docker, main 26.750.0 на MySQL 8.0: Connection::getType() = mysql, наличие методов SqlHelper-замен; сама работа на PostgreSQL не проверялась"
tags: [postgresql, mysql, бд, sqlhelper, переносимость]
sources: []
related: ["[[concept-d7-sql-layer]]", "[[recipe-d7-transactions]]", "[[concept-d7-orm-query]]", "[[recipe-migrations-as-code]]", "[[concept-dev-standards]]"]
aliases: []
updated: "2026-09-24"
---

# Код, совместимый с PostgreSQL

**TL;DR:** коробка больше не только про MySQL. Код, где диалект MySQL зашит руками — обратные
кавычки, `IFNULL`, `INSERT IGNORE`, `LIMIT` в `DELETE`, — на PostgreSQL ломается. Все эти
конструкции есть в `SqlHelper`, и писать нужно через него.

**Когда важно:** делаем модуль или решение «в продажу» либо для заказчика, который может переехать
на PostgreSQL. Для одноразовой доработки на известном стенде — вопрос гигиены, но не жизни.

## Определить СУБД в коде

```php
$conn = \Bitrix\Main\Application::getConnection();
$conn->getType();     // 'mysql' на нашем стенде
$conn->getVersion();  // ['8.0.46', …]
get_class($conn->getSqlHelper());  // Bitrix\Main\DB\MysqliSqlHelper
```

Ветвиться по `getType()` — крайняя мера. Нормальный путь — спросить у `SqlHelper` нужную
конструкцию, он отдаст её на диалекте текущей базы.

## Таблица замен

| Конструкция MySQL | Чем заменить |
|---|---|
| обратные кавычки `` `ID` `` | `$helper->quote('ID')` |
| двойные кавычки вокруг строк | только одинарные; двойные в PostgreSQL — идентификатор |
| `IFNULL()` | `$helper->getIsNullFunction()` (в PostgreSQL — `coalesce`) |
| `MID()` | `substr()` |
| `IF(...)` | `CASE WHEN … THEN … ELSE … END` |
| `YEAR()`, `MONTH()`, `DAY()` | `extract(YEAR FROM …)` |
| `LOCATE()` | `POSITION('x' IN col)` |
| `DATE_ADD()`, `DATE_SUB()` | `$helper->addSecondsToDateTime()`, `addDaysToDateTime()` |
| `DATE_FORMAT()` | `$helper->formatDate()` |
| `CONCAT()` | `$helper->getConcatFunction()` |
| `RAND()` | `$helper->getRandomFunction()` |
| `SHA1()` | `$helper->getSha1Function()` |
| `GROUP_CONCAT()` | не использовать — режет данные по длине |
| `INSERT IGNORE` | `$helper->getInsertIgnore()` |
| `REPLACE INTO` | `$helper->prepareMerge()` / `prepareMergeMultiple()` |
| `DELETE … LIMIT` | `$helper->prepareDeleteLimit()` |
| `UPDATE IGNORE` | прямой замены нет — два запроса |

Все эти методы есть в ядре 26.750.0 (проверено перебором на стенде,
[[concept-d7-sql-layer]]).

## Схема данных

| В MySQL | Как делать переносимо |
|---|---|
| `timestamp` | `datetime` плюс логика на PHP |
| `enum` | `char` с проверкой в коде |
| `char(n)` для строк переменной длины | `varchar` |
| `unsigned` | взять тип большей ёмкости |

**Имена индексов** — важная мелочь: префикс `ux_` (уникальный), `tx_` (полнотекстовый), `ix_`
(остальные), дальше таблица и колонки, всё вместе не длиннее **63 символов** — ограничение
PostgreSQL.

## Модули

Документация ведёт таблицу поддержки: **около 130 модулей** работают на PostgreSQL, примерно
**20 — нет**; среди неподдерживаемых Wiki, A/B-тестирование, веб-аналитика, веб-формы, конструктор
отчётов. Перед обещанием заказчику «переедем на PostgreSQL» сверяемся с таблицей по составу
модулей проекта — своё решение может быть готово, а нужный штатный модуль нет.

## Подводные камни

- **`.sql`-файлы установки модуля пишутся под конкретную СУБД.** Модуль, который ставится
  одним `install/db/mysql/install.sql`, на PostgreSQL не встанет; переносимый путь — описывать
  таблицы сущностями ORM и создавать их из кода ([[recipe-migrations-as-code]]).
- **Регистр имён.** PostgreSQL приводит неэкранированные идентификаторы к нижнему регистру —
  ещё одна причина не писать имена руками.
- **Не проверяли на живом PostgreSQL.** Страница собрана из документации и сверки методов на
  MySQL-стенде. Перед первым проектом на PostgreSQL прогоняем ключевые сценарии на нём.

## Связанные страницы
- [[concept-d7-sql-layer]] — соединение, `SqlHelper`, `SqlExpression`
- [[recipe-migrations-as-code]] — создание таблиц из кода
- [[concept-dev-standards]] — общие правила кода коробки

[← Ядро D7](_index-core-d7.md)
