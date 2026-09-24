---
title: "Инфоблоки и «Списки» портала"
type: concept
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / коробка в Docker: модули iblock 26.0.100 и lists 26.200.100 установлены; типы инфоблоков на портале — bitrix_processes, CRM_PRODUCT_CATALOG, events, lists, lists_socnet, news, photos, rest_entity, services, structure; на оргструктуре и отсутствиях стоят инфоблоки типа structure (absence, honour, departments, state_history); IblockTable::compileEntity есть; текст — документация фреймворка (docs.1c-bitrix.ru, раздел «Инфоблоки»)"
tags: [инфоблоки, списки, iblock, orm, хранение, оргструктура]
sources: []
related: ["[[concept-highload-blocks]]", "[[entity-smart-process]]", "[[concept-d7-orm-query]]", "[[entity-user-absence]]", "[[recipe-slow-query-diagnostics]]"]
aliases: []
updated: "2026-09-24"
---

# Инфоблоки и «Списки» портала

**TL;DR:** инфоблок — таблица с элементами, разделами и произвольными свойствами. В Битрикс24 на
инфоблоках стоит больше, чем кажется: «Списки», оргструктура, отсутствия, фотогалереи, процессы.
Понимать их нужно даже тем, кто «делает только CRM».

## Что это

| Понятие | Что значит |
|---|---|
| **тип инфоблока** | группа инфоблоков с похожей структурой; определяет, есть ли разделы |
| **инфоблок** | сама «таблица»: фиксированные поля плюс свойства |
| **элемент** | запись |
| **раздел** | группировка элементов, иерархия поддерживается |
| **свойство** | дополнительное поле: строка, число, список, привязка к элементу или разделу, справочник |

**Версия хранения свойств** решает, как лежат данные: первая — общая таблица
`b_iblock_element_property`, вторая — отдельные `b_iblock_element_prop_s<ID>` и `…_m<ID>`. На
больших объёмах разница ощутима: вторая версия быстрее выбирает свойства.

## Где это в Битрикс24 (по стенду)

Типы инфоблоков портала: `bitrix_processes` (заявки и процессы вроде отпусков и командировок),
`lists` и `lists_socnet` («Списки» в разделах и группах), `structure` (оргструктура: подразделения,
отсутствия, доска почёта, история состояний), `photos`, `news`, `events`, `services`,
`CRM_PRODUCT_CATALOG`, `rest_entity`.

Отсюда практический вывод: правка «Списка» или графика отсутствий — это работа с инфоблоком
([[entity-user-absence]]), и все ограничения инфоблоков к ней применимы.

## Три API

| Слой | Классы | Для чего |
|---|---|---|
| классический | `CIBlockElement`, `CIBlockSection`, `CIBlockProperty`, `CIBlockType` | структура: создать инфоблок, свойства, права, переиндексация, ресайз картинок |
| ORM D7 | `Bitrix\Iblock\ElementTable`, `SectionTable`, `PropertyTable` | типизированные выборки |
| скомпилированная ORM | `IblockTable::compileEntity('News')` → `$entity->getDataClass()`, классы `Bitrix\Iblock\Elements\Element<ApiCode>Table` | повседневный CRUD конкретного инфоблока |

Рекомендация документации — **смешивать осознанно**: данные читаем и пишем скомпилированной ORM,
структурные операции делаем классическим API. `compileEntity()` на стенде есть.

## Чтение и запись через объект

```php
$entity  = \Bitrix\Iblock\IblockTable::compileEntity('News');
$class   = $entity->getDataClass();

$element = $class::getList(['select' => ['ID', 'NAME', 'AUTHOR']])->fetchObject();
$element->getName();
$element->get('AUTHOR')?->getValue();          // строковое свойство
$element->get('SOURCE')->getItem()->getValue(); // свойство-список
$element->get('TAGS')->getAll();                // множественное

$new = $class::createObject()
    ->setName('Заголовок')
    ->setCode('code')
    ->set('AUTHOR', 'Иванов')
    ->addTo('TAGS', 'важное');
$new->save();
```

Файловое свойство передаётся объектом `PropertyValue($fileId, 'описание')`, свойство-список —
идентификатором значения, HTML — строкой разметки.

## Подводные камни

- **Не смешиваем параметры двух API.** Массив фильтра `getList()` ORM и `CIBlockElement::GetList()`
  выглядят похоже, но работают по-разному ([[concept-d7-orm-query]]).
- **`select` обязателен.** Выбрать элемент «со всеми свойствами» — стандартный способ уронить
  страницу ([[recipe-slow-query-diagnostics]]).
- **Поиск и SEO живут отдельно:** после удаления элемента нужен `CIBlockElement::UpdateSearch($id)`,
  после изменения SEO-шаблонов — сброс их кэша.
- **Справочник в свойстве (`directory`) хранит `UF_XML_ID`**, а не `ID`
  ([[concept-highload-blocks]]).
- **«Список» — это инфоблок**, и его элементы участвуют в бизнес-процессах; менять его структуру на
  живом портале так же опасно, как менять поля сущности CRM.

## Что выбрать

Сравнение хранилищ (highload-блок, инфоблок, смарт-процесс, своя таблица) — в
[[concept-highload-blocks]].

## Связанные страницы
- [[concept-highload-blocks]] — плоские справочники
- [[entity-smart-process]] — когда нужна карточка и стадии
- [[entity-user-absence]] — отсутствия, которые лежат в инфоблоке

[← Ядро D7](_index-core-d7.md)
