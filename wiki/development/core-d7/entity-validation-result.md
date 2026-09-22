---
title: "ValidationResult и ValidationError"
type: entity
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-22 / коробка в Docker, main 26.750.0: коды ошибок сервиса и контроллера, сохранение failedValidator в replaceWithCustomError — прогон и код ядра; текст — «Книга разработчика Bitrix24» (снимок 2026-09-21)"
tags: [валидация, ошибки, d7, класс]
sources: ["[[source-devbook-core-d7]]"]
related: ["[[entity-main-result]]", "[[entity-validation-service]]", "[[concept-validation-d7]]"]
aliases: ["bitrix24-validation-result"]
updated: "2026-09-22"
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
валидатора, а не по тексту сообщения. Своё сообщение правила (`errorMessage`) ссылку на
валидатор не стирает: `replaceWithCustomError()` подставляет в новую ошибку первый отказавший
валидатор (ядро + стенд, 26.750.0). У ошибок правил класса `failedValidator` нет, а код пустой.

## Подводные камни

- **Поле `code` означает разное в разных ситуациях.** В результате сервиса это **всегда строка** —
  имя свойства (`id`), а у вложенных объектов точечный путь (`order.payment.systemCode`). Код,
  который ждёт число, на ошибке сервиса сломается. Откуда в книге берётся `100` (стенд): так
  выглядит ошибка **скалярного параметра** action-метода — биндер заворачивает её в
  `ArgumentException` с кодом `100` и текстом `Invalid value to match parameter: [quantity] …`.
  Если же DTO собирается через `ValidationParameter`, ошибки уходят клиенту как есть — с
  кодом-именем свойства.
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
