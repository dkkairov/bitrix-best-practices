---
title: "BX.Main.Filter и BX.Main.filterManager — JS-API фильтра"
type: entity
module: templates-design
edition: box
status: verified
provenance: documented
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): UI / Фильтр — Публичная часть; события — «на момент написания»"
tags: [ui, фильтр, javascript, lazy-load, события]
sources: ["[[source-devbook-ui]]"]
related: ["[[entity-filter-component]]", "[[entity-bx-main-grid]]", "[[concept-platform-reverse-engineering]]"]
aliases: ["bitrix24-filtr-bx-main-filter"]
updated: "2026-09-21"
---

# `BX.Main.Filter` и `BX.Main.filterManager`

**Что это:** работа с фильтром из браузера. `filterManager` — реестр, `Filter` — экземпляр.
Устроено так же, как реестры грида и редактора карточки CRM
([Фильтр → Публичная часть](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Filtr/Publicnaa_cast.html);
при сверке 2026-09-21 добавлены события и программное управление).

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

## Программное управление

| Задача | Как |
|---|---|
| Применить пресет | `filter.getApi().setFilter({preset_id: '…'})` |
| Задать значения и применить | `filter.getApi().setFields({...})`, затем `filter.getApi().apply()` — поле должно быть выведено на форму |
| Добавить поле на форму | описание в `params.FIELDS`, затем `filter.getPreset().addField(…)` и `syncFields()` |

## События

`BX.Main.Filter:show`, `BX.Main.Filter:blur`, `BX.Main.Filter:beforeApply`, `BX.Main.Filter:apply`
(аргументы — `filterId`, действие `clear`/`apply`, экземпляр). Перечень книга даёт «на момент
написания».

## Подводные камни

- **`getFieldByName()` вернёт пусто для поля, которого ещё нет на форме** — при `LAZY_LOAD` описание
  такого поля получают через `getLazyLoadField()`. Помощник выше сначала пробует синхронный путь.
  Код, написанный на стенде без ленивой загрузки, может молча сломаться там, где она включена.
- Реестр надёжнее подписки на событие инициализации: скрипт, подключённый позже, событие пропустит
  (вывод команды; то же для [[recipe-crm-card-editor-js-access|редактора карточки CRM]]).

## Связанное
- [[entity-filter-component]] — серверная часть
- [[entity-bx-main-grid]] — JS-API грида

[← Шаблоны и вёрстка](_index-templates-design.md)
