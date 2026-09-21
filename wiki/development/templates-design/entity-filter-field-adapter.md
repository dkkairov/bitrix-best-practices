---
title: "\\Bitrix\\Main\\UI\\Filter\\FieldAdapter — типы полей фильтра"
type: entity
module: templates-design
edition: box
status: verified
provenance: documented
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): UI / Фильтр — Обзор (поля фильтра); перечень типов — «на момент написания»"
tags: [ui, фильтр, типы-полей, селекторы]
sources: ["[[source-devbook-ui]]"]
related: ["[[entity-filter-component]]", "[[entity-filter-options]]", "[[concept-ui-subsystem]]", "[[entity-custom-filter]]"]
aliases: ["bitrix24-filtr-fieldadapter"]
updated: "2026-09-21"
---

# `\Bitrix\Main\UI\Filter\FieldAdapter`

**Что это:** перечисление типов полей фильтра — источник значений для ключа `type` в описании поля
([поля фильтра](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Filtr/Obzor.html#pola-fil-tra);
атрибуция и нюансы уточнены при сверке с книгой 2026-09-21). Эти классы общие для компонента и для
«своего фильтра» ([[entity-custom-filter]]).

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

- **Пользовательские типы (`CUSTOM`, `CUSTOM_ENTITY`, `CUSTOM_DATE`) почти не используют** — автор
  книги отмечает, что вместо них берут стандартные поля. Если тянет к `CUSTOM`, сначала проверьте, не
  решается ли задача через `LIST` или `ENTITY_SELECTOR`.
- **`CHECKBOX` отдаёт разные значения** в зависимости от `valueType`: `0`/`1` или `Y`/`N`, и у поля
  **три состояния** — пусто, «Да», «Нет». Без приведения «пустые» формы легко принять за
  заполненные (вывод команды; похожая ловушка — в карточке CRM, [[recipe-crm-card-editor-js-access]]).
- **Подтипы чисел и дат** — классы `\Bitrix\Main\UI\Filter\NumberType`, `AdditionalNumberType`,
  `DateType`, `AdditionalDateType` (ключи `exclude`/`include`); переключатель лет
  (`allow_years_switcher`) даёт выбор только в диапазоне −20…+5 лет. Не путать с
  [[entity-bizproc-field-type|типами значений БП]].
- У необязательного немножественного поля `LIST` пустой пункт (`MAIN_UI_FILTER__NOT_SET`)
  добавляется сам.

## Связанное
- [[entity-filter-component]] — куда передаются поля

[← Шаблоны и вёрстка](_index-templates-design.md)
