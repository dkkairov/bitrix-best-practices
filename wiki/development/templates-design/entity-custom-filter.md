---
title: "\\Bitrix\\Main\\Filter — свой фильтр (Filter, DataProvider, Factory)"
type: entity
module: templates-design
edition: box
status: verified
provenance: documented
verified: "2026-06-01 / документация модуля main, раздел «Свой фильтр» (dev.1c-bitrix.ru)"
tags: [ui, фильтр, свой-фильтр, dataprovider, orm, d7]
sources: []
related: ["[[entity-filter-component]]", "[[entity-filter-options]]", "[[entity-main-event]]", "[[concept-ui-subsystem]]"]
aliases: ["bitrix24-filter-class"]
updated: "2026-09-18"
---

# `\Bitrix\Main\Filter\…` — свой фильтр

**Что это:** современный способ сделать фильтр для своей сущности. Рекомендованная замена
[[entity-filter-options|`UI\Filter\Options`]] в новом коде.

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
| `prepareFilterValue` (protected) | **предназначен для переопределения** |

## `DataProvider` — контракт

| Метод | Возврат | Назначение |
|---|---|---|
| `getSettings()` | `Settings` | конфигурация |
| `prepareFields()` | `Field[]` | описания полей (хелпер `$this->createField($id, $config)`) |
| `prepareFieldData($fieldID)` | `array\|null` | мета-описание поля |
| `prepareListFilterParam(array &$filter, $fieldId)` | — | преобразование `_from`/`_to` → `>=`/`<=` |

Наследник `EntityDataProvider` берёт часть работы на себя для ORM-сущностей.

Фабрики фильтров собираются событием — каждый модуль добавляет свои через обработчик, возвращающий
`EventResult` с массивом `callbacks` ([[entity-main-event]]).

## Подводные камни

- **`prepareFilterValue` стандартной реализации плохо работает с диапазонами** — документация
  прямо рекомендует переопределять его в наследнике. Если фильтр «по датам от и до» ведёт себя
  странно, начинать надо отсюда.
- Преобразование `_from`/`_to` в префиксы ORM — отдельный хук `prepareListFilterParam`; забыть его
  легко, а симптом — фильтр применяется, но выборка не сужается.
- Не путать два `Filter`: `\Bitrix\Main\UI\Filter\…` — старый путь (визуал и пользовательские
  настройки), `\Bitrix\Main\Filter\…` — этот, современный.

## Связанное
- [[entity-filter-options]] — путь, который этот API заменяет
- [[entity-filter-component]] — визуальная часть, куда отдаются поля

[← Шаблоны и вёрстка](_index-templates-design.md)
