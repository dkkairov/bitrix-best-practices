---
title: "Транзакции D7: начать, зафиксировать, откатить"
type: recipe
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / коробка в Docker, main 26.750.0, MySQL 8.0: прогон вложенной транзакции — вложенный rollback бросает Bitrix\\Main\\DB\\TransactionException «Nested rollbacks are unsupported»; текст — документация фреймворка (docs.1c-bitrix.ru, «Транзакции»)"
tags: [транзакции, бд, orm, savepoint, d7]
sources: []
related: ["[[concept-d7-sql-layer]]", "[[recipe-d7-orm-crud]]", "[[concept-orm-datamanager-events]]", "[[recipe-slow-query-diagnostics]]"]
aliases: []
updated: "2026-09-24"
---

# Транзакции D7: начать, зафиксировать, откатить

**Результат:** группа изменений применяется целиком или не применяется вовсе.

**Когда применять:** операция меняет несколько таблиц и «половина изменений» недопустима — перенос
остатка, создание связанных сущностей, импорт с побочными записями.

## Базовый вид

```php
use Bitrix\Main\Application;

$conn = Application::getConnection();
$conn->startTransaction();

try
{
    $result = BookTable::add([...]);
    if (!$result->isSuccess())
    {
        throw new \Bitrix\Main\SystemException(implode('; ', $result->getErrorMessages()));
    }

    OrderTable::update($id, [...]);

    $conn->commitTransaction();
}
catch (\Throwable $e)
{
    $conn->rollbackTransaction();
    throw $e;
}
```

Транзакция общая для прямых запросов и ORM: `BookTable::add()` и `$conn->queryExecute()` внутри
одного блока попадают в одну транзакцию ([[concept-d7-sql-layer]]).

## Вложенные транзакции

| Вызов | Что реально происходит |
|---|---|
| внешний `startTransaction()` | обычный `START TRANSACTION` |
| вложенный `startTransaction()` | `SAVEPOINT` |
| вложенный `commitTransaction()` | **в базу ничего не пишет** — решение за внешней транзакцией |
| вложенный `rollbackTransaction()` | откат к точке сохранения **и** `TransactionException` |

Проверено на стенде: вложенный откат бросает `Bitrix\Main\DB\TransactionException` с текстом
«Nested rollbacks are unsupported», после чего внешний `rollbackTransaction()` отрабатывает штатно.

Практический вывод: **вложенный код не откатывает — он бросает исключение**, а решение об откате
принимает тот, кто открыл внешнюю транзакцию. Иначе получаем исключение там, где ждали тихий откат.

## Правила

- **Транзакция короткая.** Внутрь не кладём HTTP-запросы, отправку почты, разбор больших файлов:
  блокировки висят всё это время, растёт риск взаимных блокировок.
- **Обработчики событий сработают внутри.** `OnAfterAdd` вызовется до `commit`: если обработчик шлёт
  уведомление, оно уйдёт даже при последующем откате ([[concept-orm-datamanager-events]]). Всё, что
  нельзя «отменить», выносим за транзакцию.
- **Откат — только через `rollbackTransaction()`.** `return` из середины блока оставит транзакцию
  открытой до конца хита.
- **Проверяем `Result`.** ORM не бросает исключение при ошибке записи — вернёт `Result` с ошибками
  ([[recipe-d7-orm-crud]]); без проверки транзакция зафиксирует незаписанное.
- **DDL не откатывается.** `createTable()`, `dropColumn()` и прочий DDL в MySQL завершает транзакцию
  неявно — миграции не оборачиваем в транзакцию и пишем их идемпотентными.

## Связанные страницы
- [[concept-d7-sql-layer]] — соединение, прямой SQL, SqlHelper
- [[recipe-d7-orm-crud]] — запись через ORM и проверка результата
- [[recipe-slow-query-diagnostics]] — что делать с долгими запросами внутри транзакции

[← Ядро D7](_index-core-d7.md)
