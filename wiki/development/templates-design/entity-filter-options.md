---
title: "\\Bitrix\\Main\\UI\\Filter\\Options — PHP-API фильтра"
type: entity
module: templates-design
edition: box
status: verified
provenance: documented
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): UI / Фильтр — Фильтры пользователя; без проверки на стенде"
tags: [ui, фильтр, php, пресеты, orm, b_user_option]
sources: ["[[source-devbook-ui]]"]
related: ["[[entity-filter-component]]", "[[entity-custom-filter]]", "[[concept-ui-subsystem]]"]
aliases: ["bitrix24-filtr-options"]
updated: "2026-09-21"
---

# `\Bitrix\Main\UI\Filter\Options`

**Что это:** серверная работа с пользовательской конфигурацией фильтра
([Фильтры пользователя](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Filtr/Filtry_polzovatela.html)).

> **Уточнено 2026-09-21 при сверке с книгой.** «Устаревшим» автор книги называет не класс целиком,
> а **получение значений** через `getFilter()` / `getFilterLogic()`: этот способ он подаёт как
> демонстрационный или для устаревших систем, а в новом коде — «свой фильтр»
> (`\Bitrix\Main\Filter\Filter::getValue()`, [[entity-custom-filter]]). Остальные методы класса
> (`pinPreset`, `getUsedFields`, …) описаны как рабочие. Раньше страница называла `getFilterLogic()`
> «самым ценным» и приписывала оценку «документации».

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | класс |
| Модуль | `main` |
| Хранение | `b_user_option`: `CATEGORY = 'main.ui.filter'`, `NAME = <filterId>`, `VALUE = serialize(...)` |

```php
use Bitrix\Main\UI\Filter\Options;

$options = new Options($filterId, $filterPresets, $commonPresetsId);
```

## Структура конфигурации в `b_user_option`

| Ключ | Что хранит |
|---|---|
| `filters` | пресеты пользователя (`name`, `fields` — уже подготовленные к выводу, `filter_rows`, `sort`, `for_all`) |
| `filter` | текущий пресет |
| `default`, `default_presets`, `deleted_presets` | пресет по умолчанию, системные и удалённые пресеты |
| `use_pin_preset` | закреплён ли пресет |

Системные пресеты: `default_filter` и `tmp_filter` (применённый фильтр).

## Методы

| Метод | Назначение |
|---|---|
| `pinPreset($presetId = 'default_filter')` | закрепить пресет |
| `getUsedFields(): array` | поля текущего фильтра |
| `getDefaultFilterId()` / `getCurrentFilterId()` | пресет по умолчанию / выбранный |
| `getFilter($sourceFields): array` | значения из запроса + служебные `PRESET_ID`, `FILTER_ID`, `FILTER_APPLIED`, `FIND` |
| `getFilterLogic($sourceFields): array` | то же в формате D7-ORM — с префиксами `>=`, `<=` |

`getFilterLogic()` полезен, чтобы читать и сопровождать старый код; для нового —
`Filter::getValue()` ([[entity-custom-filter]]).

## Подводные камни

- **Класс завязан на глобальный `$USER`** — работает с настройками **текущего** пользователя; с
  фильтрами других пользователей работать затруднительно. Идти для админских сценариев прямо в
  `b_user_option` — вывод команды, осторожно.
- **`getFilter()` возвращает и служебные ключи** — не передавайте результат в ORM как есть.
- Смена `FILTER_ID` = потеря сохранённых пресетов пользователей: они привязаны к нему в
  `b_user_option`.

## Связанное
- [[entity-filter-component]] — визуальная часть
- [[entity-custom-filter]] — рекомендованный путь для нового кода

[← Шаблоны и вёрстка](_index-templates-design.md)
