---
title: "\\Bitrix\\Main\\Filter — свой фильтр (Filter, DataProvider, Factory)"
type: entity
module: templates-design
edition: box
status: verified
provenance: documented
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): UI / Фильтр — Свой фильтр, Фильтры пользователя"
tags: [ui, фильтр, свой-фильтр, dataprovider, orm, d7]
sources: ["[[source-devbook-ui]]"]
related: ["[[entity-filter-component]]", "[[entity-filter-options]]", "[[entity-main-event]]", "[[concept-ui-subsystem]]", "[[recipe-custom-list-page-filter-grid]]"]
aliases: ["bitrix24-filter-class"]
updated: "2026-09-21"
---

# `\Bitrix\Main\Filter\…` — свой фильтр

**Что это:** «современный подход» (термин автора «Книги разработчика») к фильтру для своей
сущности: объект фильтра поверх провайдеров данных. В новом коде значения берут отсюда, а не через
`UI\Filter\Options::getFilter*()` ([Свой фильтр](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Filtr/Svoj_filtr.html)).
Рецепт целиком — [[recipe-custom-list-page-filter-grid]].

> **Уточнено 2026-09-21 при сверке с книгой.** Рекомендации — автора книги, а не «документации»;
> обязательных методов провайдера три (+ `getFieldName` у `EntityDataProvider`), а
> `prepareListFilterParam` — необязательный хук из примера для дат; классы `UI\Filter\…` (типы полей,
> темы) общие для обоих путей — «устарело» только получение значений через `Options`.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | классы |
| Модуль | `main` |
| Главные классы | `Filter`, `DataProvider` / `EntityDataProvider`, `Factory` / `FactoryMain`, `Settings` |

## `Filter`

```php
new \Bitrix\Main\Filter\Filter(
    string $id,
    DataProvider $mainProvider,
    array $additionalProviders = [],
    array $additionalParams = []
);
```

| Метод | Назначение |
|---|---|
| **`getValue($request = null)`** | **значения, готовые для `DataManager::getList(['filter' => …])`**; `null` — читать из `$_REQUEST` |
| `getFieldArrays($fieldMask = [])` | поля в формате компонента [[entity-filter-component]] |
| `getFields()` | объекты полей всех провайдеров |
| `getID()`, `getDefaultFieldIDs()` | идентификатор и поля по умолчанию |
| `prepareFilterValue` | подготовка значений; штатная плохо работает с диапазонами — пример того, что стоит переопределить в наследнике |

**Главный совет книги — всегда делать наследника `Filter`**, даже пустой класс: так контролируются
аргументы и остаётся место переопределить поведение.

## `DataProvider` — контракт

| Метод | Обязателен | Назначение |
|---|---|---|
| `getSettings()` | да | конфигурация (`Settings`) |
| `prepareFields()` | да | описания полей (хелпер `$this->createField($id, $config)`) |
| `prepareFieldData($fieldID)` | да | мета-описание поля (`array\|null`) |
| `getFieldName($fieldID)` | да, у `EntityDataProvider` | подпись поля — `EntityDataProvider` подставляет названия сам |
| `prepareListFilterParam(array &$filter, $fieldId)` | нет | необязательный хук; в примере книги переопределён для дат (`_from`/`_to` → `>=`/`<=`) |

Создать фильтр можно напрямую (`new Filter(...)` с провайдерами, в книге — `UserDataProvider` +
`UserUFDataProvider`) или фабрикой `\Bitrix\Main\Filter\Factory::createEntityFilter()`. Фабрики
собираются событием `main:OnBuildFilterFactoryMethods`: обработчик возвращает `EventResult` с массивом
`callbacks`, образец — `FactoryMain` ([[entity-main-event]]).

## Подводные камни

- **`prepareFilterValue` стандартной реализации плохо работает с диапазонами.** Если фильтр «по датам
  от и до» ведёт себя странно, начинать надо отсюда (и с `prepareListFilterParam`).
- Если значения дат не превращаются в условия ORM, выборка может не сузиться — проверьте, что
  возвращает `getValue()` (вывод команды, проверить на стенде).
- Не путать два пространства: `\Bitrix\Main\UI\Filter\…` — компонент, пользовательские настройки и
  классы типов полей (`FieldAdapter`, `DateType`, `Theme` — используются и здесь);
  `\Bitrix\Main\Filter\…` — объект фильтра и провайдеры. Устаревшим автор называет только получение
  значений через `UI\Filter\Options`.

## Связанное
- [[entity-filter-options]] — путь, который этот API заменяет
- [[entity-filter-component]] — визуальная часть, куда отдаются поля

[← Шаблоны и вёрстка](_index-templates-design.md)
