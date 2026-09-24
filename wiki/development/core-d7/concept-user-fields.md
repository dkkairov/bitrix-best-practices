---
title: "Пользовательские поля (UF): типы, создание, ORM"
type: concept
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / коробка в Docker, main 26.750.0: список типов UF портала получен через UserFieldManager — address, boolean, crm, crm_status, date, datetime, disk_file, disk_version, double, employee, enumeration, file, hlblock, iblock_element, iblock_section, integer, mail_message, money, resourcebooking, snils, string, string_formatted, url, url_preview, video, vote; классы CUserTypeEntity, UserField\\Internal\\UserFieldHelper, ORM\\Fields\\UserTypeField на месте; текст — документация фреймворка (docs.1c-bitrix.ru, «Пользовательские поля»)"
tags: [uf, пользовательские-поля, orm, справочники, сущности]
sources: []
related: ["[[concept-d7-orm-entity]]", "[[concept-highload-blocks]]", "[[entity-smart-process]]", "[[recipe-migrations-as-code]]", "[[concept-crm-universal-api]]"]
aliases: []
updated: "2026-09-24"
---

# Пользовательские поля (UF): типы, создание, ORM

**TL;DR:** UF — штатный способ добавить поле к чужой сущности: пользователю, элементу инфоблока,
сделке, смарт-процессу, своей таблице. Имя всегда начинается с `UF_`, тип выбирается из списка,
значения живут в отдельных таблицах и подхватываются ORM.

**Когда применять:** заказчику нужно «ещё одно поле» — это первый ответ, а не новая таблица и не
правка ядра ([[concept-change-invasiveness-hierarchy]]).

## Типы полей портала (стенд, Битрикс24)

`string`, `string_formatted`, `integer`, `double`, `money`, `boolean`, `date`, `datetime`,
`enumeration` (список), `file`, `url`, `url_preview`, `video`, `address`, `snils`, `vote`,
`employee` (сотрудник), `crm`, `crm_status`, `iblock_element`, `iblock_section`, `hlblock`
(справочник на highload-блоке, [[concept-highload-blocks]]), `disk_file`, `disk_version`,
`mail_message`, `resourcebooking`.

Состав зависит от установленных модулей: на «голом» Bitrix Framework типов будет меньше, чем на
портале Битрикс24.

## Создание из кода

```php
$entity = new CUserTypeEntity();
$id = $entity->Add([
    'ENTITY_ID'       => 'IBLOCK_3_SECTION',   // к какой сущности
    'FIELD_NAME'      => 'UF_MANAGER',
    'USER_TYPE_ID'    => 'employee',
    'MULTIPLE'        => 'N',
    'MANDATORY'       => 'N',
    'SETTINGS'        => [],
    'EDIT_FORM_LABEL' => ['ru' => 'Ответственный'],
]);
```

> **Четыре параметра неизменяемы после создания:** `USER_TYPE_ID`, `ENTITY_ID`, `FIELD_NAME`,
> `MULTIPLE`. Ошиблись — придётся завести новое поле и перенести данные, а это простой для
> пользователей. Проверяем на стенде до прода ([[recipe-migrations-as-code]]).

Чтение и запись «универсальным» путём:

```php
$manager = \Bitrix\Main\UserField\Internal\UserFieldHelper::getInstance()->getManager();
$manager->Update('IBLOCK_3_SECTION', $itemId, ['UF_MANAGER' => $userId]);
$value = $manager->GetUserFieldValue('IBLOCK_3_SECTION', 'UF_MANAGER', $itemId);
```

## UF в своей сущности ORM

```php
class BookTable extends DataManager
{
    public static function getUfId(): string { return 'VENDOR_BOOK'; }
}

$rows = BookTable::query()
    ->addSelect('UF_RATING')
    ->where('UF_RATING', '>', 50)
    ->fetchCollection();
```

Дальше это обычные поля сущности: `$row->getUfRating()`, `$row->setUfRating()`; ядро само
подключает таблицы значений ([[concept-d7-orm-entity]]). В карте сущности UF представлены классом
`ORM\Fields\UserTypeField` — руками его не пишем.

## Свой тип поля

Регистрируется обработчиком события `OnUserTypeBuildList` модуля `main`. Нужен редко: сначала
смотрим, нельзя ли обойтись `enumeration` или справочником на highload-блоке.

## Подводные камни

- **Штатные валидаторы сущности к UF не применяются** — проверки настраиваются в свойствах самого
  поля ([[concept-d7-orm-entity]]).
- **Множественное поле хранится отдельной таблицей**: выборка по нему дороже, а запись заменяет
  весь набор значений.
- **`ENTITY_ID` у сущностей CRM свой** (`CRM_DEAL`, `CRM_2` для смарт-процесса и т. д.) — прежде
  чем добавлять поле в CRM, смотрим [[concept-crm-universal-api]] и
  [[entity-smart-process]].
- **UF-поле — часть интерфейса заказчика.** Его имя и подписи переживут нас: `UF_CRM_1698…`,
  сгенерированное мастером, потом читает вся команда. Создавая из кода, даём осмысленное имя.
- **Удаление поля удаляет данные** без корзины.

## Связанные страницы
- [[concept-d7-orm-entity]] — `getUfId()` и поля сущности
- [[concept-highload-blocks]] — справочник для поля типа `hlblock`
- [[concept-crm-universal-api]] — UF в сущностях CRM

[← Ядро D7](_index-core-d7.md)
