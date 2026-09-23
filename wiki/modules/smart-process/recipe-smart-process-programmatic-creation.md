---
title: "Создать смарт-процесс и пользовательские поля из инсталлятора модуля"
type: recipe
module: smart-process
edition: box
status: verified
provenance: mixed
verified: "2026-06-08 / коробка; тип создан вживую, элементы заводятся сразу после установки; сверено с «Книгой разработчика Bitrix24» 2026-09-21 (Смарт-процессы — Процессы, Описание; Счёт); REST-методы сверены через MCP (apidocs.bitrix24.ru) 2026-09-23"
tags: [smart-process, crm, инсталлятор, userfield, идемпотентность, typetable]
sources: ["[[source-devbook-crm]]"]
related: ["[[concept-crm-universal-api]]", "[[recipe-module-structure-and-install]]", "[[entity-smart-process]]", "[[recipe-crm-history-all-fields]]", "[[recipe-smart-process-factory-customization]]", "[[recipe-migrations-as-code]]"]
aliases: ["bitrix24-create-smart-process-programmatically"]
updated: "2026-09-23"
---

# Создать смарт-процесс и поля из инсталлятора

**Результат:** модуль при установке сам создаёт смарт-процесс с нужными полями. Администратору не
нужно заводить тип руками и вписывать коды полей в настройки; повторная установка не плодит дубли.

> **Сверено с «Книгой разработчика» 2026-09-21.** Шаг 1 (создание типа через `TypeTable`)
> совпадает с книгой ([Процессы](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Smart_processy/Processy.html)).
> **Исправлено:** совет «чтобы сменить флаги `IS_*`, удалить тип и создать заново» противоречит книге —
> создание и редактирование типа там идут одним путём (`set()` + `save()` у объекта), а удаление
> типа — это потеря данных. Исправлено и внутреннее расхождение: код строил имя поля из
> `ENTITY_TYPE_ID`, а раздел «Подводные камни» — из ID типа.

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
элементы создаются сразу после установки (проверено вживую). Отдельными шагами книга называет
конверсионную схему, включение типа в поля привязки, связи и собственный раздел — если они нужны,
делайте их явно. Привязка к задачам и календарю — через
`\Bitrix\Crm\UserField\UserFieldManager::getLinkedUserFieldsMap()` и `enableEntityInUserField()`
([создание и редактирование](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Smart_processy/Processy.html#sozdanie-i-redaktirovanie-processa)).

### 2. Поля — через `CUserTypeEntity` на `ENTITY_ID` из фабрики

```php
$factory    = Container::getInstance()->getFactory($entityTypeId);
$ufEntityId = $factory->getUserFieldEntityId();   // канонический ENTITY_ID — не угадывать

$uf = new \CUserTypeEntity();
$uf->Add([
    'ENTITY_ID'       => $ufEntityId,
    'FIELD_NAME'      => 'UF_' . $ufEntityId . '_RECEIVER',   // UF_CRM_<ID типа>_…, ≤ 50 символов
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
ID типа, а `entityTypeId` и ID типа на каждом портале свои — хардкод не переживёт установку на второй портал
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
| Ждать, что переустановка сменит флаги типа | тип, найденный по `CODE`, **не пересоздаётся**. Флаги `IS_*` меняют у найденного объекта: `set()` + `save()` в апдейтере модуля или миграции — по книге создание и редактирование типа идут одним путём. **Не удалять тип ради смены флагов**: это удаление данных. Если какой-то флаг у живого типа не меняется — проверить на стенде и назвать его поимённо |
| Делать «одну стадию» сидированием `b_crm_status` | `IS_STAGES_ENABLED = 'N'` — одна неизменная стадия без хрупкого сидирования ([[concept-crm-dictionaries]]) |
| `FIELD_NAME` без `UF_` или длиннее 50 символов | префикс `UF_`, латиница/цифры/`_`, ≤ 50 |
| Регистрировать свою фабрику для счетов и документов | счёт, документ и B2E-документ тоже работают на API динамических типов, но у них **свои** фабрики (`Factory\SmartInvoice` и др.); у счёта `ENTITY_ID` полей — `CRM_SMART_INVOICE`, а `\CCrmOwnerType::isPossibleDynamicTypeId(31)` = `false` (книга, [Счёт](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Scet.html#osnovnoe)). Обычные смарт-процессы: ID 128–191 либо ≥ 1030 и чётный (не из книги — сверять) |

## Откат
- `DoUninstall` может удалить тип (`$type->delete()`), но это решение необратимое. `delete()`
  возвращает `Result` и может завершиться ошибкой — её обрабатывать
  ([удаление процесса](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Smart_processy/Processy.html#udalenie-processa)).
  Удалятся ли вместе с типом элементы или платформа не даст удалить непустой тип — проверить на
  стенде. Осознанное решение: по умолчанию тип оставляем, чистим только опции модуля.

## Альтернативы
- **REST [`crm.type.add`](https://apidocs.bitrix24.ru/api-reference/crm/universal/user-defined-object-types/crm-type-add.html) + `userfieldconfig.add`** — для облака и для
  приложений маркетплейса;
  для коробочного модуля это лишний HTTP-слой и нужен `scope`.
- **Ручное создание мастером** — то, от чего уходим: коды полей приходится переносить руками.

## Связанное
- [[concept-crm-universal-api]] — откуда `getFactory` и `getDynamicTypeDataClass`
- [[recipe-crm-history-all-fields]] — что делать с историей созданного типа

[← Смарт-процессы](_index-smart-process.md)
