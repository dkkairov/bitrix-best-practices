---
title: "BX.Main.gridManager — JS-API грида"
type: entity
module: templates-design
edition: box
status: verified
provenance: documented
verified: "2026-06-01 / документация модуля main, публичная часть таблиц (dev.1c-bitrix.ru)"
tags: [ui, грид, javascript, inline-редактирование]
sources: ["[[source-devbook-ui]]"]
related: ["[[entity-grid-component]]", "[[entity-bx-main-filter]]", "[[recipe-crm-card-editor-js-access]]"]
aliases: ["bitrix24-grid-bx-main"]
updated: "2026-09-18"
---

# `BX.Main.gridManager`

**Что это:** управление гридом из браузера. Реестр гридов страницы плюс API каждого экземпляра.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | JS-объекты |
| Модуль | `main` |

```js
BX.Main.gridManager.getInstanceById(id);
BX.Main.gridManager.data;           // все гриды страницы
BX.Main.gridManager.reload(id, url);  // перезагрузить, опционально с другого адреса
```

## Экземпляр

**Строка ввода (inline-редактирование):** `grid.prependRowEditor()`, `grid.appendRowEditor()`.
Работает, если грид редактируемый и есть хотя бы одна редактируемая отображаемая колонка.

**Прелоадер:** `grid.tableFade()` / `grid.tableUnfade()`.

**Работа с выделением** — через `grid.getRows()`:

| Метод | Назначение |
|---|---|
| `getSelectedIds()` | ID отмеченных строк |
| `getSelected(false)` | объекты отмеченных |
| `isAllSelected()` | состояние флажка «для всех» |
| `unselectAll()` | снять выделение |

## Подводные камни

- **`prependRowEditor()` молча ничего не сделает**, если ни одна редактируемая колонка не
  отображается у текущего пользователя — а состав колонок у него свой
  ([[entity-grid-options]]).
- **`isAllSelected()` — про флажок, а не про фактический набор строк.** При включённой постраничной
  навигации «выбрано всё» не означает «выбраны все записи выборки»: групповое действие должно это
  различать, иначе операция уедет не на те данные.
- Реестр — надёжный способ добраться до объекта; тот же приём работает для фильтра
  ([[entity-bx-main-filter]]) и карточки CRM ([[recipe-crm-card-editor-js-access]]).

## Связанное
- [[entity-grid-component]] — серверная часть

[← Шаблоны и вёрстка](_index-templates-design.md)
