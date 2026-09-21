---
title: "\\Bitrix\\UI\\Buttons\\Button"
type: entity
module: templates-design
edition: box
status: verified
provenance: documented
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): UI — Кнопки"
tags: [ui, кнопки, класс, адаптивность, меню]
sources: ["[[source-devbook-ui]]"]
related: ["[[entity-toolbar]]", "[[concept-ui-subsystem]]"]
aliases: ["bitrix24-button-class"]
updated: "2026-09-21"
---

# `\Bitrix\UI\Buttons\Button`

**Что это:** стандартная кнопка продукта. Рядом — `Icon`, `Color`, `Size`, `JsHandler`,
`ButtonAttributes` ([Кнопки](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Knopki.html);
атрибуция и подводные камни уточнены при сверке с книгой 2026-09-21).

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | класс |
| Модуль | `ui` |
| Три эквивалентные формы создания | массив, конструктор, цепочка сеттеров |

```php
// все три дают одно и то же
['link' => '/path/', 'text' => 'Открыть'];
new Button(['link' => '/path/', 'text' => 'Открыть']);
(new Button())->setText('Открыть')->setLink('/path/');
```

Многие ключи массива и сеттеры названы одинаково — переписывать с одной формы на другую обычно
дёшево (но не все — сверяйтесь с классом).

## Параметры

| Ключ | Назначение |
|---|---|
| `text` | подпись |
| `link` | адрес — кнопка перехода |
| `click` / `onclick` / `events.click` | JS-обработчик — кнопка действия (выбрать **один** из трёх) |
| `icon` | константа `Icon::*` |
| `classList` | массив CSS-классов, альтернатива `icon` |
| `dataset` | произвольные `data-*` |
| `color`, `size` | `Color::*`, `Size::*` |
| `noCaps` | не приводить текст к верхнему регистру |
| `round`, `dropdown` | скруглённая, с треугольником развёртывания |
| `menu` | выпадающее меню |

Обработчик — `\Bitrix\UI\Buttons\JsHandler` (имя JS-функции и контекст). JS-функция получает
`(button, event)`; у JS-объекта кнопки есть методы состояния `setDisabled(bool)`, `setClocking(bool)`
(иконка часов — долгая операция), `setWaiting(bool)` (загрузчик), `getDataSet()`.

## Подводные камни

- **Кнопка без иконки не схлопывается** и пишет предупреждение в консоль. При нехватке места тулбар
  схлопывает кнопки справа, оставляя только иконку. Лечится `dataset['toolbar-collapsed-icon']`
  (константа `Icon::*` или CSS-класс).
- **`click`, `onclick` и `events['click']` — три способа задать обработчик**, и приоритет у них
  определённый: `events['click']` перекрывает `onclick`, тот — `click`. Задавайте один, чтобы не
  гадать.
- **Контекст `JsHandler` пока не обрабатывается** (по книге — «в данный момент»); если JS-функции нет,
  в консоли появится предупреждение `BX.UI.ButtonManager.createFromNode`.
- Для `aria-*` и прочих атрибутов — `$button->getAttributeCollection()`.

## Связанное
- [[entity-toolbar]] — куда кнопка добавляется

[← Шаблоны и вёрстка](_index-templates-design.md)
