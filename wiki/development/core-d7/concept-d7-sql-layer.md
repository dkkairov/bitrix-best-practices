---
title: "Прямой SQL в D7: соединение, SqlHelper, SqlExpression"
type: concept
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / коробка в Docker, main 26.750.0, MySQL 8.0: состав методов MysqliSqlHelper и Connection, образцы вывода quote/IFNULL/CONCAT/rand/INSERT IGNORE, компиляция SqlExpression с экранированием; текст — документация фреймворка (docs.1c-bitrix.ru, «Выполнение запросов», «Формирование запросов»)"
tags: [sql, бд, sqlhelper, sqlexpression, безопасность, d7]
sources: []
related: ["[[recipe-d7-transactions]]", "[[concept-d7-orm-query]]", "[[recipe-d7-orm-crud]]", "[[concept-postgresql-compatibility]]", "[[recipe-slow-query-diagnostics]]"]
aliases: []
updated: "2026-09-24"
---

# Прямой SQL в D7: соединение, SqlHelper, SqlExpression

**TL;DR:** ORM закрывает почти всё ([[concept-d7-orm-query]]), но иногда нужен запрос руками. Тогда
работаем через объект соединения, а значения и имена колонок подставляем **только** через
`SqlExpression` или `SqlHelper` — иначе получаем инъекцию.

**Когда спускаться до SQL:** массовые операции, `INSERT ... SELECT`, оконные функции, служебные
запросы миграций. Разовая выборка — задача ORM.

## Соединение и выполнение

```php
$conn = \Bitrix\Main\Application::getConnection();
```

| Метод | Для чего |
|---|---|
| `query($sql)`, `query($sql, $limit)`, `query($sql, $offset, $limit)` | `SELECT`, возвращает `DB\Result` |
| `queryScalar($sql)` | первый столбец первой строки |
| `queryExecute($sql)` | `UPDATE`, `DELETE` — без результата |
| `add($table, $fields)`, `addMulti($table, $rows)` | вставка: значения экранируются, наличие колонок проверяется, возвращает id |
| `getInsertedId()`, `getAffectedRowsCount()` | после вставки и изменения |
| `isTableExists()`, `createIndex()`, `renameTable()`, `truncateTable()`, `createTable()`, `dropColumn()` | DDL для миграций ([[recipe-migrations-as-code]]) |
| `lock()`, `unlock()` | блокировки уровня приложения |

Результат `DB\Result`: `fetch()` (с преобразованием типов по настройкам системы), `fetchRaw()`
(как есть), `fetchAll()`, `getSelectedRowsCount()`, `getFields()`, `getResource()`; объект
итерируемый — работает `foreach`. Преобразования настраиваются `setConverters()` и
`addFetchDataModifier()`.

## SqlHelper: пишем на диалекте текущей СУБД

```php
$helper = $conn->getSqlHelper();
```

На стенде (MySQL 8.0) это `Bitrix\Main\DB\MysqliSqlHelper`; на PostgreSQL будет свой класс с тем же
интерфейсом — в этом и смысл ([[concept-postgresql-compatibility]]).

| Группа | Методы | Что вернул стенд |
|---|---|---|
| Экранирование | `quote()`, `forSql()` | `quote('ID')` → `` `ID` `` |
| Приведение значений | `convertToDb()`, `convertToDbString()`, `convertToDbInteger()`, `convertToDbFloat()`, `convertToDbDate()`, `convertToDbDateTime()` | даты в формате базы |
| Функции | `getCurrentDateFunction()`, `getCurrentDateTimeFunction()`, `getConcatFunction()`, `getIsNullFunction()`, `getLengthFunction()`, `getMatchFunction()`, `getRandomFunction()`, `getSha1Function()` | `NOW()`, `CONCAT(A, B)`, `IFNULL(A, B)`, `rand()` |
| Дата-арифметика | `addSecondsToDateTime()`, `addDaysToDateTime()`, `formatDate()` | вместо `DATE_ADD`/`DATE_FORMAT` |
| Конструкции | `getInsertIgnore()`, `prepareMerge()`, `prepareMergeMultiple()`, `prepareMergeValues()`, `prepareInsert()`, `prepareUpdate()`, `prepareCorrelatedUpdate()`, `prepareDeleteLimit()` | `getInsertIgnore()` → `INSERT IGNORE INTO …` |

Все перечисленные методы есть в 26.750.0 — проверено перебором.

## SqlExpression: плейсхолдеры вместо склейки

```php
use Bitrix\Main\DB\SqlExpression;

$expr = new SqlExpression(
    'SELECT ?# FROM ?# WHERE ID = ?i AND LOGIN = ?s',
    'ID', 'b_user', 1, "ad'min"
);
echo $expr->compile();
// SELECT `ID` FROM `b_user` WHERE ID = 1 AND LOGIN = 'ad\'min'
```

| Плейсхолдер | Значение |
|---|---|
| `?` / `?s` | строка с экранированием |
| `?i` | целое |
| `?f` | число с плавающей точкой |
| `?#` | имя колонки или таблицы (обрамляется кавычками диалекта) |
| `?v` | список значений для `VALUES` |

`SqlExpression` годится и как значение поля в ORM — счётчики без гонки
(`'CNT' => new SqlExpression('?# + ?i', 'CNT', 1)`, [[recipe-d7-orm-crud]]). Не путаем с
`ORM\Fields\ExpressionField`: тот описывает **вычисляемое поле сущности** и работает только на
чтение ([[concept-d7-orm-entity]]).

## Безопасность

- **Параметр `binds` в методах соединения защиты не даёт** — прямое предупреждение документации.
  Единственный безопасный путь — плейсхолдеры `SqlExpression` и методы `SqlHelper`.
- **Конкатенация переменной в SQL — инъекция**, даже если «значение точно число». Число приходит из
  запроса пользователя чаще, чем кажется.
- Методы `SqlHelper`, принимающие куски SQL (`getConcatFunction`, `getIsNullFunction` и подобные),
  **не экранируют аргументы** — это сделано, чтобы в них можно было передавать выражения. Значения
  в них подставляем уже подготовленными.

## Связанные страницы
- [[concept-d7-orm-query]] — то же самое без SQL
- [[recipe-d7-transactions]] — транзакции вокруг запросов
- [[concept-postgresql-compatibility]] — почему нельзя писать диалект руками
- [[recipe-slow-query-diagnostics]] — посмотреть, какой SQL ушёл в базу

[← Ядро D7](_index-core-d7.md)
