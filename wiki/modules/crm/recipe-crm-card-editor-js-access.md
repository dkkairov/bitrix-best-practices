---
title: "Карточка CRM из JS: редактор лежит в BX.Crm.EntityEditor.items, а не в BX.UI"
type: recipe
module: crm
edition: box
status: verified
provenance: empirical
verified: "2026-09-15 / коробка: main 26.700, crm 26.800; проверено в браузере на карточке смарт-процесса"
tags: [crm, smart-process, javascript, entity-editor, слайдер, канбан]
sources: []
related: ["[[recipe-crm-hide-card-block-js]]", "[[pattern-crm-timeline-client-side]]", "[[entity-smart-process]]", "[[concept-platform-reverse-engineering]]"]
aliases: ["bitrix24-crm-card-editor-registry"]
updated: "2026-09-18"
---

# Карточка CRM из JS: где взять редактор и модель

**Результат:** свой JS читает **значения** полей карточки смарт-процесса (ID варианта списка,
стадию, направление), а не текст с экрана.

## Предусловия
- Задача решается на клиенте осознанно: это «мягкое» вмешательство, уровень 3 в
  [[concept-change-invasiveness-hierarchy]]. Гарантия — только на сервере.

## Проблема

Очевидный путь — `BX.UI.EntityEditor.items` / `BX.UI.EntityEditor.getDefault()`. В карточке CRM
**он пуст**: `items = {}`, `getDefault()` → `null`. Код, который ищет только там, молча уходит на
запасное чтение текста, а текст врёт: у списка на экране «Решён», а в условии хранится ID варианта
`79`.

## Решение

`BX.Crm.EntityEditor` — наследник `BX.UI.EntityEditor` со **своим** статическим реестром. Карточка
CRM регистрируется там, с id вида `DYNAMIC_<entityTypeId>_details_C<направление>_editor`. Реестры —
разные объекты: `BX.Crm.EntityEditor.items !== BX.UI.EntityEditor.items`. Смотреть надо оба.

```js
function editorRegistries() {
    var list = [];
    if (window.BX && BX.Crm && BX.Crm.EntityEditor) list.push(BX.Crm.EntityEditor);
    if (window.BX && BX.UI && BX.UI.EntityEditor && list.indexOf(BX.UI.EntityEditor) === -1) {
        list.push(BX.UI.EntityEditor);
    }
    return list;
}

function editorFor(node) {                 // редактор, внутри которого лежит поле
    var found = null;
    editorRegistries().forEach(function (registry) {
        var items = registry.items || {};
        Object.keys(items).forEach(function (id) {
            try {
                var c = items[id] && items[id].getContainer && items[id].getContainer();
                if (!found && c && c.contains(node)) found = items[id];
            } catch (e) {}
        });
    });
    return found;
}
```

Что отдаёт модель (`editor._model.getField(name)`):

| Поле | Значение |
|---|---|
| Пользовательский список | `{VALUE: "79", SIGNATURE: "…", IS_EMPTY: false}` |
| Пользовательская строка | `{VALUE: "текст", SIGNATURE: "…", IS_EMPTY: false}` |
| `STAGE_ID` | `"DT1188_38:SUCCESS"` |
| `CATEGORY_ID` | `"38"` — строкой |

Режим поля: `editor.getControlById(name).getMode()`; константы одинаковы в
`BX.UI.EntityEditorMode` и `BX.Crm.EntityEditorMode`: `edit = 1`, `view = 2`.

## Подводные камни

- **Карточка живёт в iframe слайдера.** Верхнее окно — канбан или список, редактора там нет.
  Скрипт подключается в документ iframe; проверять из консоли — через
  `document.querySelector('iframe').contentWindow`.
- **Смарт-процесс, вынесенный в свой раздел,** открывает карточку по адресу
  `/page/<раздел>/<страница>/type/<id>/details/<элемент>/`, а не `/crm/type/<id>/details/…` — и в
  слайдере, и прямой ссылкой. Серверная проверка «это страница карточки» по одному `/crm/type/`
  свой JS не подключит. См. [[recipe-custom-left-menu-section]].
- **Пустые поля в режиме просмотра Bitrix прячет сам**: блок получает класс
  `ui-entity-editor-content-block-click-empty` и `display: none`. Если своё правило делает такое
  поле обязательным, показать его надо явно — иначе человек не видит, что заполнять.
- **Переключение поля на ввод перерисовывает внутренности** (`switchToSingleEditMode()`): атрибуты
  на внешнем блоке `[data-cid]` остаются, а вставленные в заголовок элементы (звёздочка,
  подсказка) пропадают. Проверять наличие своих элементов в DOM, а не флаг на блоке.
- **Контролы переключаются асинхронно**: после `switchToSingleEditMode()` поля ввода появляются не
  в том же тике.
- **Сумма — составной блок `OPPORTUNITY_WITH_CURRENCY`**, отдельного `[data-cid="OPPORTUNITY"]`
  нет. Читать `input[name="OPPORTUNITY"]`; вводить — в видимое поле, Bitrix сам перенесёт в
  скрытое. Контакт и компания — блок `CLIENT`.
- **Флажки в трёх видах:** в модели `"Y"`/`"N"`, на сервере `true`/`false`, в форме скрытое `"0"`
  плюс галочка `"1"`. Без приведения `"N"` и `"0"` выглядят как «заполнено».
- **Полоса стадий сохраняет только стадию.** Клик по `.ui-stageflow-stage-item` шлёт
  `crm.controller.item.update` с одним `stageId`; несохранённые значения полей в запрос не
  попадают и теряются. Значит, правило, зависящее от значения поля, при таком переносе считается
  по **сохранённым** данным. У обёртки `.ui-stageflow-stage` есть `data-stage-id`.
- **Признак «карточка в режиме изменения»** — режим редактора и полей (`editor.getMode()`,
  `editor.getControlById(cid).getMode()`), а **не** панель `.ui-entity-section-control-edit-mode`:
  она остаётся в разметке и после сохранения.
- **Понять, сохранили или отменили,** надёжнее всего сравнением слепка значений модели до и после.
  Подмена `editor.onSaveSuccess` не срабатывает (ядро держит ссылку на метод, полученную заранее),
  слежка за кнопками ненадёжна: панель одна на несколько разделов, а синтетический клик ядро
  игнорирует.
- **Скрытая вкладка браузера тормозит таймеры** (в Chrome — до минуты): фоновый
  `setTimeout`-пересчёт может не успеть; из автотестов звать пересчёт напрямую.

## Как проверить смену стадии в канбане без мышки

Перетаскивание в скрытом окне браузера не срабатывает. Тот же серверный путь вызывается через
объект канбана:

```js
const grid = BX.CRM.Kanban.Grid.getInstance();
grid.ajax(
    { action: 'status', entity_id: 5, prev_entity_id: 0, status: 'DT1188_38:UC_EXAMPLE' },
    data => console.log(data),
    err => console.error(err)
);
```

`grid.moveItem()` двигает карточку только на экране и запрос не шлёт; `grid.onItemMoved()` без
контекста перетаскивания падает.

## Альтернативы

| Подход | Почему нет |
|---|---|
| Только `BX.UI.EntityEditor` | в карточке CRM пуст |
| Текст из DOM | возвращает подпись вместе со значением и название варианта вместо ID |
| Событие `BX.Crm.EntityEditor:onInit` и запомнить экземпляр | работает, но скрипт, подключённый после инициализации, событие пропустит; реестр надёжнее |

## Связанное
- [[recipe-crm-hide-card-block-js]] — соседний приём работы с карточкой из JS
- [[concept-platform-reverse-engineering]] — как такие реестры находят

[← CRM](_index-crm.md)
