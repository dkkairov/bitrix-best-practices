---
title: "ValidationResult и ValidationError"
type: entity
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Технологии — Валидация (Основное, Контроллеры, Существующие правила, Свои правила)"
tags: [валидация, ошибки, d7, класс]
sources: ["[[source-devbook-core-d7]]"]
related: ["[[entity-main-result]]", "[[entity-validation-service]]", "[[concept-validation-d7]]"]
aliases: ["bitrix24-validation-result"]
updated: "2026-09-21"
---

# `ValidationResult` и `ValidationError`

**Что это:** специализированные наследники [[entity-main-result|`Result` и `Error`]] для валидации.
Отличие одно, но важное: ошибка знает, **какой валидатор** её выдал.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | классы |
| Модуль | `main`, namespace `\Bitrix\Main\Validation\` |
| Родители | `\Bitrix\Main\Result` и `\Bitrix\Main\Error` (по ядру; книга наследование не называет, по использованию совместимо) |

```php
new \Bitrix\Main\Validation\ValidationError(
    message: 'Значение недопустимо',
    failedValidator: $this      // ссылка на валидатор
);

$error->getFailedValidator();   // экземпляр валидатора
```

`ValidationResult` — обычный контейнер: `isSuccess()`, `addError()`, `getErrors()`. Создаётся
самим валидатором либо правилом; при написании своего правила его заполняете вы.

## Зачем `getFailedValidator()`

Обработка ошибок перестаёт быть разбором строк: можно ветвить логику или писать в лог по типу
валидатора, а не по тексту сообщения.

## Подводные камни

- **Поле `code` означает разное в разных ситуациях.** В результате сервиса это **всегда строка** —
  имя свойства (`id`), а у вложенных объектов точечный путь (`order.payment.systemCode`). В ответе
  контроллера в примере книги — число `100`, а имя параметра приходит в тексте сообщения; смысл кода
  книга не объясняет. Код, который ждёт число, на ошибке сервиса сломается.
- Какой класс результата возвращает `ValidationService::validate()`, книга не называет; внутри лежат
  именно `ValidationError`.
- Своего валидатора, по книге, нет у `OnlyOneOfPropertyRequired` (правило класса) и `ElementsType`
  (правило свойства) — что вернёт `getFailedValidator()` для их ошибок, проверяйте перед
  использованием. У `AtLeastOnePropertyNotEmpty` валидатор есть — `AtLeastOneNotEmptyValidator`.

> **Уточнено 2026-09-21 при сверке с книгой:** раньше «путь через точку» относился только к
> рекурсивной валидации, а без валидатора назывались «правила уровня класса» целиком.

## Связанное
- [[concept-validation-d7]] — общая картина
- [[entity-main-result]] — родительские классы

[← Ядро D7](_index-core-d7.md)
