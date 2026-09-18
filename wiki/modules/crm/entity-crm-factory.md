---
title: "\\Bitrix\\Crm\\Service\\Factory"
type: entity
module: crm
edition: box
status: verified
provenance: documented
verified: "2026-06-02 / документация Universal API CRM (apidocs.bitrix24.ru)"
tags: [crm, universal-api, фабрика, класс, d7, стадии, направления]
sources: []
related: ["[[concept-crm-universal-api]]", "[[entity-crm-container]]", "[[entity-crm-item]]", "[[entity-crm-operation]]", "[[concept-crm-dictionaries]]"]
aliases: ["bitrix24-crm-factory"]
updated: "2026-09-18"
---

# `\Bitrix\Crm\Service\Factory`

**Что это:** рабочая лошадка Universal API — по фабрике на тип CRM-сущности. Выдаёт элементы,
операции, направления и стадии, отвечает на вопросы о возможностях типа.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | абстрактный класс; наследники по типам сущностей |
| Модуль | `crm` |
| Наследник для смарт-процессов | `\Bitrix\Crm\Service\Factory\Dynamic` |
| Откуда берётся | `Container::getInstance()->getFactory($entityTypeId)` |
| Имя сервиса (для подмены) | `crm.service.factory.dynamic.<entityTypeId>` |

## Четыре группы методов

### 1. Элементы

| Метод | Назначение |
|---|---|
| `getItem(int $id): ?Item` | по первичному ключу |
| `getItems(array $parameters = []): array` | фильтр, сортировка, `limit`/`offset` — близко к ORM |
| `getItemsFilteredByPermissions($parameters, ?int $userId, string $operation)` | то же с проверкой прав |
| `getItemsCount(array $filter = []): int` | подсчёт |
| `createItem(array $data = []): Item` | новый объект, **не сохранён** |
| `getFieldsCollection()` | коллекция полей — нужна для файлов и для перечня всех полей |

### 2. Операции

```php
$factory->getAddOperation($item): Operation\Add
$factory->getUpdateOperation($item, $context = null): Operation\Update
$factory->getDeleteOperation($item, $context = null): Operation\Delete
$factory->getCopyOperation($item): Operation\Copy
$factory->getConversionOperation($item): Operation\Conversion
```

Подробно — [[entity-crm-operation]].

### 3. Направления и стадии

```php
$factory->getCategories(): Category[]
$factory->getDefaultCategory(): ?Category
$factory->getCategory(int $id): ?Category
$factory->getCategoryByCode(string $code): ?Category
$factory->createCategory(array $fields): Category   // объект, не сохраняет

$factory->getStagesEntityId(?int $categoryId = null): ?string   // 'DEAL_STAGE'
$factory->getStages(?int $categoryId = null): EO_Status_Collection
$factory->getStage(string $statusId): ?EO_Status
$factory->getStageSemantics(string $stageId): ?string
```

Удаление направления — **не через фабрику**, а через объект: `$category->delete()`. Если
направлений не создавали, вернётся одно служебное, которое удалить нельзя.

Под капотом стадии — это [[concept-crm-dictionaries|справочники CRM]] (`b_crm_status`): фабрика их
инкапсулирует, но не отменяет.

### 4. Метаданные и признаки типа

| Метод | Пример для сделки |
|---|---|
| `getEntityTypeId()` | `2` |
| `getUserFieldEntityId()` | `'CRM_DEAL'` — **канонический `ENTITY_ID` для пользовательских полей** |
| `getEntityName()` | `'DEAL'` |
| `getEntityAbbreviation()` | `'D'` |
| `getEntityDescription()` / `…InPlural()` | «Сделка» / «Сделки» |
| `getFieldCaption($code)` / `getFieldValueCaption($code, $value)` | подпись поля и читаемое значение |
| `getFieldsInfo()` / `getUserFieldsInfo()` | описания полей |

Плюс 20+ предикатов: `isCategoriesSupported`/`isCategoriesEnabled`, `isStagesSupported`/
`isStagesEnabled`, `isAutomationEnabled`, `isBizProcEnabled`, `isObserversEnabled`,
`isRecyclebinEnabled`, `isClientEnabled`, `isLinkWithProductsEnabled` и другие.

**`*Supported` ≠ `*Enabled`:** первое — техническая возможность типа, второе — текущая настройка.

## Подводные камни

- **`getUserFieldEntityId()` не угадывать.** У смарт-процесса это `CRM_<ID типа>`, а **не**
  `CRM_<ENTITY_TYPE_ID>`: у типа с `ENTITY_TYPE_ID = 162` поля лежат под `CRM_4` и называются
  `UF_CRM_4_…`. См. [[recipe-smart-process-programmatic-creation]].
- **`getFieldsInfo()` не возвращает `UF_CRM_*`.** Для полного перечня полей —
  `getFieldsCollection()->getFieldNameList()`. На этом ломается история изменений
  ([[recipe-crm-history-all-fields]]).
- **Счёт, документ и B2E-документ** работают на API динамических типов, но имеют свои фабрики
  (`Factory\SmartInvoice` и другие). Обычные смарт-процессы: ID 128–191 либо ≥ 1030 и чётный.
- `getUpdateOperation()` и `getAddOperation()` не `final` — на этом стоит подмена. Но сигнатуры
  могут измениться при обновлении продукта: сверять рефлексией
  ([[pattern-module-self-disabling-guard]]).

## Связанное
- [[entity-crm-container]] — где берут фабрику
- [[entity-crm-item]], [[entity-crm-operation]] — что она выдаёт

[← CRM](_index-crm.md)
