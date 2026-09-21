---
title: "Компонент bitrix:main.ui.filter"
type: entity
module: templates-design
edition: box
status: verified
provenance: documented
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): UI / Фильтр — Обзор, Фильтры пользователя, Свой фильтр; без проверки на стенде"
tags: [ui, фильтр, компонент, пресеты, грид]
sources: ["[[source-devbook-ui]]"]
related: ["[[concept-ui-subsystem]]", "[[entity-filter-field-adapter]]", "[[entity-filter-options]]", "[[entity-grid-component]]", "[[entity-custom-filter]]", "[[recipe-custom-list-page-filter-grid]]"]
aliases: ["bitrix24-filtr-component"]
updated: "2026-09-21"
---

# Компонент `bitrix:main.ui.filter`

**Что это:** визуальная часть фильтра — та самая строка «Фильтр и поиск» над списками продукта
([Фильтр → Обзор](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Filtr/Obzor.html)).

> **Сверено с книгой 2026-09-21.** Отмечено противоречие книги про `FIELDS`/`FILTER`; добавлены
> особенности пресетов и предзаполнение фильтра запросом; рекомендация «своего фильтра» —
> автора книги, а не «документации». Атрибуция исправлена.

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
| `FIELDS` / `FILTER` | описания полей ([[entity-filter-field-adapter]]) — **книга противоречит сама себе**, см. ниже |
| `FILTER_ROWS` | какие поля сейчас на форме (`<id> => bool`); не передан — вычисляется |
| `FILTER_PRESETS` | системные пресеты (пользователь их не удалит) |
| `CURRENT_PRESET`, `COMMON_PRESETS_ID` | выбранный и основной пресет |
| `VALUE_REQUIRED`, `VALUE_REQUIRED_MODE` | обязательные параметры фильтра |

**`FIELDS` или `FILTER`.** Таблица параметров книги называет оба ключа (а `FILTER_ROWS` описывает
дважды), введение к «Своему фильтру» требует `FIELDS`, а единственный рабочий пример передаёт поля
в `FILTER`; в JS они видны как `params.FIELDS`. Какой ключ компонент реально читает — **проверить на
стенде или по официальной документации** (ссылку на неё даёт книга). До проверки держать один ключ,
как в рабочем примере, — [[recipe-custom-list-page-filter-grid]].

Системный пресет `default_filter` добавляется сам — переопределять не нужно.

**Связь с гридом:** `GRID_ID` — при указании действия фильтра обновляют
[[entity-grid-component|грид]].

**Поведение:** `ENABLE_LABEL`, `DISABLE_SEARCH`, `RESET_TO_DEFAULT_MODE` (по умолчанию `true`),
`ENABLE_ADDITIONAL_FILTERS` (опции вроде «Значение отсутствует»), `ENABLE_FIELDS_SEARCH`.

**Визуал:** `THEME` (`\Bitrix\Main\UI\Filter\Theme`: `DEFAULT_FILTER`, `BORDER`, `ROUNDED`, `LIGHT`,
`MUTED`), `RENDER_FILTER_INTO_VIEW` и `RENDER_FILTER_INTO_VIEW_SORT` (по умолчанию 500) — отрисовка в
зону отложенного вывода ([[concept-deferred-functions-and-page-areas]]), `SETTINGS_URL`.

**Конфигурация** (`CONFIG`) собирается слиянием трёх источников: `config.json` в каталоге тем,
`config.json` выбранной темы и параметр `CONFIG`. Ключи `CONFIG`: `SEARCH`, `DEFAULT_PRESET`,
`FILTER_SHOW_DELAY`, `FILTER_CLOSE_DELAY`, `AUTOFOCUS`, `POPUP_BIND_ELEMENT_SELECTOR`, `POPUP_OFFSET_*`.

## Пресеты

- Пресеты **индивидуальны** для каждого пользователя и хранятся в `b_user_option`
  ([[entity-filter-options]]).
- Сохранённое **не пересчитывается**: правка `FILTER_PRESETS` в коде не дойдёт до пресетов, которые
  пользователь уже сохранил (вывод из книги — проверить); особенно заметно на полях с датами.
- Пресеты «тяжёлые»: кроме значений хранят подписи `*_label` и служебные подполя (`*_numsel`,
  `*_datesel` и т. п.).

## Предзаполнить фильтр извне

POST-запрос с `apply_filter=Y` и GET-параметр `filter_id` задают значения фильтра при открытии
страницы ([изменение фильтра через apply_filter](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Filtr/Obzor.html#izmenenie-fil-tra-cerez-apply-filter-i-post-zapros)).
Из JS — `getApi().setFields()` + `apply()` ([[entity-bx-main-filter]]).

## Подводные камни

- **`FILTER_ID` уникален и участвует в хранении пользовательских настроек** — сменив его, вы
  обнулите сохранённые пресеты пользователей ([[entity-filter-options]]).
- При неожиданном виде фильтра смотреть всю цепочку конфигурации, а не только свой массив `CONFIG`.
- Для новой разработки автор книги рекомендует «свой фильтр» (`\Bitrix\Main\Filter\Filter`) —
  [[entity-custom-filter]].
- Глава книги о ленивой загрузке (`LAZY_LOAD`) не дописана — на неё не опираться.

## Связанное
- [[concept-ui-subsystem]] — место фильтра в UI
- [[entity-filter-field-adapter]] — типы полей
- [[recipe-custom-list-page-filter-grid]] — фильтр на своей странице

[← Шаблоны и вёрстка](_index-templates-design.md)
