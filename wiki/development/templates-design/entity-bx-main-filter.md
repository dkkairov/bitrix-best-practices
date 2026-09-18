---
title: "BX.Main.Filter и BX.Main.filterManager — JS-API фильтра"
type: entity
module: templates-design
edition: box
status: verified
provenance: documented
verified: "2026-06-01 / документация модуля main, публичная часть фильтра (dev.1c-bitrix.ru)"
tags: [ui, фильтр, javascript, lazy-load]
sources: ["[[source-devbook-ui]]"]
related: ["[[entity-filter-component]]", "[[entity-bx-main-grid]]", "[[concept-platform-reverse-engineering]]"]
aliases: ["bitrix24-filtr-bx-main-filter"]
updated: "2026-09-18"
---

# `BX.Main.Filter` и `BX.Main.filterManager`

**Что это:** работа с фильтром из браузера. `filterManager` — реестр, `Filter` — экземпляр.
Устроено так же, как реестры грида и редактора карточки CRM.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | JS-объекты |
| Модуль | `main` |

```js
BX.Main.filterManager.getList();        // все фильтры страницы
BX.Main.filterManager.getById(id);      // конкретный
```

## Экземпляр

| Метод / свойство | Назначение |
|---|---|
| `.params` | все параметры, переданные в компонент (`FILTER_ID`, `FIELDS`, `LAZY_LOAD`…) |
| `getParam(name, default)` | значение параметра |
| `getFilterFieldsValues()` | текущие значения; ключ `FIND` — строка поиска |
| `resetFilter(withoutSearch = false)` | сбросить значения |
| `adjustFocus()` | перевести фокус в поле поиска |
| `getFieldByName(name)` | поле **синхронно** — когда `LAZY_LOAD` выключен |
| `getLazyLoadField(name)` | поле **как Promise** — когда `LAZY_LOAD` включён |

## Универсальный доступ к полю

Два способа получения поля — постоянный источник ошибок. Помощник, который закрывает оба:

```js
BX.Main.Filter.prototype.getFieldDesc = function (fieldName) {
    return new Promise((resolve, reject) => {
        const field = this.getFieldByName(fieldName);
        if (field) { resolve(field); return; }
        if (!this.getParam('LAZY_LOAD')) { reject(); return; }
        return this.getLazyLoadField(fieldName).then(resolve, reject);
    });
};
```

## Подводные камни

- **`getFieldByName()` при включённом `LAZY_LOAD` вернёт пусто** — не ошибку. Код, написанный на
  стенде без ленивой загрузки, молча сломается там, где она включена.
- Реестр — надёжнее подписки на событие инициализации: скрипт, подключённый позже, событие
  пропустит. Тот же вывод, что для [[recipe-crm-card-editor-js-access|редактора карточки CRM]].

## Связанное
- [[entity-filter-component]] — серверная часть
- [[entity-bx-main-grid]] — JS-API грида

[← Шаблоны и вёрстка](_index-templates-design.md)
