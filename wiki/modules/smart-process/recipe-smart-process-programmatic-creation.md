---
title: "Создать смарт-процесс и пользовательские поля из инсталлятора модуля"
type: recipe
module: smart-process
edition: box
status: verified
provenance: empirical
verified: "2026-06-08 / коробка; тип создан вживую, элементы заводятся сразу после установки"
tags: [smart-process, crm, инсталлятор, userfield, идемпотентность, typetable]
sources: []
related: ["[[concept-crm-universal-api]]", "[[recipe-module-structure-and-install]]", "[[entity-smart-process]]", "[[recipe-crm-history-all-fields]]"]
aliases: ["bitrix24-create-smart-process-programmatically"]
updated: "2026-09-18"
---

# Создать смарт-процесс и поля из инсталлятора

**Результат:** модуль при установке сам создаёт смарт-процесс с нужными полями. Администратору не
нужно заводить тип руками и вписывать коды полей в настройки; повторная установка не плодит дубли.

## Предусловия
- Модуль установлен и его автозагрузчик подключён — [[recipe-module-structure-and-install]].
  В `DoInstall` **до** обращения к своим классам вызвать `Loader::includeModule($this->MODULE_ID)`.
- Понимание фабрик Universal API — [[concept-crm-universal-api]].

## Шаги

### 1. Тип — через ORM `TypeTable`

```php
use Bitrix\Crm\Service\Container;

$typeDataClass = Container::getInstance()->getDynamicTypeDataClass();   // TypeTable

$type = $typeDataClass::createObject();
$type->set('TITLE', 'Благодарность');
$type->set('NAME', 'THANKS');      // латиница
$type->set('CODE', 'THANKS');      // по нему ищем дубль при переустановке
$type->set('IS_STAGES_ENABLED', 'N');
$type->set('IS_CATEGORIES_ENABLED', 'N');
$type->set('IS_USE_IN_USERFIELD_ENABLED', 'Y');
$type->set('IS_SET_OPEN_PERMISSIONS', 'N');   // 'Y' = доступен всем сразу
// + прочие IS_*: автоматизация, БП, клиент, корзина — как в crm.type.add
$saveResult   = $type->save();                // \Bitrix\Main\Result
$entityTypeId = (int)$type->getEntityTypeId();
```

`save()` создаёт **и строку типа, и хранилище элементов** (таблицу `b_crm_dynamic_items_*`):
отдельно провизионить ничего не нужно — элементы создаются сразу после установки.

### 2. Поля — через `CUserTypeEntity` на `ENTITY_ID` из фабрики

```php
$factory    = Container::getInstance()->getFactory($entityTypeId);
$ufEntityId = $factory->getUserFieldEntityId();   // канонический ENTITY_ID — не угадывать

$uf = new \CUserTypeEntity();
$uf->Add([
    'ENTITY_ID'       => $ufEntityId,
    'FIELD_NAME'      => 'UF_CRM_' . $entityTypeId . '_RECEIVER',  // префикс UF_, ≤ 50 символов
    'USER_TYPE_ID'    => 'employee',   // string | integer | boolean | enumeration | …
    'MANDATORY'       => 'Y',
    'MULTIPLE'        => 'N',
    'EDIT_FORM_LABEL' => ['ru' => 'Получатель'],
]);
```

### 3. Идемпотентность

- Тип: перед созданием `getList(['filter' => ['=CODE' => $code]])` — если есть, вернуть его
  `entityTypeId`.
- Поле: `CUserTypeEntity::GetList([], ['ENTITY_ID' => $e, 'FIELD_NAME' => $f])`.

### 4. Где хранить результат

`entityTypeId` и карту «логическое имя → реальный код поля» класть в опции модуля
(`Option::set`), чтобы вся логика читала коды оттуда, а не хардкодила. Коды полей зависят от
`entityTypeId`, а он на каждом портале свой — хардкод не переживёт установку на второй портал
(та же болезнь, что [[antipattern-bizproc-hardcoded-portal-ids]]).

## Проверка результата
- Тип виден в *CRM → Настройки → Смарт-процессы*, элементы создаются.
- Поля на месте, обязательность и подписи соответствуют заданным.
- Повторная установка не создаёт второй тип и вторые поля.

## Подводные камни

| Ловушка | Правильно |
|---|---|
| Использовать классы своего модуля в `DoInstall` сразу после `registerModule` | сначала `Loader::includeModule($this->MODULE_ID)` — иначе автозагрузчик из `include.php` ещё не подключён |
| Угадывать `ENTITY_ID` для пользовательских полей | брать `$factory->getUserFieldEntityId()`. У смарт-процесса это `CRM_<ID типа>`, а **не** `CRM_<ENTITY_TYPE_ID>`: у типа с `ENTITY_TYPE_ID = 162` поля лежат под `CRM_4` и называются `UF_CRM_4_…` |
| Ждать, что переустановка сменит флаги типа | тип, найденный по `CODE`, **не пересоздаётся**; смена `IS_*` требует удаления типа и создания заново |
| Делать «одну стадию» сидированием `b_crm_status` | `IS_STAGES_ENABLED = 'N'` — одна неизменная стадия без хрупкого сидирования ([[concept-crm-dictionaries]]) |
| `FIELD_NAME` без `UF_` или длиннее 50 символов | префикс `UF_`, латиница/цифры/`_`, ≤ 50 |
| Регистрировать свою фабрику для счетов и документов | счёт, документ и B2E-документ тоже работают на API динамических типов, но у них **свои** фабрики (`Factory\SmartInvoice` и др.). Обычные смарт-процессы: ID 128–191 либо ≥ 1030 и чётный |

## Откат
- `DoUninstall` может удалить тип (`delete()`), но это удалит и элементы. Осознанное решение:
  по умолчанию тип оставляем, чистим только опции модуля.

## Альтернативы
- **REST `crm.type.add` + `userfieldconfig.add`** — для облака и для приложений маркетплейса;
  для коробочного модуля это лишний HTTP-слой и нужен `scope`.
- **Ручное создание мастером** — то, от чего уходим: коды полей приходится переносить руками.

## Связанное
- [[concept-crm-universal-api]] — откуда `getFactory` и `getDynamicTypeDataClass`
- [[recipe-crm-history-all-fields]] — что делать с историей созданного типа

[← Смарт-процессы](_index-smart-process.md)
