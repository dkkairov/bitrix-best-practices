---
title: "\\Bitrix\\Main\\UI\\Filter\\FieldAdapter — типы полей фильтра"
type: entity
module: templates-design
edition: box
status: verified
provenance: documented
verified: "2026-06-01 / документация модуля main, обзор фильтра (dev.1c-bitrix.ru)"
tags: [ui, фильтр, типы-полей, селекторы]
sources: ["[[source-devbook-ui]]"]
related: ["[[entity-filter-component]]", "[[entity-filter-options]]", "[[concept-ui-subsystem]]"]
aliases: ["bitrix24-filtr-fieldadapter"]
updated: "2026-09-18"
---

# `\Bitrix\Main\UI\Filter\FieldAdapter`

**Что это:** перечисление типов полей фильтра — источник значений для ключа `type` в описании поля.

## Каталог типов

| Константа | Значение | Особые ключи |
|---|---|---|
| `STRING` | `string` | — |
| `TEXTAREA` | `textarea` | — |
| `NUMBER` | `number` | `messages`, `exclude`, `include` |
| `DATE` | `date` | `time`, `messages`, `exclude`, `include`, `allow_years_switcher` |
| `LIST` | `list` | `items` (значение → подпись) |
| `DEST_SELECTOR` | `dest_selector` | `lightweight`; params: `multiple`, `context`, `enableUsers`, `enableDepartments`, `isNumeric`, `prefix` |
| `ENTITY_SELECTOR` | `entity_selector` | params: `multiple`, `addEntityIdToResult`, `showDialogOnEmptyInput`, `dialogOptions` |
| `CHECKBOX` | `checkbox` | `valueType`: `'numeric'` → `0`/`1`, иначе `Y`/`N` |
| `CUSTOM`, `CUSTOM_ENTITY`, `CUSTOM_DATE` | пользовательские | — |

## Базовая структура поля

```php
[
    'id'            => 'CODE',
    'name'          => 'Подпись',
    'type'          => \Bitrix\Main\UI\Filter\FieldAdapter::STRING,
    'placeholder'   => '…',
    'params'        => ['multiple' => true],
    'required'      => false,
    'valueRequired' => false,
    'strict'        => false,
]
```

## Подводные камни

- **Пользовательские типы (`CUSTOM`, `CUSTOM_ENTITY`, `CUSTOM_DATE`) плохо кастомизируются** —
  документация прямо говорит, что вместо них используют стандартные поля. Если тянет к `CUSTOM`,
  сначала проверьте, не решается ли задача через `LIST` или `ENTITY_SELECTOR`.
- **`CHECKBOX` отдаёт разные значения** в зависимости от `valueType`: `0`/`1` или `Y`/`N`. Без
  приведения обе «пустые» формы выглядят как заполненные — та же ловушка, что в карточке CRM
  ([[recipe-crm-card-editor-js-access]]).
- Поля дат в фильтре работают с подтипами («вчера», «прошлая неделя») — это отдельное семейство
  констант, не путать с [[entity-bizproc-field-type|типами значений БП]].

## Связанное
- [[entity-filter-component]] — куда передаются поля

[← Шаблоны и вёрстка](_index-templates-design.md)
