---
title: "\\CCrmStatus и \\Bitrix\\Crm\\StatusTable"
type: entity
module: crm
edition: box
status: verified
provenance: documented
verified: "2026-06-02 / документация модуля CRM, раздел словарей (apidocs.bitrix24.ru)"
tags: [crm, справочники, статусы, стадии, класс, кэш]
sources: ["[[source-devbook-crm]]"]
related: ["[[concept-crm-dictionaries]]", "[[entity-crm-factory]]", "[[concept-bitrix-naming-conventions]]", "[[pattern-crm-sales-funnel-design]]"]
aliases: ["bitrix24-ccrm-status"]
updated: "2026-09-18"
---

# `\CCrmStatus` и `\Bitrix\Crm\StatusTable`

**Что это:** два класса к одной таблице `b_crm_status`. Старый `CCrmStatus` кэширует и годится для
записи; D7-шный `StatusTable` — **только для чтения**.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | классы |
| Модуль | `crm` |
| Таблица | `b_crm_status` — все справочники CRM в одной |
| Чтение и ORM-связи | `\Bitrix\Crm\StatusTable` (`DataManager`) |
| Любое изменение | `\CCrmStatus` |

Почему так — разобрано в [[concept-crm-dictionaries]]: `CCrmStatus` держит кэш, и запись мимо него
оставляет систему с устаревшими данными.

## `CCrmStatus` — чтение (статические, с кэшем)

| Метод | Возврат |
|---|---|
| `GetStatus($entityId)` | полные структуры всех значений |
| `GetStatusList($entityId)` | `STATUS_ID => NAME` |
| `GetStatusListEx($entityId)` | то же с экранированием |
| `GetFirstStatusID($entityId)` | код первого значения по `SORT` |
| `GetEntityTypes()` | каталог всех зарегистрированных справочников + `SEMANTIC_INFO` |

## `CCrmStatus` — изменение (на экземпляре)

```php
$entity = new \CCrmStatus('SOURCE');   // ENTITY_ID — в конструктор
$id = $entity->Add($fields);           // int | false
$entity->Update($id, $fields, $opts);
$entity->Delete($id);
$entity->GetLastError();
```

Массово: `CCrmStatus::BulkCreate($entityId, $items)`, `CCrmStatus::Erase($entityId)`.

**`Update` меняет только** `SORT`, `NAME`, `SYSTEM`, `COLOR`, `SEMANTICS`. Для остального нужны
опции: `'ENABLE_STATUS_ID' => true`, `'ENABLE_NAME_INIT' => true`.

## Поля `b_crm_status`

| Поле | Назначение |
|---|---|
| `ID` | первичный ключ |
| `ENTITY_ID` | какой справочник (`SOURCE`, `STATUS`, `DEAL_STAGE`, …) |
| `STATUS_ID` | символьный код значения |
| `NAME` / `NAME_INIT` | отображаемое / исходное (для сброса к системному) |
| `SORT` | порядок |
| `SYSTEM` | `Y`/`N` — можно ли удалить пользователю |
| `CATEGORY_ID` | направление (для лидов и сделок) |
| `COLOR` | цвет |
| `SEMANTICS` | семантика статуса |

## Семантика воронки — в метаданных

```php
'STATUS' => ['SEMANTIC_INFO' => [
    'START_FIELD'           => 'NEW',
    'FINAL_SUCCESS_FIELD'   => 'CONVERTED',
    'FINAL_UNSUCCESS_FIELD' => 'JUNK',
    'FINAL_SORT'            => 0,
]]
```

Старт, успех и провал описаны данными, а не зашиты в коде — поэтому модуль может
зарегистрировать свой справочник с правильной семантикой.

## Подводные камни

- **`Delete($id)` работает по первичному ключу, а не по `STATUS_ID`.** Перепутать легко.
- **Удаление несуществующего `ID` даёт фатальную ошибку**, а не `false`.
- **`Delete` не проверяет принадлежность `ID` справочнику из конструктора.** `ENTITY_ID` нужен
  только для сброса кэша — ошибка в коде **тихо удалит чужое значение** из другого справочника.
- Изменение справочников через `StatusTable` противопоказано: см. выше про кэш.
- Шаг `SORT` делайте 10 с началом в 10 — останется место вставить значение между.

## Связанное
- [[concept-crm-dictionaries]] — зачем это знать при внедрении
- [[entity-crm-factory]] — `getStages()` работает поверх этих справочников

[← CRM](_index-crm.md)
