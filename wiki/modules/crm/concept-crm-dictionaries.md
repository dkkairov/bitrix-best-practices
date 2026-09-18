---
title: "Справочники CRM: новое API читает, старое пишет"
type: concept
module: crm
edition: box
status: verified
provenance: documented
verified: "2026-06-02 / документация модуля CRM (apidocs.bitrix24.ru)"
tags: [crm, справочники, статусы, стадии, b_crm_status, кэш]
sources: ["[[source-devbook-crm]]"]
related: ["[[concept-crm-universal-api]]", "[[concept-bitrix-naming-conventions]]", "[[pattern-crm-sales-funnel-design]]", "[[antipattern-crm-stage-explosion]]"]
aliases: ["bitrix24-crm-spravochniki"]
updated: "2026-09-18"
---

# Справочники CRM

**TL;DR:** все перечисления CRM — источники, типы компаний, стадии лидов и сделок — лежат в одной
таблице `b_crm_status`. Читать через `\Bitrix\Crm\StatusTable`, **писать только через `CCrmStatus`**.

## Что это и как работает

Справочник («статус», `crm_status`) — простое перечисление значений. Все справочники объединены в
таблице `b_crm_status` ключом `ENTITY_ID`.

| Поле | Смысл |
|---|---|
| `ENTITY_ID` | какой справочник (`SOURCE`, `STATUS`, `DEAL_STAGE`, …) |
| `STATUS_ID` | символьный код значения внутри справочника |
| `NAME` | отображаемое название (пользователь может менять) |
| `NAME_INIT` | исходное название — для «сбросить к системному» |
| `SORT` | порядок |
| `SYSTEM` | `Y`/`N` — можно ли удалить пользователю |
| `CATEGORY_ID` | направление (для лидов и сделок) |
| `COLOR` | цвет |
| `SEMANTICS` | семантика статуса |

`CATEGORY_ID` / `COLOR` / `SEMANTICS` — надстройка ради стадий лидов и сделок: изначально таблица
проектировалась под плоские перечисления.

## Парадокс: новое API только для чтения

| Задача | Класс | Почему |
|---|---|---|
| ORM-выборка, связи | `\Bitrix\Crm\StatusTable` | стандартный путь D7 |
| Любое изменение | `\CCrmStatus` (старый API) | он **кэширует**; запись мимо него оставляет систему с устаревшим кэшем |

Документация формулирует это жёстко: изменять справочники через `StatusTable` противопоказано —
часть системных статусов кэшируется, и последствия могут быть необратимыми.

Это прямое исключение из правила «D7 — современный путь»,
см. [[concept-bitrix-naming-conventions]].

## `CCrmStatus` — что нужно знать

**Чтение** (статические методы, с кэшем): `GetStatus($entityId)` — полные структуры;
`GetStatusList($entityId)` — `STATUS_ID => NAME`; `GetStatusListEx()` — то же с экранированием;
`GetFirstStatusID($entityId)`; `GetEntityTypes()` — все зарегистрированные справочники.

**Изменение** (на экземпляре с нужным `ENTITY_ID`): `Add`, `Update`, `Delete`; массово —
`BulkCreate($entityId, $items)`, `Erase($entityId)`.

Тонкости, на которых спотыкаются:

- `Update` меняет только `SORT`, `NAME`, `SYSTEM`, `COLOR`, `SEMANTICS`. Для `STATUS_ID` и
  `NAME_INIT` нужны опции `ENABLE_STATUS_ID` / `ENABLE_NAME_INIT`.
- `Delete($id)` работает **по первичному ключу**, не по `STATUS_ID`. Удаление несуществующего →
  фатальная ошибка.
- `Delete` **не проверяет**, что `ID` принадлежит указанному `ENTITY_ID` — имя справочника нужно
  только для очистки кэша. Ошибка в коде тихо удалит чужое значение.

## Семантика воронки живёт в метаданных

```php
'STATUS' => [
    'SEMANTIC_INFO' => [
        'START_FIELD'           => 'NEW',
        'FINAL_SUCCESS_FIELD'   => 'CONVERTED',
        'FINAL_UNSUCCESS_FIELD' => 'JUNK',
        'FINAL_SORT'            => 0,
    ],
]
```

Старт, успех и провал — не зашиты в коде, а описаны метаданными справочника. Поэтому модуль может
зарегистрировать свой справочник с правильной семантикой воронки.

## Почему важно при внедрении

Проектирование воронки ([[pattern-crm-sales-funnel-design]]) — это, по сути, наполнение
справочника стадий. Отсюда же цена [[antipattern-crm-stage-explosion|взрыва стадий]]: каждая
стадия — строка в общей таблице, которую видят отчёты, фильтры и автоматизация.

Методы фабрики Universal API по стадиям (`getStagesEntityId`, `getStages`, `getStage`,
`getStageSemantics`) — обёртки над этими же справочниками: UA не отменяет `b_crm_status`, а
инкапсулирует его.

## Практики
- Шаг `SORT` — 10, начиная с 10: остаётся место вставить значение между.
- В своих справочниках `NAME_INIT` можно не заполнять — откатывать к системному нечего.

## Открытые вопросы
- Полный список системных `ENTITY_ID`.
- Полный набор значений `SEMANTICS`.
- Где именно живёт кэш `CCrmStatus::GetStatus` и как его гарантированно сбросить, если запись
  всё-таки прошла через ORM.

## Связанные страницы
- [[concept-crm-universal-api]] — фабрика и её обёртки над стадиями

[← CRM](_index-crm.md)
