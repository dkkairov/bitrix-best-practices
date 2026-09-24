---
title: "Highload-блоки: своя таблица без своего модуля"
type: concept
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / коробка в Docker: модуль highloadblock 25.100.0 установлен, у HighloadBlockTable есть getById, compileEntity, getList, add, update, delete; на стенде один HL-блок; в списке типов пользовательских полей портала есть hlblock (типа directory среди UF нет — это тип свойства инфоблока); текст — документация фреймворка (docs.1c-bitrix.ru, раздел «Highload-блоки»)"
tags: [highload, справочники, orm, пользовательские-поля, хранение]
sources: []
related: ["[[concept-d7-orm-entity]]", "[[concept-iblocks-and-lists]]", "[[recipe-d7-orm-crud]]", "[[entity-smart-process]]", "[[recipe-migrations-as-code]]"]
aliases: []
updated: "2026-09-24"
---

# Highload-блоки: своя таблица без своего модуля

**TL;DR:** highload-блок — отдельная таблица в базе, структура которой задаётся пользовательскими
полями `UF_*`, а доступ идёт через динамический ORM-класс. Получается «своя таблица», которую можно
завести из админки, без модуля и миграций.

**Когда применять:** плоские справочники (бренды, цвета, города, коды систем), соответствия внешних
идентификаторов, настройки интеграции. Нужна иерархия, публикация, права по разделам — это
инфоблок ([[concept-iblocks-and-lists]]); нужна карточка, стадии и бизнес-логика — смарт-процесс
([[entity-smart-process]]).

Заводятся в админке: **Контент → Highload-блоки**. Модуль — `highloadblock` (на стенде 25.100.0).

## Доступ к записям

```php
use Bitrix\Highloadblock\HighloadBlockTable;

Loader::includeModule('highloadblock');

$block  = HighloadBlockTable::getById($blockId)->fetch();
$entity = HighloadBlockTable::compileEntity($block);
$data   = $entity->getDataClass();       // динамический класс сущности

$rows = $data::getList([
    'select' => ['ID', 'UF_NAME'],
    'filter' => ['=UF_ACTIVE' => 1],
    'order'  => ['UF_SORT' => 'ASC'],
    'limit'  => 50,
])->fetchAll();

$add = $data::add(['UF_NAME' => 'Синий', 'UF_TAGS' => ['a', 'b']]);
$data::update($id, ['UF_NAME' => 'Голубой']);
$data::delete($id);
```

Дальше это обычная ORM: `getRow()`, `fetchObject()`, `fetchCollection()`, объект `Result` с
`isSuccess()` и ошибками ([[recipe-d7-orm-crud]], [[concept-d7-orm-objects]]).

## Как справочник для полей

| Механизм | Что хранит | Где применяется |
|---|---|---|
| поле типа **`hlblock`** | системный `ID` записи | пользовательские поля любой сущности портала (тип есть в списке UF на стенде) |
| свойство типа **`directory`** | `UF_XML_ID` записи | свойства элементов инфоблока |

- Справочник обязан иметь **`UF_XML_ID`** — стабильный код; по нему и связываются данные. Полезные
  поля: `UF_NAME`, `UF_SORT`, `UF_FILE`, `UF_DEF`, `UF_DESCRIPTION`.
- Поле `hlblock` создаётся через `CUserTypeEntity` с `SETTINGS` = `HLBLOCK_ID` и `HLFIELD_ID`;
  `MULTIPLE = 'Y'` даёт множественное.
- У поля `hlblock` в ORM появляется алиас `<ПОЛЕ>_REF` — связанную запись можно выбрать одним
  запросом.

## Подводные камни

- **`ID` и `UF_XML_ID` — разные ключи.** Поле `hlblock` хранит `ID`, свойство `directory` —
  `UF_XML_ID`; подменить одно другим нельзя.
- **Целостности нет.** Внешних ключей база не создаёт: удалили запись справочника — ссылки на неё
  останутся «мёртвыми», чистим сами.
- **Переносимость.** Между стендом и продом `ID` блоков и записей различаются: в коде опираемся на
  коды (`UF_XML_ID`, имя таблицы), а не на числа. Структуру переносим миграцией
  ([[recipe-migrations-as-code]]).
- **Прав нет по умолчанию.** Записи не проверяют доступ автоматически — если данные чувствительные,
  проверяем сами (операции `hl_element_read/write/delete`).
- **Множественное поле перезаписывается целиком:** переданный массив заменяет прежние значения,
  пустой массив очищает.
- **Файловые поля** — через `CFile::MakeFileArray()`, замена файла требует передачи `old_id`.
- **Запросы в цикле убивают производительность.** Справочник читаем пачкой по списку кодов, а не по
  одной записи на элемент.

## Что выбрать для хранения

| Данные | Где хранить |
|---|---|
| плоский справочник, настройки, соответствия | **highload-блок** |
| контент с разделами, «Списки» портала | инфоблок ([[concept-iblocks-and-lists]]) |
| объект с карточкой, стадиями, правами и роботами | смарт-процесс ([[entity-smart-process]]) |
| данные своего модуля со сложной схемой | своя таблица и `DataManager` ([[concept-d7-orm-entity]]) |

## Связанные страницы
- [[concept-d7-orm-entity]] — ORM, на которой всё это стоит
- [[concept-iblocks-and-lists]] — инфоблоки и «Списки»
- [[recipe-migrations-as-code]] — перенос структуры между стендами

[← Ядро D7](_index-core-d7.md)
