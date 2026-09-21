---
title: "\\Bitrix\\Main\\Validation\\ValidationService"
type: entity
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Технологии — Валидация / Основное; тип результата validate() в книге не назван"
tags: [валидация, сервис, d7, класс, рефлексия]
sources: ["[[source-devbook-core-d7]]"]
related: ["[[concept-validation-d7]]", "[[concept-service-locator]]", "[[entity-validation-result]]", "[[entity-main-result]]", "[[recipe-d7-custom-validation-rule]]"]
aliases: ["bitrix24-validation-service"]
updated: "2026-09-21"
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
| Главный метод | `validate($object)` — у результата `isSuccess()` и `getErrors()`; точный класс результата книга не называет (предположительно `ValidationResult` — сверить по `main/lib/validation/`) |

```php
$service = \Bitrix\Main\DI\ServiceLocator::getInstance()->get('main.validation.service');
$result  = $service->validate($dto);
```

Это первый встроенный сервис, который приходится доставать по имени, — удобный повод понять
[[concept-service-locator|ServiceLocator]] на практике.

## Как работает

Через рефлексию. Практические следствия:

- **Модификаторы доступа игнорируются** — `private`-свойства тоже проверяются.
- **Nullable-свойство, которое не устанавливали, пропускается**; если `null` присвоен явно —
  валидируется. Разница тонкая и объясняет «почему правило не сработало».
- **Рекурсия** включается атрибутом `\Bitrix\Main\Validation\Rule\Recursive\Validatable` на
  свойстве-объекте; коды ошибок приходят точечным путём (`order.payment.systemCode`).

## Подводные камни

- Рефлексия по объекту — не бесплатная операция. В горячем пути (валидация в цикле по тысячам
  записей) измеряйте, а не предполагайте (совет команды, в книге этого нет).
- Валидация работает по **объекту**, а не по массиву. Для старого кода книга даёт два пути: собрать
  DTO внутри метода, не меняя его сигнатуру, или вызвать валидатор напрямую без атрибутов
  (`(new EmailValidator())->validate($value)`).

## Открытые вопросы
- Есть ли у `validate()` опции (контекст, группы правил).
- Кэшируется ли разбор атрибутов между вызовами.

## Связанное
- [[concept-validation-d7]] — правила, каталог и где применять
- [[entity-validation-result]] — формат ошибок

[← Ядро D7](_index-core-d7.md)
