---
title: "\\Bitrix\\Main\\Result и \\Bitrix\\Main\\Error"
type: entity
module: core-d7
edition: box
status: verified
provenance: documented
verified: "2026-06-01 / документация модуля main (dev.1c-bitrix.ru)"
tags: [d7, result, error, класс, обработка-ошибок, контроллеры]
sources: ["[[source-devbook-core-d7]]"]
related: ["[[concept-validation-d7]]", "[[entity-crm-operation]]", "[[concept-coding-standards]]", "[[entity-validation-result]]"]
aliases: ["bitrix24-result-error"]
updated: "2026-09-18"
---

# `\Bitrix\Main\Result` и `\Bitrix\Main\Error`

**Что это:** сквозной способ вернуть «получилось или нет, и что именно не так». Используется по
всему D7 — от валидации до операций CRM. Альтернатива исключению, брошенному в неожиданном месте.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | классы |
| Модуль | `main` |
| Наследники | `ErrorCollection`, `ValidationError` ([[entity-validation-result]]) |

## API

```php
$result = new \Bitrix\Main\Result();
$result->addError(new \Bitrix\Main\Error('Текст ошибки'));

$result->isSuccess();   // bool
$result->getErrors();   // \Bitrix\Main\Error[]
```

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

Возвращать `Result` наружу через слой сервисов, а не бросать исключение — принятый в D7 стиль.
Ровно этот контракт у [[entity-crm-operation|действий операций CRM]]: `addError()` внутри действия
прерывает сохранение и показывает человеку текст.

## Интеграция с контроллером

Если валидация не прошла, D7-контроллер сам формирует ответ:

```json
{
  "status": "error",
  "data": null,
  "errors": [{ "message": "…", "code": 100, "customData": null }]
}
```

Код `100` — общий «ошибка валидации параметра».

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
