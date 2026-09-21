---
title: "Universal API CRM: Container → Factory → Item + Operation"
type: concept
module: crm
edition: box
status: verified
provenance: mixed
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Модуль CRM — Универсальное API, Смарт-процессы, Счёт, Предложение; строка про REST облака — не сверялась"
tags: [crm, universal-api, d7, фабрика, операции, servicelocator]
sources: ["[[source-devbook-crm]]"]
related: ["[[pattern-crm-action-vs-event]]", "[[recipe-crm-history-all-fields]]", "[[recipe-smart-process-programmatic-creation]]", "[[concept-crm-dictionaries]]", "[[entity-smart-process]]", "[[recipe-smart-process-factory-customization]]", "[[recipe-crm-legacy-entity-crud]]", "[[recipe-crm-lead-conversion]]"]
aliases: ["bitrix24-crm-ua"]
updated: "2026-09-21"
---

# Universal API CRM

**TL;DR:** современный единообразный способ работы с CRM-сущностями в коробке. Четыре кубика —
`Container` → `Factory` → `Item` + `Operation`. Расширяется **действиями операций**, а не
обработчиками событий. Источник — глава «Универсальное API» «Книги разработчика»
([Концепция](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Koncepcia.html)).

> **Сверено с книгой 2026-09-21.** Атрибуция исправлена: материал — из книги, а не из apidocs
> (там REST). Уточнены: где UA включён и его «срок годности» для старого API, имя сервиса фабрики
> (только смарт-процессы), позиция книги по подмене контейнера, отличия счёта и КП.

## Что это и как работает

Universal API появился вместе со смарт-процессами (они в CRM с версии 20.700.0) и решает три
задачи: убрать дублирование кода, снизить связность классов и сделать CRM тестируемой. Плата —
заметно выросшая сложность изучения, которая компенсируется единообразием интерфейса.

```
ServiceLocator ──'crm.service.container'──► Container (singleton)
                                                │
                     getFactory($entityTypeId)  │  getContext, getFileUploader,
                                                ▼  getDynamicTypeDataClass …
                                            Factory
                                                │ getItem / getItems / createItem
                                                │ getAddOperation / getUpdateOperation / …
                                                ▼
                                   Item  ──►  Operation (+ Action)
```

| Кубик | Класс |
|---|---|
| Контейнер | `\Bitrix\Crm\Service\Container` |
| Фабрика | `\Bitrix\Crm\Service\Factory` + наследники (`Factory\Dynamic` — смарт-процессы, `Factory\SmartInvoice` — счета) |
| Элемент | `\Bitrix\Crm\Item` + наследники |
| Операция | `\Bitrix\Crm\Service\Operation\{Add,Update,Delete,Copy,Conversion}` |
| Действие | `\Bitrix\Crm\Service\Operation\Action` |

## Канонический сценарий «изменить элемент»

```php
use Bitrix\Crm\Service\Container;

$factory = Container::getInstance()->getFactory($entityTypeId);
$item    = $factory->getItem($id);
$item->setTitle('Новое название');
$result  = $factory->getUpdateOperation($item)->launch();   // \Bitrix\Main\Result
```

Для изменения шагов шесть: фабрика из контейнера → элемент из фабрики → правка полей → операция из
фабрики → передать элемент → запустить. Для создания элемент берут через `createItem()`. Между
получением операции и запуском может быть ещё шаг — **настроить операцию** (например, при массовой
правке отключить БП и роботов — [[entity-crm-operation]]).

## Где включён

По книге ([Как включить](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Kak_vklucit.html)):
- **Всегда:** смарт-процессы, счета (новые, `SmartInvoice`), коммерческие предложения, документы
  подсистемы подписи.
- **По настройке:** лиды, сделки, контакты, компании — переключается через
  `\Bitrix\Crm\Settings\<Type>Settings` (`isFactoryEnabled` / `setFactoryEnabled`) или параметром
  `?enableFactory=Y` в адресе раздела CRM; переключить может любой пользователь с доступом в CRM.
- **Срок годности старого API.** На момент написания книги поддержка UA для этих четырёх сущностей
  названа экспериментальной; позже её обещают включить принудительно, а старое поведение убрать
  ([Концепция](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Koncepcia.html#novoe-universal-noe-api)).
  Поэтому практики на старом API ([[recipe-crm-legacy-entity-crud]]) — с версией crm в `verified`.

Перед тем как писать код под UA для сделки или лида, проверьте, включён ли он на этом портале:
иначе часть логики уйдёт мимо.

**Единый интерфейс — не единое поведение.** Счёт — смарт-процесс с фиксированными настройками,
своими стадиями и доп. полями (`ACCOUNT_NUMBER`, `COMMENTS`); коммерческое предложение — фабрика
не на механике смарт-процесса, поведение во многом переопределено
([Счёт](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Scet.html),
[Предложение](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Predlozenie.html)).

## Почему важно при внедрении

- Это **точка расширения уровня 2** в [[concept-change-invasiveness-hierarchy]]: вмешиваемся
  легитимно, ядро не трогаем.
- Главный паттерн расширения — добавить `Action` в операцию через подмену фабрики. Сравнение с
  событиями — [[pattern-crm-action-vs-event]], рецепт — [[recipe-smart-process-factory-customization]].
- **Практика команды:** для смарт-процесса подменяем фабрику **одного типа**
  (`crm.service.factory.dynamic.<entityTypeId>` — это имя только для смарт-процессов), а не контейнер
  целиком. Книга описывает оба способа и подмену контейнера называет самым простым, но для одной
  фабрики он избыточен; кроме того, некоторые **модули** Маркетплейса сами подменяют контейнер
  ([Подмена фабрики](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Kastomizacia/Podmena_fabriki.html)).

## Облако vs коробка

UA — API коробки (PHP). В облаке те же сущности доступны только через REST (семейства `crm.item.*`,
`crm.type.*` — сверять по apidocs через MCP). Практики, написанные под `Operation\Action`, в облако
**не переносятся** — там эквивалент это роботы, БП и обработчики событий REST.

## Открытые вопросы
- Полный список сервисов `Container::getXxx()` (страница книги «Контейнер» дублирует «Концепцию»).
- Что внутри `\Bitrix\Crm\Service\Context` помимо `getUserId()` (у нас используется и `getScope()` —
  эмпирика, crm 26.800, [[recipe-crm-history-all-fields]]).
- `Operation\Conversion` книга только перечисляет; рабочая конвертация лида —
  [[recipe-crm-lead-conversion]] (старый механизм), КП из сделки — мастер конвертации.

## Связанные страницы
- [[concept-crm-dictionaries]] — справочники и стадии под фабрикой
- [[recipe-smart-process-programmatic-creation]] — создание типа и полей кодом
- [[concept-bitrix-naming-conventions]] — как называются классы обоих поколений

[← CRM](_index-crm.md)
