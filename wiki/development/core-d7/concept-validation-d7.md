---
title: "Валидация D7: PHP-атрибуты вместо простыней if"
type: concept
module: core-d7
edition: box
status: verified
provenance: documented
verified: "2026-06-01 / документация модуля main, раздел валидации (dev.1c-bitrix.ru)"
tags: [d7, валидация, атрибуты, dto, контроллеры, result]
sources: []
related: ["[[concept-service-locator]]", "[[concept-coding-standards]]", "[[concept-change-invasiveness-hierarchy]]", "[[antipattern-ajax-controller-lowercase-name]]"]
aliases: ["bitrix24-validacia"]
updated: "2026-09-18"
---

# Валидация D7

**TL;DR:** в ядре есть декларативная валидация через PHP-атрибуты на свойствах DTO. Это штатный
механизм (уровень 1 иерархии), который заменяет ручные `if`-проверки, особенно в контроллерах.

## Модель: правило и валидатор

|  | Что делает | Что знает | Интерфейс |
|---|---|---|---|
| **Валидатор** | проверяет одно значение | только значение | `\Bitrix\Main\Validation\Validator\ValidatorInterface` |
| **Правило** | декларирует, куда применить валидатор и с какими настройками | о свойстве или классе | `PropertyValidationAttributeInterface` / `ClassValidationAttributeInterface` |

Сервис-исполнитель — `\Bitrix\Main\Validation\ValidationService`, достаётся из
[[concept-service-locator|ServiceLocator]] под кодом `main.validation.service`.

## Базовый сценарий

```php
use Bitrix\Main\Validation\Rule\{Email, Phone, AtLeastOnePropertyNotEmpty};
use Bitrix\Main\DI\ServiceLocator;

#[AtLeastOnePropertyNotEmpty(['email', 'phone'])]
class CreateUser
{
    #[Email] private ?string $email;
    #[Phone] private ?string $phone;
    // геттеры и сеттеры
}

$result = ServiceLocator::getInstance()->get('main.validation.service')->validate($dto);
if (!$result->isSuccess()) {
    return $result;   // \Bitrix\Main\Result с массивом ошибок
}
```

## Где применять

| Слой | Как |
|---|---|
| DTO бизнес-логики | атрибуты на свойствах + `$service->validate($dto)` |
| HTTP-контроллер, скалярный параметр | атрибут прямо на параметре action-метода |
| HTTP-контроллер, DTO | `getAutoWiredParameters()` + `ValidationParameter` |
| Старый код с массивами | прямой вызов валидатора без атрибутов |
| Вложенные объекты | `#[Validatable]` на свойстве-объекте |

## Каталог встроенных правил

- **Правила класса:** `AtLeastOnePropertyNotEmpty`, `OnlyOneOfPropertyRequired`.
- **Правила свойства/параметра:** `ElementsType`, `Email`, `InArray`, `Json`, `Length`, `Max`,
  `Min`, `NotEmpty`, `Phone`, `PhoneOrEmail`, `PositiveNumber`, `Range`, `RegExp`, `Url`,
  рекурсивный `Validatable`.

Большинство правил принимают `errorMessage` — строку или локализуемое сообщение.

## Нюансы поведения, которые ловят руками

- **Модификаторы доступа игнорируются:** `private`-свойства тоже валидируются (рефлексия).
- **Nullable-свойство, которое не устанавливали, — пропускается.** Если `null` присвоен **явно** —
  валидируется. Разница неочевидна и даёт «пропущенные» ошибки.
- **Рекурсивная валидация** возвращает ошибки с точечным путём: `order.payment.systemCode`.

## Результат

`\Bitrix\Main\Result`: `isSuccess()`, `getErrors()` — массив `ValidationError` (наследник
`\Bitrix\Main\Error`) с `getCode()`, `getMessage()`, `getFailedValidator()`.

## Почему важно при внедрении

Это уровень 1 в [[concept-change-invasiveness-hierarchy]]: прежде чем писать свою проверку,
посмотрите, нет ли готового правила. Валидация в контроллере — ещё и защита от вызовов мимо
интерфейса (REST, свои AJAX-действия).

## Открытые вопросы
- Поддерживаются ли группы валидации.
- Можно ли переопределить встроенное правило своим через подмену сервиса.
- Как валидация взаимодействует с D7-ORM.
- При нескольких ошибках на одном свойстве собираются все или работает fail-fast.

## Связанные страницы
- [[concept-service-locator]] — откуда берётся сервис валидации
- [[concept-coding-standards]] — место валидации в общем код-стайле

[← Ядро D7](_index-core-d7.md)
