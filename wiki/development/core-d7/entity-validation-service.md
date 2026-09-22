---
title: "\\Bitrix\\Main\\Validation\\ValidationService"
type: entity
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-22 / коробка в Docker, main 26.750.0: подпись validate(), группы правил, неинициализированные свойства, отсутствие кэша разбора атрибутов — по коду ValidationService и прогону; текст — «Книга разработчика Bitrix24» (снимок 2026-09-21)"
tags: [валидация, сервис, d7, класс, рефлексия]
sources: ["[[source-devbook-core-d7]]"]
related: ["[[concept-validation-d7]]", "[[concept-service-locator]]", "[[entity-validation-result]]", "[[entity-main-result]]", "[[recipe-d7-custom-validation-rule]]"]
aliases: ["bitrix24-validation-service"]
updated: "2026-09-22"
---

# `\Bitrix\Main\Validation\ValidationService`

**Что это:** исполнитель валидации: принимает объект с атрибутами-правилами и возвращает результат
с ошибками `ValidationError` ([Валидация](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Validacia/Osnovnoe.html)).

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | сервис |
| Модуль | `main` |
| Имя в локаторе | `main.validation.service` |
| Главный метод | `validate(object $object, mixed $group = null): ValidationResult` (ядро 26.750.0); у результата `isSuccess()` и `getErrors()` |
| Второй метод | `validateParameter(ReflectionParameter $parameter, mixed $value)` — им пользуется биндер контроллера |

```php
$service = \Bitrix\Main\DI\ServiceLocator::getInstance()->get('main.validation.service');
$result  = $service->validate($dto);
```

Это первый встроенный сервис, который приходится доставать по имени, — удобный повод понять
[[concept-service-locator|ServiceLocator]] на практике.

## Как работает

Через рефлексию. Практические следствия:

- **Модификаторы доступа игнорируются** — `private`-свойства тоже проверяются.
- **Неинициализированные свойства** (стенд): nullable — пропускается; типизированное без `null`
  в типе — своя ошибка «Не установлено значение обязательного поля» с кодом-именем свойства;
  свойство **без типа** считается инициализированным как `null` и валидируется.
- Если `null` присвоен явно — свойство валидируется. Разница тонкая и объясняет «почему правило
  не сработало».
- **Группы правил** — второй аргумент `validate($dto, 'order')`. Правило учитывает группы, если
  реализует `Rule\ValidateByGroupInterface`; без интерфейса или с пустым списком групп
  применяется всегда (стенд).
- **Атрибут неизвестного класса** (опечатка, забытый `use`) молча пропускается — валидации
  просто не будет (стенд).
- **Рекурсия** включается атрибутом `\Bitrix\Main\Validation\Rule\Recursive\Validatable` на
  свойстве-объекте; коды ошибок приходят точечным путём (`order.payment.systemCode`).

## Подводные камни

- Рефлексия по объекту — не бесплатная операция, и **разбор не кэшируется**: на каждый вызов
  заново читаются атрибуты и создаётся экземпляр правила (`newInstance()`), при работе с
  группами — дважды (ядро 26.750.0). В горячем пути (валидация в цикле по тысячам записей)
  измеряйте, а не предполагайте.
- Валидация работает по **объекту**, а не по массиву. Для старого кода книга даёт два пути: собрать
  DTO внутри метода, не меняя его сигнатуру, или вызвать валидатор напрямую без атрибутов
  (`(new EmailValidator())->validate($value)`).

> **Уточнено по ядру и прогону на стенде 2026-09-22** (`main` 26.750.0): подпись `validate()`,
> группы правил, поведение неинициализированных свойств, отсутствие кэша разбора атрибутов.

## Открытые вопросы
- Как группы валидации используются в самом ядре (какие модули их задают).

## Связанное
- [[recipe-d7-custom-validation-rule]] — своё правило, группы, прогон на стенде
- [[concept-validation-d7]] — правила, каталог и где применять
- [[entity-validation-result]] — формат ошибок

[← Ядро D7](_index-core-d7.md)
