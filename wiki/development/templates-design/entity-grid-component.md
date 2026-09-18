---
title: "Компонент bitrix:main.ui.grid"
type: entity
module: templates-design
edition: box
status: verified
provenance: documented
verified: "2026-06-01 / документация модуля main, раздел таблиц (dev.1c-bitrix.ru)"
tags: [ui, грид, компонент, пагинация, групповые-действия]
sources: []
related: ["[[concept-ui-subsystem]]", "[[entity-grid-options]]", "[[entity-bx-main-grid]]", "[[entity-filter-component]]"]
aliases: ["bitrix24-grid-component"]
updated: "2026-09-18"
---

# Компонент `bitrix:main.ui.grid`

**Что это:** табличное представление — основа всех страниц списков продукта.

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
    'ROWS'      => [['id' => 'r1', 'columns' => ['ID' => '1', 'NAME' => 'Иванов']]],
    'AJAX_MODE' => 'Y',
]);
```

## Навигация

| Параметр | Назначение |
|---|---|
| `TOTAL_ROWS_COUNT` / `TOTAL_ROWS_COUNT_HTML` | счётчик; второй — когда считаем асинхронно |
| `AJAX_ID` | идентификатор для AJAX |
| `NAV_PARAM_NAME`, `CURRENT_PAGE` | имя URL-параметра страницы и текущая страница |
| `NAV_STRING`, `NAV_OBJECT` | готовый HTML пагинации либо `PageNavigation` / `CDBResult` |
| `PAGE_SIZES`, `DEFAULT_PAGE_SIZE` | варианты «строк на странице», по умолчанию 20 |
| `SHOW_MORE_BUTTON`, `ENABLE_NEXT_PAGE` | ленивая загрузка «Загрузить ещё» |

## Действия

`ACTIONS_LIST` (или `ACTIONS`) — действия строки; `SHOW_ROW_ACTIONS_MENU` / `ROW_ACTIONS` —
показывать ли «бургер»; **`ACTION_PANEL`** — панель групповых действий над отмеченными строками.

## Термины

**Бургер** — контекстное меню строки. **Пресет** — персональные настройки порядка и ширины
столбцов ([[entity-grid-options]]). **Панель действий** — групповые операции.

## Подводные камни

- **`GRID_ID` участвует в хранении пользовательских настроек** — сменив его, обнулите пресеты
  пользователей.
- `HEADERS` — устаревшее имя параметра; в новом коде `COLUMNS`.
- Если на странице несколько гридов, у каждого должны быть свои имена URL-параметров сортировки,
  иначе они перебьют друг друга ([[entity-grid-options]]).
- Связка с фильтром — через `GRID_ID` в параметрах [[entity-filter-component|фильтра]], а не
  наоборот.

## Связанное
- [[entity-grid-options]] — серверные настройки пользователя
- [[entity-bx-main-grid]] — управление гридом из JS

[← Шаблоны и вёрстка](_index-templates-design.md)
