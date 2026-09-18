---
title: "\\Bitrix\\Crm\\Service\\Container"
type: entity
module: crm
edition: box
status: verified
provenance: documented
verified: "2026-06-02 / документация Universal API CRM (apidocs.bitrix24.ru)"
tags: [crm, universal-api, container, класс, d7]
sources: []
related: ["[[concept-crm-universal-api]]", "[[entity-crm-factory]]", "[[concept-service-locator]]", "[[recipe-crm-history-all-fields]]"]
aliases: ["bitrix24-crm-container"]
updated: "2026-09-18"
---

# `\Bitrix\Crm\Service\Container`

**Что это:** singleton и точка входа в [[concept-crm-universal-api|Universal API]] — фасад над
сервисами CRM и абстрактная фабрика для фабрик сущностей.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | класс (singleton) |
| Модуль | `crm` (`Loader::includeModule('crm')`) |
| Имя сервиса | `crm.service.container` в [[entity-service-locator\|ServiceLocator]] |
| Edition | box |

```php
$container = \Bitrix\Crm\Service\Container::getInstance();
// под капотом: ServiceLocator::getInstance()->get('crm.service.container')
```

## Методы

| Метод | Возврат | Назначение |
|---|---|---|
| `getFactory(int $entityTypeId)` | `?Factory` | главная точка получения фабрики |
| `getContext()` | `Context` | контекст хита: `getUserId()`, `getScope()` |
| `getFileUploader()` | `FileUploader` | регистрация файлов перед сохранением элемента |
| `getDynamicTypeDataClass()` | `TypeTable` | CRUD типов смарт-процессов |
| `getTypeByEntityTypeId($id)` | `?Type` | объект типа смарт-процесса |
| `getUserBroker()` | — | получение ФИО по ID (используется при форматировании истории) |
| `static getIdentifierByClassName($className, $args)` | `string` | сгенерировать имя сервиса фабрики |

## Как работает `getFactory()`

1. Сгенерировать имя сервиса — для смарт-процессов это
   `crm.service.factory.dynamic.<entityTypeId>`.
2. Спросить `ServiceLocator::has($identifier)` — есть, вернуть.
3. Нет — получить `Type` через `getTypeByEntityTypeId()`.
4. Создать фабрику, зарегистрировать в локаторе, вернуть.

**Шаг 2 — это и есть штатная точка подмены:** зарегистрировав свой сервис под этим именем, вы
перехватываете фабрику конкретного типа. Практика — [[recipe-crm-history-all-fields]].

## Подводные камни

- **Подменять контейнер целиком не нужно и опасно.** Некоторые приложения маркетплейса тоже его
  подменяют. Если `get_class(Container::getInstance())` вернул не стандартный и не ваш класс —
  разбираться с их разработчиками. Подменяйте фабрику одного типа.
- **`getContext()` контейнера при REST-запросе остаётся «ручным».** Внутри действия операции брать
  контекст у самой операции (`Action::getContext()`), иначе источник изменения определится неверно.

## Связанное
- [[concept-crm-universal-api]] — общая картина
- [[entity-crm-factory]] — что отдаёт

[← CRM](_index-crm.md)
