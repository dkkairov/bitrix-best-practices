---
title: "UI-подсистема: тулбар, фильтр, грид, кнопки"
type: concept
module: templates-design
edition: box
status: verified
provenance: mixed
verified: "2026-09-22 / коробка в Docker, main 26.750.0: зоны шаблона bitrix24 (AIR) и безусловное подключение тулбара сверены по header.php; 2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Разработка / UI — все 13 страниц; примеры книги ≈2023, без проверки на стенде"
tags: [ui, тулбар, фильтр, грид, кнопки, шаблон, отложенные-функции]
sources: ["[[source-devbook-ui]]"]
related: ["[[concept-change-invasiveness-hierarchy]]", "[[concept-request-lifecycle]]", "[[recipe-custom-left-menu-section]]", "[[recipe-crm-card-editor-js-access]]", "[[recipe-custom-list-page-filter-grid]]", "[[concept-deferred-functions-and-page-areas]]"]
aliases: ["bitrix24-ui"]
updated: "2026-09-22"
---

# UI-подсистема

**TL;DR:** для большинства интерфейсных задач в продукте уже есть готовый компонент. Начинать
любую страницу списка или формы с вопроса «что из этого уже реализовано» — это уровень 1
[[concept-change-invasiveness-hierarchy|иерархии способов изменения]], и, по оценке автора
«Книги разработчика», знание штатных инструментов экономит до 30 % времени
([UI → Введение](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Vvedenie.html)).
Сборка целиком — [[recipe-custom-list-page-filter-grid]].

> **Сверено с книгой 2026-09-21.** Атрибуция исправлена (материал — из книги, не с dev.1c-bitrix.ru;
> оценки и оговорки — автора книги, а не «документации»). Исправлено: тулбар стоит **между** зонами
> `above_pagetitle`/`below_pagetitle`, а не занимает их; условие «только в шаблоне Bitrix24» относится
> к тулбару, а не ко всей подсистеме; «устаревшим» автор называет только получение значений через
> `Options::getFilter*()`.

## Что входит

| Подсистема | Namespace | Модуль |
|---|---|---|
| **Тулбар** — шапка страницы | `\Bitrix\UI\Toolbar\…` | `ui` |
| **Кнопки** | `\Bitrix\UI\Buttons\…` | `ui` |
| **Фильтр** — компонент и настройки пользователя | `\Bitrix\Main\UI\Filter\…` | `main` |
| **Свой фильтр** — объект фильтра и провайдеры данных | `\Bitrix\Main\Filter\…` | `main` |
| **Грид** — табличное представление | `\Bitrix\Main\Grid\…` | `main` |

«UI» — не один модуль: тулбар и кнопки живут в модуле `ui`, фильтр и грид — в главном модуле.
**Практика команды:** перед использованием тулбара и кнопок вызывать
`Loader::includeModule('ui')` (в книге этого нет — она считает `ui` неотъемлемой частью Bitrix24).

## Тулбар

Стандартная шапка страницы: слева направо — контент до заголовка (`addBeforeTitleHtml`), заголовок,
звезда «в избранное» (`addFavoriteStar`), контент после заголовка (`addAfterTitleHtml`), фильтр
(`addFilter`), кнопки (`addButton`), пользовательский контент справа (`addRightCustomHtml`).

По умолчанию тулбар отображается при трёх условиях
([условие применения](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tulbar/Osnovnoe.html#uslovie-primenenia)):

1. используется шаблон дизайна Bitrix24 — в других шаблонах компонент вызывается явно;
2. пусты буферы отложенных функций `pagetitle`, `inside_pagetitle`, `in_pagetitle` — иначе
   включается «устаревший» вид шапки. **Пункт относится к шаблону до AIR:** на коробке 26.750.0
   этих зон нет, тулбар подключается безусловно (стенд, 2026-09-22);
3. буферизация вывода ещё идёт (см. [[concept-request-lifecycle]]).

Тулбар выводится **между** зонами `above_pagetitle` и `below_pagetitle`; сами эти зоны свободны для
своего вывода над и под шапкой. Конфликтуют с тулбаром только `pagetitle*` (п. 2) —
[[concept-deferred-functions-and-page-areas]].

Компонент `bitrix:ui.sidepanel.wrapper` требует явного `'USE_UI_TOOLBAR' => 'Y'`.

## Фильтр

Компонент `bitrix:main.ui.filter`. Четыре понятия: **фильтр** (весь компонент), **поле**
(управляющий элемент по признаку), **пресет** (именованная группировка полей со значениями),
**конфигурация поля** (тип, обязательность, множественность).

Три уровня работы:

- **PHP, компонент:** параметры (`FILTER_ID`, поля, `FILTER_PRESETS`). Настройки пользователя лежат
  в `b_user_option` (`CATEGORY = 'main.ui.filter'`), доступ — через `\Bitrix\Main\UI\Filter\Options`.
- **Значения фильтра для выборки:** в новом коде — объект «своего фильтра»
  (`\Bitrix\Main\Filter\Filter::getValue()`, [[entity-custom-filter]]). `Options::getFilterLogic()`
  тоже отдаёт массив для `DataManager::getList()`, но автор книги называет этот путь демонстрационным
  или для устаревших систем
  ([получение фильтра](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Filtr/Filtry_polzovatela.html#polucenie-fil-tra)).
- **JS:** `BX.Main.Filter` в публичной части.

## Грид

Компонент `bitrix:main.ui.grid` — штатный табличный вывод.

```php
$APPLICATION->IncludeComponent('bitrix:main.ui.grid', '', [
    'GRID_ID' => 'MY_GRID',
    'COLUMNS' => [
        ['id' => 'ID',   'name' => 'ID',  'sort' => 'ID',   'default' => true],
        ['id' => 'NAME', 'name' => 'Имя', 'sort' => 'NAME', 'default' => true],
    ],
    'ROWS' => [
        ['id' => 'r1', 'columns' => ['ID' => '1', 'NAME' => 'Иванов']],
    ],
    'AJAX_MODE' => 'Y',
]);
```

Обязательны `GRID_ID`, `COLUMNS` (устаревший синоним — `HEADERS`) и `ROWS`; остальное — поведенческие
флаги, постраничная навигация и панель групповых действий.

Термины: **бургер** — контекстное меню строки (ключ `actions` в строке); **пресет** — персональные
настройки порядка и ширины столбцов (PHP-API `\Bitrix\Main\Grid\Options`); **панель действий** —
групповые операции над отмеченными строками (`ACTION_PANEL`); JS-API — `BX.Main.gridManager`.

## Почему важно при внедрении

Своя страница, собранная на этих компонентах, визуально не отличается от продукта и получает
бесплатно: пресеты, сохранение настроек пользователя, групповые действия, адаптив. Своя вёрстка
«похоже, но не то» — частый источник претензий заказчика к качеству доработки (опыт команды).

## Подводные камни
- **Вне шаблона Bitrix24 тулбар сам не выводится** — компонент `bitrix:ui.toolbar` вызывают явно;
  фильтр и грид подключаются явно в любом шаблоне.
- Не выводить ничего в `pagetitle`, `inside_pagetitle`, `in_pagetitle`: на старых коробках пропадёт
  тулбар, на новых вывод просто потеряется — зон нет
  ([[concept-deferred-functions-and-page-areas]]).
- Собственные страницы, выведенные в свой раздел меню, тоже должны использовать эти компоненты —
  см. [[recipe-custom-left-menu-section]].

## Связанные страницы
- [[recipe-custom-list-page-filter-grid]] — своя страница-список: фильтр, грид, тулбар
- [[concept-deferred-functions-and-page-areas]] — зоны страницы и отложенный вывод
- [[concept-request-lifecycle]] — буферизация, на которой стоит тулбар
- [[recipe-crm-card-editor-js-access]] — соседний слой: JS-API карточки CRM

[← Шаблоны и вёрстка](_index-templates-design.md)
