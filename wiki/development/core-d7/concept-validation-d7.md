---
title: "Валидация D7: PHP-атрибуты вместо простыней if"
type: concept
module: core-d7
edition: box
status: verified
provenance: documented
verified: "2026-09-22 / коробка в Docker, main 26.750.0: группы правил, тип результата, поведение неинициализированных свойств — прогон; текст — «Книга разработчика Bitrix24» (снимок 2026-09-21)"
tags: [d7, валидация, атрибуты, dto, контроллеры, result]
sources: ["[[source-devbook-core-d7]]"]
related: ["[[concept-service-locator]]", "[[concept-coding-standards]]", "[[concept-change-invasiveness-hierarchy]]", "[[antipattern-ajax-controller-lowercase-name]]", "[[recipe-d7-custom-validation-rule]]", "[[entity-validation-service]]", "[[entity-validation-result]]"]
aliases: ["bitrix24-validacia"]
updated: "2026-09-24"
---

# Валидация D7

**TL;DR:** в ядре есть декларативная валидация через PHP-атрибуты на свойствах DTO. Это штатный
механизм (уровень 1 иерархии), который заменяет ручные `if`-проверки, особенно в контроллерах.
Работает только с объектами, не с массивами. Источник — раздел «Валидация» «Книги разработчика»
([Основное](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Validacia/Osnovnoe.html)).

> **Сверено с книгой 2026-09-21.** Атрибуция исправлена (прежде — dev.1c-bitrix.ru). Уточнены:
> полные имена классов, коды ошибок, каталог правил с параметрами, довод в пользу валидации в
> контроллере; тип результата `validate()` книга не называет — помечено. С какой версии `main`
> доступна валидация, в книге нет.

## Модель: правило и валидатор

|  | Что делает | Что знает | Интерфейс |
|---|---|---|---|
| **Валидатор** | проверяет одно значение | только значение | `\Bitrix\Main\Validation\Validator\ValidatorInterface` |
| **Правило** | декларирует, куда применить валидаторы, в каком контексте и с какими настройками | о свойстве или классе | `\Bitrix\Main\Validation\Rule\PropertyValidationAttributeInterface` / `\Bitrix\Main\Validation\Rule\ClassValidationAttributeInterface` |

Сервис-исполнитель — `\Bitrix\Main\Validation\ValidationService`, достаётся из
[[concept-service-locator|ServiceLocator]] под кодом `main.validation.service`. Свои правила и
валидаторы — [[recipe-d7-custom-validation-rule]].

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
    return $result;   // ошибки — ValidationError; точный класс результата в книге не назван
}
```

## Где применять

| Слой | Как |
|---|---|
| DTO бизнес-логики | атрибуты на свойствах + `$service->validate($dto)` |
| HTTP-контроллер, скалярный параметр | атрибут прямо на параметре action-метода (`#[PositiveNumber] int $userId`) — проверка до вызова действия |
| HTTP-контроллер, DTO | `getAutoWiredParameters()` + `\Bitrix\Main\Validation\Engine\AutoWire\ValidationParameter` с замыканием-фабрикой DTO; при ошибке действие не вызывается |
| Старый код, сигнатуру менять нельзя | собрать DTO внутри метода — или прямой вызов валидатора без атрибутов (`(new EmailValidator())->validate($value)`) |
| Вложенные объекты | `#[Validatable]` (`Bitrix\Main\Validation\Rule\Recursive\Validatable`) на свойстве-объекте |

Ответ контроллера при ошибке — стандартный JSON (`status: error`); в примере книги у ошибки
`code: 100`, а перед текстом валидатора — префикс с именем параметра. Смысл кода 100 книга не
объясняет — не ветвить на нём логику фронтенда без проверки на стенде
([Контроллеры](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Validacia/Kontrollery.html)).

## Каталог встроенных правил

([Существующие правила](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Validacia/Susestvuusie_pravila.html);
правила — в `Bitrix\Main\Validation\Rule\`, валидаторы — в `Bitrix\Main\Validation\Validator\`)

| Правило | Ключевые параметры и ловушки |
|---|---|
| `AtLeastOnePropertyNotEmpty` (класс) | список свойств, `allowZero`, `allowEmptyString` |
| `OnlyOneOfPropertyRequired` (класс) | ровно одно непустое из списка; «пустыми» считаются `null`, `0`, `''`, `false`, `[]`; своего валидатора нет |
| `Email`, `PhoneOrEmail` | `strict`, `domainCheck` — **DNS-запрос** (MX/A) при проверке |
| `NotEmpty` | `allowZero`, `allowSpaces` |
| `InArray` | `validValues`, `strict` |
| `Length` | `min`, `max` (оба необязательные) |
| `Min`, `Max`, `Range` | границы — только `int`; `Range` включает границы |
| `PositiveNumber` | реализован через `MinValidator` |
| `RegExp` | `pattern`, `flags`, `offset` (`preg_match`) |
| `ElementsType` | тип из `Bitrix\Main\Validation\Rule\Enum\Type` (`Integer`, `String`, `Float`, `Numeric`) или класс |
| `Json`, `Phone`, `Url` | — |

Состав каталога перепроверен на стенде 2026-09-24 (main 26.750.0): все шестнадцать правил выше
лежат в `Bitrix\Main\Validation\Rule\`, там же `AbstractPropertyValidationAttribute`,
`AbstractClassValidationAttribute`, их интерфейсы и `ValidateByGroupInterface`. Документация
фреймворка ([«Валидация»](https://docs.1c-bitrix.ru/pages/framework/validation.html)) перечисляет
тот же набор, но **о группах валидации не пишет вовсе** — у нас они разобраны ниже и проверены
прогоном.

Почти все правила принимают `errorMessage` — строку или локализуемое сообщение; исключение — `Json`.
Правила ставят и на свойства, и на параметры (в том числе promoted-параметры конструктора и
параметры action-методов).

## Нюансы поведения, которые ловят руками

- **Модификаторы доступа игнорируются:** `private`-свойства тоже валидируются (рефлексия).
- **Nullable-свойство, которое не устанавливали, — пропускается.** Если `null` присвоен **явно** —
  валидируется (значит и свойство с умолчанием `= null` тоже: оно инициализировано). Типизированное
  свойство без `null` в типе, оставленное без значения, даёт ошибку «Не установлено значение
  обязательного поля»; свойство без типа валидируется как `null` (стенд, 26.750.0).
- **Группы правил есть:** `validate($dto, 'order')`; правило участвует в них, если реализует
  `Rule\ValidateByGroupInterface` (встроенные принимают `groups:` в конструкторе). Правило без
  интерфейса применяется в любой группе (стенд).
- **Правило с опечаткой в имени класса молча не работает** — ни ошибки, ни исключения (стенд).
- **Код ошибки — строка:** имя свойства (`id`), для вложенных — путь через точку
  (`order.payment.systemCode`).
- **Тексты сообщений** в примерах книги для одной и той же ошибки различаются — логику по тексту не
  строить.

## Почему важно при внедрении

Это уровень 1 в [[concept-change-invasiveness-hierarchy]]: прежде чем писать свою проверку,
посмотрите, нет ли готового правила. Довод книги для контроллеров: некорректный запрос
останавливается раньше, а формат ошибок единый. Защита от вызовов мимо интерфейса (свои
AJAX-действия, интеграции) — вывод команды.

## Открытые вопросы
- Как валидация взаимодействует с D7-ORM.
- С какой версии `main` доступна валидация (на 26.750.0 пакет есть).

Закрыты по книге: свои валидаторы советуют писать по принципу fail fast; встроенное правило не
подменяют, а пишут своё — [[recipe-d7-custom-validation-rule]].

Закрыты прогоном на стенде 2026-09-22 (`main` 26.750.0): группы правил поддерживаются; тип
результата — `ValidationResult`; поведение неинициализированных свойств. Подробности и код —
[[recipe-d7-custom-validation-rule]], [[entity-validation-service]].

## Связанные страницы
- [[concept-service-locator]] — откуда берётся сервис валидации
- [[entity-validation-service]], [[entity-validation-result]] — классы
- [[concept-coding-standards]] — место валидации в общем код-стайле

[← Ядро D7](_index-core-d7.md)
