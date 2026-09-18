---
title: "ValidationResult и ValidationError"
type: entity
module: core-d7
edition: box
status: verified
provenance: documented
verified: "2026-06-01 / документация модуля main, раздел валидации (dev.1c-bitrix.ru)"
tags: [валидация, ошибки, d7, класс]
sources: []
related: ["[[entity-main-result]]", "[[entity-validation-service]]", "[[concept-validation-d7]]"]
aliases: ["bitrix24-validation-result"]
updated: "2026-09-18"
---

# `ValidationResult` и `ValidationError`

**Что это:** специализированные наследники [[entity-main-result|`Result` и `Error`]] для валидации.
Отличие одно, но важное: ошибка знает, **какой валидатор** её выдал.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | классы |
| Модуль | `main`, namespace `\Bitrix\Main\Validation\` |
| Родители | `\Bitrix\Main\Result` и `\Bitrix\Main\Error` |

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

- **Поле `code` означает разное в разных ситуациях**: в ответе контроллера это числовой код
  (`100` — ошибка валидации параметра), а при рекурсивной валидации — точечный путь к свойству
  (`order.payment.systemCode`). Код, который ждёт число, на вложенном объекте сломается.
- `ValidationService::validate()` возвращает обычный `Result`, но внутри лежат именно
  `ValidationError`. Вызывающий код может об этом не знать — а может воспользоваться.
- Для правил уровня класса (`OnlyOneOfPropertyRequired` и подобных) непонятно, что вернёт
  `getFailedValidator()`: отдельного валидатора у них нет. Проверяйте перед использованием.

## Связанное
- [[concept-validation-d7]] — общая картина
- [[entity-main-result]] — родительские классы

[← Ядро D7](_index-core-d7.md)
