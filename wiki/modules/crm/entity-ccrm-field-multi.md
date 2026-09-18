---
title: "\\CCrmFieldMulti — мультиполя телефон, почта, сайт, мессенджер"
type: entity
module: crm
edition: box
status: verified
provenance: documented
verified: "2026-06-02 / документация модуля CRM, раздел структур данных (apidocs.bitrix24.ru)"
tags: [crm, мультиполя, телефон, почта, дубликаты, класс]
sources: []
related: ["[[entity-ccrm-owner-type]]", "[[entity-crm-factory]]", "[[concept-crm-dictionaries]]"]
aliases: ["bitrix24-ccrm-field-multi"]
updated: "2026-09-18"
---

# `\CCrmFieldMulti`

**Что это:** работа с мультиполями CRM — телефонами, почтами, сайтами, мессенджерами. Все
мультиполя **всех** сущностей лежат в одной таблице `b_crm_field_multi`.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | класс (старый C-API) |
| Модуль | `crm` |
| Таблица | `b_crm_field_multi` |
| Константы типов | `PHONE`, `EMAIL`, `WEB`, `IM` |

## Хранение

| Поле | Назначение |
|---|---|
| `ENTITY_ID` | мнемокод сущности (`LEAD`, `CONTACT`, …) — из [[entity-ccrm-owner-type]] |
| `ELEMENT_ID` | ID элемента |
| `TYPE_ID` | тип значения: `PHONE`, `EMAIL`, `WEB`, `IM` |
| `VALUE_TYPE` | подтип: `WORK`, `HOME`, `MOBILE`, … |
| `COMPLEX_ID` | `TYPE_ID + '_' + VALUE_TYPE` |
| `VALUE` | само значение |

У каждого подтипа есть `FULL`, `SHORT`, `ABBR` и `TEMPLATE` — HTML-шаблон отображения
(например, ссылка `callto:` для телефона). Получить всё дерево: `GetEntityTypes()`.

## Пакетная запись — add, update и delete одним вызовом

`SetFields()` — основной рабочий метод. Ключи внутри типа управляют операцией:

```php
$data = [
    'PHONE' => [
        'n0'  => ['VALUE_TYPE' => 'WORK',   'VALUE' => '+7 000 000-00-00'],  // новое
        'n1'  => ['VALUE_TYPE' => 'MOBILE', 'VALUE' => '+7 000 000-00-01'],  // новое
        '123' => ['VALUE' => ''],                                            // удалить строку 123
        '456' => ['VALUE' => '+7 000 000-00-02'],                            // изменить строку 456
    ],
    'EMAIL' => [/* … */],
];

(new \CCrmFieldMulti())->SetFields(\CCrmOwnerType::LeadName, 123, $data);
```

Точечные методы: `Add`, `Update`, `Delete($id)`, `DeleteByElement($entityId, $elementId)` —
последний убирает все мультиполя элемента.

Справочные: `IsSupportedType($typeID)`, `GetEntityTypeInfos()`, `GetEntityTypes()`,
`GetDefaultValueType($typeID)` (для `PHONE` — `WORK`), `GetListEx(...)`.

## Подводные камни

- **`'ENABLE_NOTIFICATION' => true` в `$opts`** сообщает контроллеру дубликатов, что контактные
  данные изменились. Без него поиск дублей продолжит работать по старым значениям — на импорте и
  массовых правках это заметно.
- **Пустой `VALUE` в `SetFields` означает удаление**, а не «очистить значение». Случайно пришедшая
  пустая строка из формы удалит строку.
- Мультиполя — не обычное поле сущности: в Universal API их наличие проверяется через
  `$factory->isMultiFieldsEnabled()`, а читаются они отдельно от `Item`.
- Значение хранится строкой как есть: нормализация телефонов — ваша ответственность, иначе дубли
  `+7 000` и `8 000` система считает разными.

## Связанное
- [[entity-ccrm-owner-type]] — откуда берётся `ENTITY_ID`
- [[entity-crm-factory]] — `isMultiFieldsEnabled()`

[← CRM](_index-crm.md)
