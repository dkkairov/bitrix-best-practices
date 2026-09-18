---
title: "\\Bitrix\\UI\\Toolbar — Manager, Toolbar, Facade"
type: entity
module: templates-design
edition: box
status: verified
provenance: documented
verified: "2026-06-01 / документация модуля ui (dev.1c-bitrix.ru)"
tags: [ui, тулбар, класс, кнопки, фасад]
sources: []
related: ["[[concept-ui-subsystem]]", "[[entity-ui-button]]", "[[entity-filter-component]]"]
aliases: ["bitrix24-toolbar"]
updated: "2026-09-18"
---

# `\Bitrix\UI\Toolbar\…`

**Что это:** API шапки страницы. Три класса: `Manager` (реестр), `Toolbar` (экземпляр) и
`Facade\Toolbar` (статический фасад для системного тулбара — то, чем пользуются в 90 % случаев).

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | классы |
| Модуль | `ui` — нужен `Loader::includeModule('ui')` |
| ID системного тулбара | `Toolbar::DEFAULT_ID` = `default-toolbar` |

## Фасад — рекомендуемый путь

```php
use Bitrix\UI\Toolbar\Facade\Toolbar;
use Bitrix\UI\Toolbar\ButtonLocation;

Toolbar::addFilter($filterOptions);                     // параметры bitrix:main.ui.filter
Toolbar::addButton($button, ButtonLocation::RIGHT);     // массив или объект Button
Toolbar::addFavoriteStar();
Toolbar::deleteFavoriteStar();
Toolbar::hasFavoriteStar();
```

Фасад работает **только с системным тулбаром**. Для своего — через `Manager`.

## Manager и экземпляр

```php
$manager = \Bitrix\UI\Toolbar\Manager::getInstance();
$toolbar = $manager->createToolbar($id, ['filter' => $filterOptions]);
$toolbar = $manager->getToolbarById($id);
```

Методы экземпляра: `addBeforeTitleHtml()` / `addAfterTitleHtml()` / `addRightCustomHtml()` и парные
геттеры; `deleteButtons(\Closure $fn)` — убрать кнопки, для которых замыкание вернуло `true`;
`shuffleButtons(\Closure $fn, ButtonLocation $loc)` — переставить порядок внутри локации.

`ButtonLocation`: `RIGHT` (по умолчанию) и `AFTER_TITLE`.

## Подводные камни

- **Нужен `Loader::includeModule('ui')`** — тулбар и кнопки живут в отдельном модуле, в отличие
  от фильтра и грида ([[concept-ui-subsystem]]).
- **Тулбар не появится, если непусты буферы отложенных функций** `pagetitle`, `inside_pagetitle`,
  `in_pagetitle` — включится «устаревший» вид шапки.
- **Вне шаблона Bitrix24 компонент тулбара нужно вызывать явно.**
- Тулбар занимает зоны `above_pagetitle` и `below_pagetitle` — ваши собственные отложенные функции
  для этих зон будут конкурировать с ним.
- `deleteButtons()` — способ убрать чужую кнопку, не трогая чужой код; но он завязан на признаки
  кнопки, которые могут измениться при обновлении.

## Связанное
- [[concept-ui-subsystem]] — общая картина UI
- [[entity-ui-button]] — что кладут в `addButton`

[← Шаблоны и вёрстка](_index-templates-design.md)
