---
title: "\\CCrmFieldMulti — мультиполя телефон, почта, сайт, мессенджер"
type: entity
module: crm
edition: box
status: verified
provenance: mixed
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Модуль CRM — Словари / Структуры данных; Лид, Контакт — примеры (поиск по телефону) и методы"
tags: [crm, мультиполя, телефон, почта, дубликаты, класс]
sources: ["[[source-devbook-crm]]"]
related: ["[[entity-ccrm-owner-type]]", "[[entity-crm-factory]]", "[[concept-crm-dictionaries]]", "[[recipe-crm-legacy-entity-crud]]"]
aliases: ["bitrix24-ccrm-field-multi"]
updated: "2026-09-21"
---

# `\CCrmFieldMulti`

**Что это:** работа с мультиполями CRM — телефонами, почтами, сайтами, мессенджерами. Все
мультиполя **всех** сущностей лежат в одной таблице `b_crm_field_multi`
([Структуры данных](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Slovari/Struktury_dannyh.html#kommunikacionnye-pola)).

> **Сверено с книгой 2026-09-21.** Атрибуция исправлена (материал — из книги, не из apidocs).
> Добавлен поиск элемента по телефону через индекс дубликатов (по книге `GetListEx` для этого не
> годится); уточнено, к каким методам относится `ENABLE_NOTIFICATION`; добавлена запись через `FM`.

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

**Через саму сущность.** В старом API мультиполя можно передать в `CCrmLead`/`CCrmContact`/
`CCrmCompany::Add/Update` ключом `FM` с той же структурой (`n0…` — новые, ID — существующие, пустой
`VALUE` — удалить); `Update` контакта и компании при этом сам обновляет индекс дубликатов —
[[recipe-crm-legacy-entity-crud]].

Справочные: `IsSupportedType($typeID)`, `GetEntityTypeInfos()`, `GetEntityTypes()`,
`GetDefaultValueType($typeID)` (для `PHONE` — `WORK`), `GetListEx(...)` — чтение мультиполей
**конкретного** элемента (`ENTITY_ID`, `ELEMENT_ID`, `TYPE_ID`).

Для фильтра «есть телефон / почта / мессенджер» у лида, контакта и компании есть вычисляемые поля
`HAS_PHONE`, `HAS_EMAIL`, `HAS_IMOL` — без обращения к мультиполям.

## Поиск элемента по телефону — через индекс дубликатов

`GetListEx` для поиска **по номеру** не годится: значения хранятся так, как их ввели
(`88002000600`, `+7 800 2000 600`, `8 (800) 200-60-00` — для системы разные строки). Книга ищет
через менеджер дубликатов ([найти лид по номеру телефона](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Lid/Primery.html#najti-lid-po-nomeru-telefona)):

```php
\Bitrix\Main\Loader::requireModule('crm');

$digits  = preg_replace('/\D+/', '', $phone);          // в индексе — только цифры
$adapter = \Bitrix\Crm\EntityAdapterFactory::create(
    ['FM' => ['PHONE' => [['VALUE' => $digits]]]],
    \CCrmOwnerType::Contact
);
$duplicates = (new \Bitrix\Crm\Integrity\ContactDuplicateChecker())->findDuplicates(
    $adapter,
    new \Bitrix\Crm\Integrity\DuplicateSearchParams(['FM.PHONE'])
);
foreach ($duplicates as $duplicate) {
    foreach ($duplicate->getEntities() as $entity) {        // DuplicateEntity
        // $entity->getEntityTypeID(), $entity->getEntityID()
    }
}
```

Условия: индекс дубликатов **построен и актуален** (иначе результат неверен); номер — только
цифрами; в выдаче не больше 50 элементов каждого типа. В примерах книги для лида тоже используется
`ContactDuplicateChecker` с отбором по типу, а на странице компании имя класса искажено — какой
checker правилен для лида и компании, проверить в ядре.

## Подводные камни

- **`'ENABLE_NOTIFICATION' => true`** сообщает контроллеру дубликатов, что контактные данные
  изменились. Опцию принимают **точечные** `Add`/`Update`/`Delete`; у `SetFields` параметр `$options`
  не используется. Что без неё поиск дублей работает по старым значениям — вывод команды, не
  проверено.
- **Пустой `VALUE` в `SetFields` означает удаление**, а не «очистить значение». Случайно пришедшая
  пустая строка из формы удалит строку.
- Мультиполя — не обычное поле сущности: в Universal API их наличие проверяется через
  `$factory->isMultiFieldsEnabled()`; как они читаются через `Item` в новых версиях — сверить по
  ядру (наблюдение команды).
- Значение хранится строкой как есть: для поиска и сравнения номеров — индекс дубликатов (см. выше),
  а не сравнение строк.

## Связанное
- [[entity-ccrm-owner-type]] — откуда берётся `ENTITY_ID`
- [[entity-crm-factory]] — `isMultiFieldsEnabled()`

[← CRM](_index-crm.md)
