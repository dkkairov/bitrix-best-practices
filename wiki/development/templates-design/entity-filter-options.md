---
title: "\\Bitrix\\Main\\UI\\Filter\\Options — PHP-API фильтра"
type: entity
module: templates-design
edition: box
status: verified
provenance: documented
verified: "2026-06-01 / документация модуля main, фильтры пользователя (dev.1c-bitrix.ru)"
tags: [ui, фильтр, php, пресеты, orm, b_user_option]
sources: []
related: ["[[entity-filter-component]]", "[[entity-custom-filter]]", "[[concept-ui-subsystem]]"]
aliases: ["bitrix24-filtr-options"]
updated: "2026-09-18"
---

# `\Bitrix\Main\UI\Filter\Options`

**Что это:** серверная работа с пользовательской конфигурацией фильтра и мост из значений фильтра
в формат ORM.

> **Документация называет этот путь устаревшим.** Для новой разработки рекомендуется «свой
> фильтр» — [[entity-custom-filter]]. Страница нужна, потому что существующий код полон `Options`.

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

## Методы

| Метод | Назначение |
|---|---|
| `pinPreset($presetId = 'default_filter')` | закрепить пресет |
| `getUsedFields(): array` | поля текущего фильтра |
| `getDefaultFilterId()` / `getCurrentFilterId()` | пресет по умолчанию / выбранный |
| `getFilter($sourceFields): array` | значения из запроса + служебные `PRESET_ID`, `FILTER_ID`, `FILTER_APPLIED`, `FIND` |
| **`getFilterLogic($sourceFields): array`** | **то же в формате D7-ORM** — с префиксами `>=`, `<=` |

`getFilterLogic()` — самое ценное: готовый массив для `DataManager::getList(['filter' => …])`,
не нужно вручную превращать `_from`/`_to` в диапазоны.

## Подводные камни

- **Класс завязан на глобальный `$USER`** — работает с настройками **текущего** пользователя.
  Прочитать или поправить чужой фильтр этим API затруднительно: для админских сценариев придётся
  идти в `b_user_option` напрямую.
- **`getFilter()` возвращает и служебные ключи** — не передавайте результат в ORM как есть,
  для этого есть `getFilterLogic()`.
- Смена `FILTER_ID` = потеря сохранённых пресетов пользователей: они привязаны к нему в
  `b_user_option`.

## Связанное
- [[entity-filter-component]] — визуальная часть
- [[entity-custom-filter]] — рекомендованный путь для нового кода

[← Шаблоны и вёрстка](_index-templates-design.md)
