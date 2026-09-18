---
title: "\\Bitrix\\UI\\Buttons\\Button"
type: entity
module: templates-design
edition: box
status: verified
provenance: documented
verified: "2026-06-01 / документация модуля ui, раздел кнопок (dev.1c-bitrix.ru)"
tags: [ui, кнопки, класс, адаптивность, меню]
sources: []
related: ["[[entity-toolbar]]", "[[concept-ui-subsystem]]"]
aliases: ["bitrix24-button-class"]
updated: "2026-09-18"
---

# `\Bitrix\UI\Buttons\Button`

**Что это:** стандартная кнопка продукта. Рядом — `Icon`, `Color`, `Size`, `JsHandler`,
`ButtonAttributes`.

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

Имена ключей массива и сеттеров совпадают — переписывать с одной формы на другую дёшево.

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

Методы состояния: `setDisabled(bool)`, `setClocking(bool)` (иконка часов — долгая операция),
`setWaiting(bool)` (загрузчик), `getDataSet()`.

## Подводные камни

- **Кнопка без иконки не схлопывается на узком экране** и пишет предупреждение. Тулбар на
  мобильном схлопывает кнопки справа налево, оставляя только иконку. Лечится
  `dataset['toolbar-collapsed-icon']` (константа `Icon::*` или CSS-класс).
- **`click`, `onclick` и `events.click` — три способа задать одно и то же.** Указывать нужно ровно
  один, иначе поведение непредсказуемо.
- Для `aria-*` и прочих атрибутов — `$button->getAttributeCollection()`.

## Связанное
- [[entity-toolbar]] — куда кнопка добавляется

[← Шаблоны и вёрстка](_index-templates-design.md)
