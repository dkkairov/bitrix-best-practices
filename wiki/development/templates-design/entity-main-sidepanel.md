---
title: "main.sidepanel — боковая панель (слайдер)"
type: entity
module: templates-design
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / коробка в Docker, main 26.750.0: расширение /bitrix/js/main/sidepanel на месте; текст — документация фреймворка (docs.1c-bitrix.ru, «Боковая панель main.sidepanel»)"
tags: [ui, слайдер, sidepanel, js, интерфейс]
sources: []
related: ["[[concept-ui-subsystem]]", "[[entity-main-popup]]", "[[concept-js-extensions]]", "[[recipe-crm-card-editor-js-access]]"]
aliases: []
updated: "2026-09-24"
---

# `main.sidepanel` — боковая панель (слайдер)

**Что это:** выезжающая справа панель, в которой Битрикс24 открывает карточки, формы и списки.
Пользователь воспринимает её как «окно», разработчик — как iframe с адресом.

**Зачем помнить:** свои страницы и формы правильно открывать слайдером, а не новой вкладкой — так
интерфейс остаётся привычным, а данные под панелью не теряются.

## Открыть

```javascript
BX.SidePanel.Instance.open('/vendor/order/edit/?ID=42', {
    width: 900,
    cacheable: false,
    allowChangeHistory: false,
    events: {
        onCloseComplete: () => { /* обновить список под панелью */ },
    },
});
```

Метод вернёт `true`, если панель начала открываться.

| Параметр | Смысл |
|---|---|
| `width` | ширина в пикселях |
| `startPosition` | сторона появления: `right`, `bottom`, `top` |
| `cacheable` | держать ли панель после закрытия (по умолчанию `true`) |
| `contentCallback` | своё содержимое без iframe |
| `requestMethod`, `requestParams` | открыть по `POST` с параметрами |
| `loader` | индикатор загрузки |
| `allowChangeHistory`, `allowChangeTitle` | менять ли адрес в строке браузера и заголовок |
| `animationDuration` | длительность анимации |

## События

`onOpenStart`, `onOpening`, `onOpen`, `onLoad` (iframe загрузился), `onOpenComplete`,
`onCloseStart`, `onClose`, `onCloseComplete`. В `onCloseStart` можно вызвать `denyAction()` —
и панель не закроется (например, есть несохранённые изменения).

## Управление и связь между панелями

| Метод | Что делает |
|---|---|
| `BX.SidePanel.Instance.close()` | закрыть верхнюю панель |
| `reload()` | перезагрузить верхнюю панель |
| `getTopSlider()`, `getSlider(url)` | получить панель |
| `postMessage(source, eventId, data)` | сообщение предыдущему слайдеру или странице |
| `postMessageTop(...)` | сообщение на основную страницу |

`postMessage` — штатный способ сказать списку «элемент сохранён, обнови строку», не перезагружая
всю страницу.

## Ловушки

- **`cacheable: true` по умолчанию.** Панель, открытая второй раз, может показать старое
  содержимое — для форм ставим `false`.
- **Внутри слайдера своя страница целиком.** Скрипты родителя недоступны напрямую: связь только
  через `postMessage` и события.
- **Вложенные панели** — обычная ситуация в Битрикс24; закрывая свою, не полагайтесь на то, что
  под ней основная страница, а не другой слайдер.
- **`allowChangeHistory: true`** меняет адрес: при перезагрузке страницы пользователь окажется на
  странице слайдера, а не там, откуда открыл.

## Связанные страницы
- [[entity-main-popup]] — попапы и меню
- [[concept-ui-subsystem]] — остальная UI-подсистема
- [[recipe-crm-card-editor-js-access]] — карточка CRM, которую обычно и открывают слайдером

[← Шаблоны и дизайн](_index-templates-design.md)
