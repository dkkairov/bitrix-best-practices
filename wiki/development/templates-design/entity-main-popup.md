---
title: "main.popup — всплывающие окна и меню"
type: entity
module: templates-design
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / коробка в Docker, main 26.750.0: расширение /bitrix/js/main/popup на месте; текст — документация фреймворка (docs.1c-bitrix.ru, «Всплывающие окна и меню main.popup»)"
tags: [ui, popup, меню, js, интерфейс]
sources: []
related: ["[[entity-main-sidepanel]]", "[[concept-ui-system-components]]", "[[entity-ui-button]]", "[[concept-js-extensions]]"]
aliases: []
updated: "2026-09-24"
---

# `main.popup` — всплывающие окна и меню

**Что это:** базовый механизм всплывающих окон и выпадающих меню интерфейса. На нём стоят меню
кнопок, контекстные меню грида и половина диалогов портала.

```php
\Bitrix\Main\UI\Extension::load('main.popup');
```

| Класс | Псевдоним `BX.*` | Для чего |
|---|---|---|
| `Popup` | `BX.PopupWindow` | всплывающее окно |
| `PopupManager` | `BX.PopupWindowManager` | реестр окон по `id` |
| `Menu` | `BX.PopupMenuWindow` | выпадающее меню |
| `MenuManager` | `BX.PopupMenu` | реестр меню |

## Окно

```javascript
const popup = new BX.PopupWindow({
    content: 'Текст или DOM-элемент',
    bindElement: button,
    closeByEsc: true,
    autoHide: true,
    closeIcon: true,
    width: 400,
    buttons: [ /* кнопки */ ],
});
popup.show();
```

Полезные параметры: `bindOptions` (например, `position: 'top'`), `angle` — «хвостик» к элементу
привязки, `overlay`, `animation` (`fading`, `scale`, `false`), `darkMode`.
События жизненного цикла: `onInit`, `onShow`, `onAfterClose`, `onDestroy`.

## Меню

```javascript
const menu = new BX.PopupMenuWindow({
    id: 'vendor-actions',
    bindElement: button,
    items: [
        { text: 'Изменить', onclick: () => {} },
        { delimiter: true },
        { text: 'Удалить', onclick: () => {}, disabled: !canDelete },
        { text: 'Ещё', items: [ /* вложенные пункты */ ] },
    ],
});
menu.show();
```

Пункты меняются на лету: `addMenuItem()`, `removeMenuItem()`, `getMenuItem()`.

## Что выбрать

| Задача | Инструмент |
|---|---|
| подтверждение, короткая форма, подсказка у кнопки | `main.popup` |
| отдельная страница, карточка, длинная форма | слайдер ([[entity-main-sidepanel]]) |
| типовой диалог в новом стиле | `ui.system.dialog` ([[concept-ui-system-components]]) |
| меню действия у кнопки | `ui.buttons` с меню ([[entity-ui-button]]) |

## Ловушки

- **`id` обязателен только при создании через менеджер** — но без него не получится найти и закрыть
  окно из другого места.
- **`autoHide: true` закрывает окно по клику вне его** — для форм это потеря введённого.
- **Окно живёт в DOM страницы**, а не слайдера: открытое из iframe оно окажется внутри панели, и
  при её закрытии исчезнет вместе с ней.
- **Своя вёрстка вместо `ui.system.*`** быстро расходится со стилем портала после обновления.

## Связанные страницы
- [[entity-main-sidepanel]] — когда нужна целая страница
- [[concept-ui-system-components]] — системные компоненты нового стиля
- [[entity-ui-button]] — кнопки и их меню

[← Шаблоны и дизайн](_index-templates-design.md)
