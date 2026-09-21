---
title: "\\Bitrix\\Crm\\Service\\Container"
type: entity
module: crm
edition: box
status: verified
provenance: mixed
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Модуль CRM — Универсальное API, Кастомизация (подмена фабрики, добавление действий); getScope, getUserBroker и контекст при REST — эмпирика, crm 26.800"
tags: [crm, universal-api, container, класс, d7]
sources: ["[[source-devbook-crm]]"]
related: ["[[concept-crm-universal-api]]", "[[entity-crm-factory]]", "[[concept-service-locator]]", "[[recipe-crm-history-all-fields]]", "[[recipe-smart-process-factory-customization]]"]
aliases: ["bitrix24-crm-container"]
updated: "2026-09-21"
---

# `\Bitrix\Crm\Service\Container`

**Что это:** singleton и точка входа в [[concept-crm-universal-api|Universal API]] — фасад над
сервисами CRM и абстрактная фабрика для фабрик сущностей. Отдельной страницы про API контейнера в
книге нет (страница «Контейнер» дублирует «Концепцию»); сведения собраны со страниц кастомизации
([Подмена фабрики](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Kastomizacia/Podmena_fabriki.html)).
Атрибуция исправлена при сверке 2026-09-21; эмпирические строки помечены.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | класс (singleton) |
| Модуль | `crm` (`Loader::includeModule('crm')`) |
| Имя сервиса | `crm.service.container` в [[concept-service-locator\|ServiceLocator]] |
| Edition | box |

```php
$container = \Bitrix\Crm\Service\Container::getInstance();
// под капотом: ServiceLocator::getInstance()->get('crm.service.container')
```

## Методы

| Метод | Возврат | Назначение |
|---|---|---|
| `getFactory(int $entityTypeId)` | `?Factory` | главная точка получения фабрики |
| `getContext()` | `Context` | контекст хита — пользователь хита (`getUserId()`); `getScope()` — эмпирика |
| `getFileUploader()` | `FileUploader` | регистрация файлов перед сохранением элемента |
| `getDynamicTypeDataClass()` | `TypeTable` | CRUD типов смарт-процессов |
| `getTypeByEntityTypeId($id)` | `?Type` | объект типа смарт-процесса |
| `getUserBroker()` | — | получение ФИО по ID (эмпирика, [[recipe-crm-history-all-fields]]; в книге нет) |
| `static getIdentifierByClassName($className, $args)` | `string` | сгенерировать имя сервиса фабрики |

## Как работает `getFactory()`

1. Сгенерировать имя сервиса — для смарт-процессов это
   `crm.service.factory.dynamic.<entityTypeId>`.
2. Спросить `ServiceLocator::has($identifier)` — есть, вернуть.
3. Нет — получить `Type` через `getTypeByEntityTypeId()`.
4. Создать фабрику, зарегистрировать в локаторе, вернуть.

**Шаг 2 — рабочая точка подмены**, но книга описывает её как «окно» между инициализацией системы и
первым вызовом фабрики, опирающееся на внутреннюю реализацию: зарегистрировать свой сервис нужно
**до первого `getFactory()`** в хите, а не когда-нибудь. Практика —
[[recipe-smart-process-factory-customization]], [[recipe-crm-history-all-fields]]; страховка —
[[pattern-module-self-disabling-guard|сторож]].

## Подводные камни

- **Контейнер целиком — выбор команды: не подменяем.** Книга допускает подмену контейнера
  (`addInstanceLazy('crm.service.container', …)`) и называет её самым простым путём, но для одной
  фабрики это избыточно, а некоторые **модули** Маркетплейса тоже его подменяют. Если
  `get_class(Container::getInstance())` вернул не стандартный и не ваш класс — разбираться с
  разработчиками модуля.
- **Два контекста.** По книге контекст контейнера — пользователь хита, контекст действия
  (`$this->getContext()` в `Action`) — пользователь, переданный в операцию. Эмпирика команды
  (crm 26.800): при REST-запросе контекст контейнера остаётся «ручным», поэтому источник изменения
  внутри действия берём из контекста операции.

## Связанное
- [[concept-crm-universal-api]] — общая картина
- [[entity-crm-factory]] — что отдаёт

[← CRM](_index-crm.md)
