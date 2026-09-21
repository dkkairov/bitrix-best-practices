---
title: "\\Bitrix\\UI\\Toolbar — Manager, Toolbar, Facade"
type: entity
module: templates-design
edition: box
status: verified
provenance: mixed
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): UI — Тулбар, Кнопки; includeModule('ui') — практика команды"
tags: [ui, тулбар, класс, кнопки, фасад]
sources: ["[[source-devbook-ui]]"]
related: ["[[concept-ui-subsystem]]", "[[entity-ui-button]]", "[[entity-filter-component]]", "[[concept-deferred-functions-and-page-areas]]", "[[recipe-custom-list-page-filter-grid]]"]
aliases: ["bitrix24-toolbar"]
updated: "2026-09-21"
---

# `\Bitrix\UI\Toolbar\…`

**Что это:** API шапки страницы. Три класса: `Manager` (реестр, синглтон), `Toolbar` (экземпляр) и
`Facade\Toolbar` (статический фасад для системного тулбара — основной способ работы)
([Тулбар](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tulbar/Osnovnoe.html)).

> **Сверено с книгой 2026-09-21.** Исправлено: тулбар стоит **между** зонами `above_pagetitle` и
> `below_pagetitle`, а не занимает их; локаций кнопок три, а не две; второй аргумент
> `shuffleButtons` — значение константы, а не объект. Убрано «90 % случаев» (в книге числа нет);
> `includeModule('ui')` помечен как практика команды. Атрибуция исправлена.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | классы |
| Модуль | `ui` (практика команды — подключать `Loader::includeModule('ui')`) |
| ID системного тулбара | `Toolbar::DEFAULT_ID` = `default-toolbar` |
| Где на странице | между точками встройки `above_pagetitle` и `below_pagetitle` |

## Фасад — рекомендуемый путь

```php
use Bitrix\UI\Toolbar\Facade\Toolbar;
use Bitrix\UI\Toolbar\ButtonLocation;

Toolbar::addFilter($filterOptions);                         // параметры bitrix:main.ui.filter
Toolbar::addButton($button, ButtonLocation::AFTER_TITLE);   // массив или объект Button
Toolbar::addFavoriteStar();
Toolbar::deleteFavoriteStar();
Toolbar::hasFavoriteStar();
```

`ButtonLocation`: `AFTER_TITLE`, `AFTER_FILTER`; любое другое значение трактуется как `RIGHT`.

Фасад работает **только с системным тулбаром** (`default-toolbar`), и для своего HTML в нём методов
нет.

## Manager и экземпляр

```php
use Bitrix\UI\Toolbar\Facade\Toolbar;

$manager = \Bitrix\UI\Toolbar\Manager::getInstance();
$toolbar = $manager->getToolbarById(Toolbar::DEFAULT_ID);          // системный тулбар
$toolbar->addAfterTitleHtml('<span class="vendor-badge">Черновик</span>');

$own = $manager->createToolbar($id, ['filter' => $filterOptions]);  // свой тулбар; из опций читается только filter
```

Методы экземпляра: `addBeforeTitleHtml()` / `addAfterTitleHtml()` / `addRightCustomHtml()` и парные
геттеры; `deleteButtons(\Closure $fn)` — убрать кнопки (во всех локациях), для которых замыкание
вернуло строго `true`; `shuffleButtons(\Closure $fn, $buttonLocation)` — замыкание возвращает
переупорядоченный массив кнопок внутри локации (второй аргумент — значение константы
`ButtonLocation::*`).

## Подводные камни

- **HTML в `add…Html()` не экранируется** — книга требует передавать заранее безопасный HTML; всё
  пользовательское — через `htmlspecialcharsbx()`, иначе XSS.
- **Тулбар не появится, если непусты буферы отложенных функций** `pagetitle`, `inside_pagetitle`,
  `in_pagetitle` — включится «устаревший» вид шапки. Зоны `above_pagetitle` и `below_pagetitle`
  при этом **свободны** для своего вывода над и под тулбаром
  ([[concept-deferred-functions-and-page-areas]]).
- **Тулбар выводится, пока идёт буферизация**; вне шаблона Bitrix24 компонент тулбара нужно вызывать
  явно; в `bitrix:ui.sidepanel.wrapper` — параметр `'USE_UI_TOOLBAR' => 'Y'`.
- `deleteButtons()` — способ убрать чужую кнопку, не трогая чужой код; но он завязан на признаки
  кнопки, которые могут измениться при обновлении.

## Связанное
- [[concept-ui-subsystem]] — общая картина UI
- [[entity-ui-button]] — что кладут в `addButton`
- [[recipe-custom-list-page-filter-grid]] — тулбар с фильтром на своей странице

[← Шаблоны и вёрстка](_index-templates-design.md)
