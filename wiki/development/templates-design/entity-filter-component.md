---
title: "Компонент bitrix:main.ui.filter"
type: entity
module: templates-design
edition: box
status: verified
provenance: documented
verified: "2026-06-01 / документация модуля main, раздел фильтра (dev.1c-bitrix.ru)"
tags: [ui, фильтр, компонент, пресеты, грид]
sources: ["[[source-devbook-ui]]"]
related: ["[[concept-ui-subsystem]]", "[[entity-filter-field-adapter]]", "[[entity-filter-options]]", "[[entity-grid-component]]", "[[entity-custom-filter]]"]
aliases: ["bitrix24-filtr-component"]
updated: "2026-09-18"
---

# Компонент `bitrix:main.ui.filter`

**Что это:** визуальная часть фильтра — та самая строка «Фильтр и поиск» над списками продукта.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | компонент |
| Модуль | `main` (подключать ничего не нужно) |
| Файлы | `/bitrix/components/bitrix/main.ui.filter/` |
| Единственный обязательный параметр | `FILTER_ID` |

## Параметры

**Поля и пресеты**

| Параметр | Назначение |
|---|---|
| `FIELDS` | набор полей ([[entity-filter-field-adapter]]) |
| `FILTER` | конфигурации полей |
| `FILTER_ROWS` | какие поля сейчас на форме (`<id> => bool`); не передан — вычисляется |
| `FILTER_PRESETS` | системные пресеты (пользователь их не удалит) |
| `CURRENT_PRESET`, `COMMON_PRESETS_ID` | выбранный и основной пресет |
| `VALUE_REQUIRED`, `VALUE_REQUIRED_MODE` | обязательные параметры фильтра |

Системный пресет `default_filter` добавляется сам — переопределять не нужно.

**Связь с гридом:** `GRID_ID` — при указании действия фильтра обновляют
[[entity-grid-component|грид]].

**Поведение:** `ENABLE_LABEL`, `DISABLE_SEARCH`, `RESET_TO_DEFAULT_MODE` (по умолчанию `true`),
`ENABLE_ADDITIONAL_FILTERS` (опции вроде «Значение отсутствует»), `ENABLE_FIELDS_SEARCH`.

**Визуал:** `THEME` (`DEFAULT_FILTER`, `BORDER`, `ROUNDED`, `LIGHT`, `MUTED`),
`RENDER_FILTER_INTO_VIEW` и `RENDER_FILTER_INTO_VIEW_SORT` — отрисовка в область отложенного
вывода, `SETTINGS_URL`.

## Подводные камни

- **`FILTER_ID` уникален и участвует в хранении пользовательских настроек** — сменив его, вы
  обнулите сохранённые пресеты пользователей ([[entity-filter-options]]).
- Конфигурация собирается слиянием нескольких источников, приоритетный — параметр `CONFIG`:
  при неожиданном виде фильтра смотреть надо всю цепочку, а не только свой массив.
- Для новой разработки документация рекомендует не PHP-`Options`, а «свой фильтр» —
  [[entity-custom-filter]].

## Связанное
- [[concept-ui-subsystem]] — место фильтра в UI
- [[entity-filter-field-adapter]] — типы полей

[← Шаблоны и вёрстка](_index-templates-design.md)
