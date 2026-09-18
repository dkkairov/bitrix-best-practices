---
title: "\\Bitrix\\Main\\Validation\\ValidationService"
type: entity
module: core-d7
edition: box
status: verified
provenance: documented
verified: "2026-06-01 / документация модуля main, раздел валидации (dev.1c-bitrix.ru)"
tags: [валидация, сервис, d7, класс, рефлексия]
sources: []
related: ["[[concept-validation-d7]]", "[[concept-service-locator]]", "[[entity-validation-result]]", "[[entity-main-result]]"]
aliases: ["bitrix24-validation-service"]
updated: "2026-09-18"
---

# `\Bitrix\Main\Validation\ValidationService`

**Что это:** исполнитель валидации: принимает объект с атрибутами-правилами и возвращает
[[entity-main-result|`Result`]] с ошибками.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | сервис |
| Модуль | `main` |
| Имя в локаторе | `main.validation.service` |
| Главный метод | `validate($object): \Bitrix\Main\Result` |

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
  записей) измеряйте, а не предполагайте.
- Валидация работает по **объекту**, а не по массиву: для старого кода с массивами правила через
  атрибуты не применить — там вызывают валидатор напрямую.

## Открытые вопросы
- Есть ли у `validate()` опции (контекст, группы правил).
- Кэшируется ли разбор атрибутов между вызовами.

## Связанное
- [[concept-validation-d7]] — правила, каталог и где применять
- [[entity-validation-result]] — формат ошибок

[← Ядро D7](_index-core-d7.md)
