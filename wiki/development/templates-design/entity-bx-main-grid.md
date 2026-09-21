---
title: "BX.Main.gridManager — JS-API грида"
type: entity
module: templates-design
edition: box
status: verified
provenance: mixed
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): UI / Таблицы — Публичная часть, Панель действий; без проверки на стенде"
tags: [ui, грид, javascript, inline-редактирование, события]
sources: ["[[source-devbook-ui]]"]
related: ["[[entity-grid-component]]", "[[entity-bx-main-filter]]", "[[recipe-crm-card-editor-js-access]]", "[[recipe-custom-list-page-filter-grid]]"]
aliases: ["bitrix24-grid-bx-main"]
updated: "2026-09-21"
---

# `BX.Main.gridManager`

**Что это:** управление гридом из браузера. Реестр гридов страницы плюс API каждого экземпляра
([Таблицы → Публичная часть](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tablicy/Publicnaa_cast.html)).

> **Сверено с книгой 2026-09-21.** Исправлено: `prependRowEditor()` без видимой редактируемой
> колонки добавляет **пустую строку**, а не «молча ничего не делает». Добавлены события грида и
> лечение грида, загруженного через AJAX. Утверждение про `isAllSelected()` переписано по книге.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | JS-объекты |
| Модуль | `main` |

```js
BX.Main.gridManager.getInstanceById(id);
BX.Main.gridManager.data;             // все гриды страницы
BX.Main.gridManager.reload(id, url);  // перезагрузить, опционально с другого адреса
```

## Экземпляр

**Строка ввода (inline-редактирование):** `grid.prependRowEditor()`, `grid.appendRowEditor()`.
Нужен редактируемый грид с хотя бы одной редактируемой **отображаемой** колонкой — иначе добавится
пустая строка.

**Прелоадер:** `grid.tableFade()` / `grid.tableUnfade()`.

**Работа с выделением** — через `grid.getRows()`:

| Метод | Назначение |
|---|---|
| `getSelectedIds()` | ID отмеченных строк |
| `getSelected(false)` | объекты отмеченных |
| `isAllSelected()` | отмечены ли все строки на странице |
| `selectAll()` / `unselectAll()` | отметить / снять отметку со всех строк |

После программной смены отметок — `grid.adjustCheckAllCheckboxes()`, чтобы синхронизировать
чекбокс «все на странице».

## События

| Событие | Когда |
|---|---|
| `Grid::disabled` / `Grid::enable` | грид заблокирован / разблокирован |
| `Grid::beforeRequest` | перед AJAX-запросом грида: можно отменить запрос (`cancelRequest`) или подменить `url` |

## Подводные камни

- **Грид, вставленный на страницу через AJAX** (вкладка карточки, слайдер), теряет пагинацию,
  настройки и размер страницы. По книге лечится обработчиком `Grid::beforeRequest`, который по
  `gridId` подставляет правильный `url` (порядок аргументов обработчика — проверить на стенде).
- **«Для всех» ≠ «все на странице».** Книга различает флажок «Для всех» на панели действий (серверный
  флаг — `(new \Bitrix\Main\Grid\Panel\Snippet())->getForAllCheckbox()`) и чекбокс «все строки на
  странице» (`isAllSelected()`). Групповое действие должно явно учитывать, какой из них выбран, иначе
  операция уйдёт не на тот набор (вывод команды).
- Реестр — надёжный способ добраться до объекта; тот же приём работает для фильтра
  ([[entity-bx-main-filter]]) и карточки CRM ([[recipe-crm-card-editor-js-access]]).

## Связанное
- [[entity-grid-component]] — серверная часть
- [[recipe-custom-list-page-filter-grid]] — сборка страницы-списка

[← Шаблоны и вёрстка](_index-templates-design.md)
