---
title: "Universal API CRM: Container → Factory → Item + Operation"
type: concept
module: crm
edition: box
status: verified
provenance: documented
verified: "2026-06-02 / документация Universal API CRM (apidocs.bitrix24.ru)"
tags: [crm, universal-api, d7, фабрика, операции, servicelocator]
sources: ["[[source-devbook-crm]]"]
related: ["[[pattern-crm-action-vs-event]]", "[[recipe-crm-history-all-fields]]", "[[recipe-smart-process-programmatic-creation]]", "[[concept-crm-dictionaries]]", "[[entity-smart-process]]"]
aliases: ["bitrix24-crm-ua"]
updated: "2026-09-18"
---

# Universal API CRM

**TL;DR:** современный единообразный способ работы с CRM-сущностями в коробке. Четыре кубика —
`Container` → `Factory` → `Item` + `Operation`. Расширяется **действиями операций**, а не
обработчиками событий.

## Что это и как работает

Universal API появился вместе со смарт-процессами и решает три задачи: убрать дублирование кода,
снизить связность классов и сделать CRM тестируемой. Плата — заметно выросшая сложность изучения,
которая компенсируется единообразием: один и тот же код работает со сделкой, счётом и
смарт-процессом.

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
| Фабрика | `\Bitrix\Crm\Service\Factory` + наследники (`Factory\Dynamic` — смарт-процессы) |
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

Шесть шагов всегда одни: фабрика из контейнера → элемент из фабрики → правка полей → операция из
фабрики → передать элемент → запустить.

## Где включён

- **Всегда:** смарт-процессы, счета, предложения, документы.
- **Опционально:** лиды, сделки, контакты, компании (переключается через
  `\Bitrix\Crm\Settings\<Type>Settings`: `isFactoryEnabled` / `setFactoryEnabled`).
- Направление развития — включение для всех типов.

Перед тем как писать код под UA для сделки или лида, проверьте, включён ли он на этом портале:
иначе часть логики уйдёт мимо.

## Почему важно при внедрении

- Это **точка расширения уровня 2** в [[concept-change-invasiveness-hierarchy]]: вмешиваемся
  легитимно, ядро не трогаем.
- Главный паттерн расширения — добавить `Action` в операцию через подмену фабрики конкретного
  типа. Подробное сравнение с событиями — [[pattern-crm-action-vs-event]].
- Подменять фабрику **одного типа** (`crm.service.factory.dynamic.<entityTypeId>`), а не контейнер
  целиком: подмена контейнера конфликтует с приложениями маркетплейса.

## Облако vs коробка

UA — API коробки (PHP). В облаке те же сущности доступны только через REST: `crm.item.*`,
`crm.type.*`. Практики, написанные под `Operation\Action`, в облако **не переносятся** — там
эквивалент это роботы, БП и обработчики событий REST.

## Открытые вопросы
- Полный список сервисов `Container::getXxx()`.
- Что внутри `\Bitrix\Crm\Service\Context` помимо `getUserId()` и `getScope()`.
- Конструктор `Operation\Conversion` (конвертация лида) — не разобран.

## Связанные страницы
- [[concept-crm-dictionaries]] — справочники и стадии под фабрикой
- [[recipe-smart-process-programmatic-creation]] — создание типа и полей кодом
- [[concept-bitrix-naming-conventions]] — как называются классы обоих поколений

[← CRM](_index-crm.md)
