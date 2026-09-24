---
title: "Системные компоненты UI: ui.system.*, иконки, доступность"
type: concept
module: templates-design
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / коробка в Docker, main 26.750.0: в /bitrix/js/ui/system лежат alert, checkbox, chip, dialog, highlighter, input, label, menu, popover, radiobutton, skeleton, typography; в /bitrix/js/ui/icon-set — наборы actions, animated, contact-center, crm, disk, editor, main, outline, small-outline, social, solid, special; расширение /bitrix/js/ui/a11y на месте; текст — документация фреймворка (docs.1c-bitrix.ru, разделы «Системные…», «Иконки», «ui.a11y»)"
tags: [ui, компоненты, иконки, типографика, доступность, a11y]
sources: []
related: ["[[concept-ui-subsystem]]", "[[entity-main-popup]]", "[[concept-js-extensions]]", "[[entity-ui-button]]"]
aliases: []
updated: "2026-09-24"
---

# Системные компоненты UI: `ui.system.*`, иконки, доступность

**TL;DR:** у платформы есть набор готовых элементов интерфейса в актуальном стиле. Пока мы верстаем
«свои» кнопки и диалоги, интерфейс расходится с порталом после каждого обновления; системные
компоненты обновляются вместе с ним.

## Семейство `ui.system.*`

На стенде (`/bitrix/js/ui/system/`): `alert`, `checkbox`, `chip`, `dialog`, `highlighter`, `input`,
`label`, `menu`, `popover`, `radiobutton`, `skeleton`, `typography`.

| Компонент | Когда |
|---|---|
| `alert` | сообщение об ошибке или предупреждение в интерфейсе |
| `dialog` | типовое модальное окно с кнопками |
| `input`, `label`, `checkbox`, `radiobutton` | поля формы в стиле портала |
| `chip` | тег, выбранное значение, фильтр-«таблетка» |
| `menu`, `popover` | меню и всплывающая подсказка нового поколения |
| `skeleton` | заглушка на время загрузки вместо «прыгающего» контента |
| `highlighter` | подсветка совпадений в тексте |
| `typography` | системные стили текста |

Подключаются как обычные расширения: `\Bitrix\Main\UI\Extension::load('ui.system.dialog')`
([[concept-js-extensions]]).

## Иконки: `ui.icon-set`

```php
\Bitrix\Main\UI\Extension::load(['ui.icon-set.api.core', 'ui.icon-set.outline']);
```

```javascript
import { Icon, Outline } from 'ui.icon-set.api.core';
import 'ui.icon-set.outline';

new Icon({ icon: Outline.CHECK_L, size: 24, color: '#2fc6f6' }).renderTo(container);
```

Разметкой — тоже можно: `<div class="ui-icon-set --check-l"></div>` плюс CSS-переменная размера.
Есть и компонент для Vue (`ui.icon-set.api.vue`, `<BIcon :name="Outline.CHECK_L" />`).

Наборы на стенде: `actions`, `animated`, `contact-center`, `crm`, `disk`, `editor`, `main`,
`outline`, `small-outline`, `social`, `solid`, `special`. **Каждый набор — отдельное CSS-расширение**,
которое нужно подключить, иначе иконка не отрисуется. Размер по умолчанию 24 px, цвет задаётся
значением или дизайн-токеном.

## Доступность: `ui.a11y`

```javascript
import { FocusTrap, FocusZone, LiveAnnouncer } from 'ui.a11y';
```

| Класс | Задача |
|---|---|
| `FocusTrap` | удерживает фокус внутри модального окна |
| `FocusZone` | навигация стрелками, `Home`, `End` внутри блока |
| `FocusNavigator` | найти фокусируемый элемент и перевести на него фокус |
| `FocusMonitor` | вернуть фокус после закрытия панели или удаления элемента |
| `InputModalityTracker` | чем пользуется человек — клавиатурой, мышью, касанием |
| `InteractivityChecker` | виден ли элемент, доступен ли он, можно ли его сфокусировать |
| `LiveAnnouncer` | сообщение скринридеру без перевода фокуса |

Для внутреннего портала это не формальность: клавиатурная навигация в своей форме — первое, о чём
спрашивают опытные пользователи, а `FocusTrap` в своём модальном окне занимает три строки.

## Правила

- **Сначала ищем готовый компонент**, потом верстаем свой: `ui.system.*`, `ui.buttons`
  ([[entity-ui-button]]), `main.popup` ([[entity-main-popup]]), грид и фильтр
  ([[concept-ui-subsystem]]).
- **Иконки не рисуем своими SVG** без нужды — в наборах уже есть почти всё, и они меняются вместе
  с дизайном портала.
- **Подключаем ровно нужные расширения.** Загрузить весь `ui.icon-set` ради одной иконки — лишние
  сотни килобайт на каждой странице.
- **Своя вёрстка формы = долг.** После смены темы портала она выглядит чужой, и это замечает
  заказчик, а не разработчик.

## Связанные страницы
- [[concept-ui-subsystem]] — тулбар, фильтр, грид
- [[entity-main-popup]], [[entity-main-sidepanel]] — окна и панели
- [[concept-js-extensions]] — как всё это подключается

[← Шаблоны и дизайн](_index-templates-design.md)
