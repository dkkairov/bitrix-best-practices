---
title: "Компонент bitrix:main.ui.grid"
type: entity
module: templates-design
edition: box
status: verified
provenance: documented
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): UI / Таблицы — Обзор, Панель действий, Публичная часть; примеры книги ≈2023, без проверки на стенде"
tags: [ui, грид, компонент, пагинация, групповые-действия]
sources: ["[[source-devbook-ui]]"]
related: ["[[concept-ui-subsystem]]", "[[entity-grid-options]]", "[[entity-bx-main-grid]]", "[[entity-filter-component]]", "[[recipe-custom-list-page-filter-grid]]"]
aliases: ["bitrix24-grid-component"]
updated: "2026-09-21"
---

# Компонент `bitrix:main.ui.grid`

**Что это:** штатное табличное представление продукта
([Таблицы → Обзор](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tablicy/Obzor.html)).
Своя страница-список целиком — [[recipe-custom-list-page-filter-grid]].

> **Исправлено 2026-09-21 при сверке с книгой.** Раньше «действиями строки» назывались
> `ACTIONS_LIST`/`ACTIONS`. По книге **меню строки задаётся в каждой строке ключом `actions`**, а
> `ACTIONS_LIST`/`ACTIONS` книга относит к панели действий. Добавлены описание колонок и строк,
> сворачиваемые строки и грид, загружаемый через AJAX. Атрибуция исправлена.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | компонент |
| Модуль | `main` |
| Файлы | `/bitrix/components/bitrix/main.ui.grid/` |
| Обязательные параметры | `GRID_ID`, `COLUMNS` (или устаревший `HEADERS`), `ROWS` |

## Минимальный вызов

```php
$APPLICATION->IncludeComponent('bitrix:main.ui.grid', '', [
    'GRID_ID' => 'MY_GRID',
    'COLUMNS' => [
        ['id' => 'ID',   'name' => 'ID',  'sort' => 'ID',   'default' => true],
        ['id' => 'NAME', 'name' => 'Имя', 'sort' => 'NAME', 'default' => true],
    ],
    'ROWS' => [[
        'id'      => 'r1',
        'columns' => ['ID' => '1', 'NAME' => 'Иванов'],       // что вывести (HTML)
        'data'    => ['ID' => 1, 'NAME' => 'Иванов'],         // исходные данные, в т.ч. для правки в строке
        'actions' => [                                        // меню строки («бургер»)
            ['text' => 'Открыть', 'onclick' => 'BX.SidePanel.Instance.open("/path/1/")'],
        ],
    ]],
    'AJAX_MODE'           => 'Y',
    'AJAX_OPTION_JUMP'    => 'N',
    'AJAX_OPTION_HISTORY' => 'N',
]);
```

## Колонки и строки

- Колонка: `id`, `name`, `sort`, `default`. **Без `default` колонка по умолчанию скрыта.**
- Строка: `columns` — значения для вывода; `data` — исходные данные (нужны, например, для правки в
  строке); `editableColumns` — какие колонки редактируются; `actions` — меню строки.
- В полном примере книги строки передают только `data` (с ключами `~…`), хотя по таблице параметров
  вывод идёт из `columns` — при своей реализации проверить, что показывает грид.

## Навигация

| Параметр | Назначение |
|---|---|
| `TOTAL_ROWS_COUNT` / `TOTAL_ROWS_COUNT_HTML` | счётчик; второй — когда считаем асинхронно |
| `AJAX_ID` | идентификатор для AJAX (`\CAjax::GetComponentID()`) |
| `NAV_PARAM_NAME`, `CURRENT_PAGE` | имя URL-параметра страницы и текущая страница |
| `NAV_STRING`, `NAV_OBJECT` | готовый HTML пагинации либо `PageNavigation` / `CDBResult` |
| `PAGE_SIZES`, `DEFAULT_PAGE_SIZE` | варианты «строк на странице», по умолчанию 20 |
| `SHOW_MORE_BUTTON`, `ENABLE_NEXT_PAGE` | ленивая загрузка «Загрузить ещё» |

## Действия

| Что | Где задаётся |
|---|---|
| Меню строки («бургер») | ключ `actions` **в каждой строке** `ROWS`; показывать ли меню — `SHOW_ROW_ACTIONS_MENU` |
| Групповые действия над отмеченными строками | **`ACTION_PANEL`** (группы → элементы; типы, действия и готовые элементы — классы `\Bitrix\Main\Grid\Panel\…`) |
| `ACTIONS_LIST` / `ACTIONS` | в таблице параметров книги отнесены к панели действий; в новом коде — `ACTION_PANEL` |

Подробно о панели: [Панель действий](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tablicy/Panel_dejstvij.html)
(классическая панель и JS-панель `ui.actionpanel`; `ONCHANGE`-цепочки из
`\Bitrix\Main\Grid\Panel\Actions`, готовые элементы `\Bitrix\Main\Grid\Panel\Snippet`).

## Термины

**Бургер** — контекстное меню строки. **Пресет** — персональные настройки порядка и ширины
столбцов ([[entity-grid-options]]). **Панель действий** — групповые операции.

## Подводные камни

- **`GRID_ID` участвует в хранении пользовательских настроек** — сменив его, обнулите пресеты
  пользователей.
- `HEADERS` — устаревшее имя параметра; в новом коде `COLUMNS`.
- **Несколько гридов на странице** — у каждого свои имена URL-параметров сортировки (`vars` в
  [[entity-grid-options|Grid\Options]]), иначе возможна сортировка по несуществующему полю вплоть до
  фатальной ошибки.
- **`ENABLE_COLLAPSIBLE_ROWS` автор книги советует не использовать** — ломается пагинация.
- **Грид, вставленный через AJAX** (вкладка, слайдер), теряет пагинацию, настройки и размер
  страницы; лечится обработчиком JS-события `Grid::beforeRequest` — [[entity-bx-main-grid]].
- Связка с фильтром — через `GRID_ID` в параметрах [[entity-filter-component|фильтра]], а не
  наоборот.

## Связанное
- [[entity-grid-options]] — серверные настройки пользователя
- [[entity-bx-main-grid]] — управление гридом из JS
- [[recipe-custom-list-page-filter-grid]] — сборка страницы-списка

[← Шаблоны и вёрстка](_index-templates-design.md)
