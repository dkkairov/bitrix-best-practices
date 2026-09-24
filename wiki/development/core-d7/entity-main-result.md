---
title: "\\Bitrix\\Main\\Result и \\Bitrix\\Main\\Error"
type: entity
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Валидация — Основное, Контроллеры; Задачи — Основные команды; стиль «Result вместо исключения» — практика команды; 2026-09-24 — состав методов Result и ErrorCollection сверен по ядру стенда (main 26.750.0) и документации фреймворка"
tags: [d7, result, error, класс, обработка-ошибок, контроллеры]
sources: ["[[source-devbook-core-d7]]"]
related: ["[[concept-validation-d7]]", "[[entity-crm-operation]]", "[[concept-coding-standards]]", "[[entity-validation-result]]", "[[concept-tasks-api-v2]]"]
aliases: ["bitrix24-result-error"]
updated: "2026-09-24"
---

# `\Bitrix\Main\Result` и `\Bitrix\Main\Error`

**Что это:** сквозной способ вернуть «получилось или нет, и что именно не так». Используется по
всему D7 — от валидации до операций CRM. Альтернатива исключению, брошенному в неожиданном месте.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | классы |
| Модуль | `main` |
| Наследники | `ValidationResult` (от `Result`) и `ValidationError` (от `Error`) — [[entity-validation-result]]; результат команд задач V2 `Bitrix\Tasks\V2\Internal\Result\Result` — [[concept-tasks-api-v2]]. `ErrorCollection` — отдельная коллекция ошибок, не наследник `Result` (по ядру, сверить) |

## API

```php
$result = new \Bitrix\Main\Result();
$result->addError(new \Bitrix\Main\Error('Текст ошибки', 'ERROR_CODE'));

$result->isSuccess();            // bool
$result->getErrors();            // \Bitrix\Main\Error[]
$result->getErrorMessages();     // string[]
$result->addErrors([$e1, $e2]);  // несколько сразу
$result->setData(['id' => 42]);
$result->getData();
$result->getErrorCollection()->getErrorByCode('ERROR_CODE');   // найти ошибку по коду
```

Все перечисленные методы есть в ядре 26.750.0 (сверено перебором 2026-09-24), включая
`ErrorCollection::getErrorByCode()`. Документация фреймворка объясняет и смысл конструкции:
`Result` нужен там, где ошибок может быть **несколько сразу** — например, невалидны три поля формы;
исключение сообщает ровно об одной проблеме и разворачивает стек.

## Канонический стиль

```php
public function create(CreateUser $data): \Bitrix\Main\Result
{
    $result = $this->validation->validate($data);
    if (!$result->isSuccess()) {
        return $result;
    }
    // бизнес-логика
    return $result;
}
```

Возвращать `Result` наружу через слой сервисов, а не бросать исключение — принятый в команде стиль
(в книге как принцип не сформулирован, но её примеры валидации и команд задач построены так же).
Ровно этот контракт у [[entity-crm-operation|действий операций CRM]]: `addError()` внутри действия
прерывает сохранение и показывает человеку текст.

## Интеграция с контроллером

Если валидация не прошла, D7-контроллер сам формирует ответ (пример из книги —
[Контроллеры](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Validacia/Kontrollery.html)):

```json
{
  "status": "error",
  "data": null,
  "errors": [{ "message": "…", "code": 100, "customData": null }]
}
```

В примере книги код — `100`, а перед текстом валидатора в `message` стоит префикс с именем
параметра. Что означает `100`, книга не объясняет: не ветвите фронтенд по этому коду без проверки на
стенде.

## Подводные камни

- **Проверять `isSuccess()`, а не пустоту `getErrors()`** — наследники могут переопределять
  логику успеха.
- **Текст ошибки видит пользователь.** В действиях операций CRM это единственное, что он получит
  вместо «не сохранилось»: писать по-человечески, без кодов и имён классов.
- `ErrorCollection` (например, возврат `internalExecute()` у действия БП) — родственник, но не то
  же самое: у него своя семантика накопления.

## Связанное
- [[concept-validation-d7]] — главный источник ошибок этого вида
- [[entity-crm-operation]] — где `addError()` отменяет операцию

[← Ядро D7](_index-core-d7.md)
