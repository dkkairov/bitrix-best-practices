---
title: "\\Bitrix\\Crm\\Item"
type: entity
module: crm
edition: box
status: verified
provenance: mixed
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Модуль CRM — Универсальное API / Элементы, Кастомизация; Смарт-процессы / Элементы; Счёт; нестрогое isChanged — эмпирика, crm 26.800"
tags: [crm, universal-api, item, класс, d7, поля, товары, файлы]
sources: ["[[source-devbook-crm]]"]
related: ["[[concept-crm-universal-api]]", "[[entity-crm-factory]]", "[[entity-crm-operation]]", "[[pattern-crm-action-vs-event]]"]
aliases: ["bitrix24-crm-item"]
updated: "2026-09-21"
---

# `\Bitrix\Crm\Item`

**Что это:** элемент CRM-сущности в Universal API. Объект в памяти: `set*` ничего не сохраняет;
штатная запись в БД — только через [[entity-crm-operation|операцию]]
([Элементы](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Elementy.html)).

> **Уточнено 2026-09-21 при сверке с книгой.** У элемента **есть** низкоуровневые `save()` и
> `delete()`, но они обходят обязательные шаги операции — в прикладном коде не используем (раньше
> страница утверждала, что писать можно только операцией). Добавлены семантика товарных позиций и
> момент, когда доступен снимок `getItemBeforeSave()`. Атрибуция исправлена.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | абстрактный класс; наследники `Item\Deal`, `Item\Lead`, `Item\Contact`, `Item\Company`, `Item\Dynamic` (смарт-процессы), … |
| Модуль | `crm` |
| Откуда берётся | `$factory->getItem($id)` / `getItems()` / `createItem()` |

## Чтение и запись полей

```php
$item->getTitle();          // магический геттер (camelCase из SNAKE_CASE)
$item->get('TITLE');        // общий

$item->setTitle('X');       // магический сеттер, возвращает $this
$item->set('TITLE', 'X');   // общий

$item->isChangedTitle();    // магический
$item->isChanged('TITLE');  // общий
```

**Для пользовательских полей — только общий метод.** `ASSIGNED_BY_ID` → `assignedById`
однозначно, а у цифр верхнего регистра нет: одновременно могут существовать `UF_CRM_1_1` и
`UF_CRM_11`, и куда отнести `ufCrm11` — неизвестно. Пишите
`$item->set('UF_CRM_9_EXAMPLE', $value)`.

## Унификация разных сущностей

Лид хранит «статус» в одной колонке, сделка — в другой. Общий код пишут через константы:

```php
$item->get($item::FIELD_NAME_STAGE_ID);      // работает и для лида, и для сделки
$item::FIELD_NAME_CATEGORY_ID;               // 'CATEGORY_ID'
```

## Состояние «до сохранения»

```php
$item->remindActual('FIELD_CODE');   // значение, которое сейчас в БД
$item->isChanged('FIELD_CODE');      // менялось ли локально
```

Внутри действия операции доступен снимок целиком: `$this->getItemBeforeSave()`. Книга связывает
его с действием **после** сохранения (`ACTION_AFTER_SAVE`); в действии **до** сохранения она
использует `remindActual()` / `isChanged*()` — заполнен ли снимок там, проверить в коде.

## Сериализация

| Метод | Что отдаёт |
|---|---|
| `getData($mode = Values::ALL)` | массив |
| `getCompatibleData()` | формат старого API |
| `jsonSerialize()` / `toArray()` | для JSON |

`Values::ALL` — всё, `Values::ACTUAL` — только из БД без локальных правок, `Values::CURRENT` —
только локальные изменения.

## Товарные позиции

`setProductRowsFromArrays()`, `addToProductRows(ProductRow)`, `removeFromProductRows()`,
`updateProductRow($id, $fields)`, `setProductRows(ProductRow[])`. Объект —
`\Bitrix\Crm\ProductRow::createFromArray()`. Смежные: `\Bitrix\Crm\Discount`,
`\Bitrix\Crm\ProductType`.

По книге ([товарные позиции](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Elementy.html#tovarnye-pozicii)):
- `updateProductRow()` — передавать **только изменяемые** поля; непереданные сбросятся к исходным;
  резерв не поддерживается;
- `setProductRows()` **удаляет** позиции, которых нет в новом списке;
- методы возвращают `Result` — если разбор товаров не удался, операцию не запускать.

## Файлы — два шага

```php
$field = $factory->getFieldsCollection()->getField($ufFieldCode);
Container::getInstance()->getFileUploader()->registerFileId($field, $fileId);
$item->set($ufFieldCode, $fileId);      // множественное — массивом
```

Без регистрации в `FileUploader` значение не сохранится.

## Наблюдатели и клиент

```php
$item->getObservers();  $item->setObservers([1, 2, 3]);

$item->isClientEmpty();  $item->getCompanyId();
$item->getPrimaryContact();     // \Bitrix\Crm\Contact — НЕ Item\Contact
$bindings = \Bitrix\Crm\Binding\EntityBinding::prepareEntityBindings(\CCrmOwnerType::Contact, [1,2,3]);
$item->bindContacts($bindings);
```

## Подводные камни

- **`set*` не сохраняет.** Самая частая ошибка новичка в Universal API.
- **`$item->save()` и `$item->delete()` существуют, но в прикладном коде запрещены.** По книге
  низкоуровневое сохранение обходит значения по умолчанию, права, связи, push, БП и роботов,
  пересчёт прав, поисковый индекс, статистику и таймлайн; удаление не чистит товары, права, связи с
  событиями, дела, чаты и счётчики. Только `get*Operation()->launch()`
  ([Смарт-процессы → Элементы](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Smart_processy/Elementy.html#sohranenie)).
- **`var_dump` / `print_r` на `Item` способен уронить портал по памяти** — объект тянет за собой
  коллекции и связанные сущности. Для отладки — `getData()` или конкретные поля.
- **`isChanged()` сравнивает массивы нестрого** (`!=`): `['119']` и `[119]` считаются равными.
  Обычно это спасает от ложных записей в историю, но может скрыть реальное изменение типа.
  (Эмпирика команды, crm 26.800; в книге нет.)
- `getPrimaryContact()` возвращает `\Bitrix\Crm\Contact`, а не `Item` — разные классы с похожими
  именами.

## Связанное
- [[entity-crm-operation]] — чем сохраняют
- [[pattern-crm-action-vs-event]] — почему объект лучше массива изменённых полей

[← CRM](_index-crm.md)
