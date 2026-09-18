---
title: "Соглашения именования: CCrmDeal против \\Bitrix\\Crm\\DealTable"
type: concept
module: core-d7
edition: box
status: verified
provenance: documented
verified: "2026-06-01 / Книга разработчика Bitrix24 (dev.1c-bitrix.ru)"
tags: [разработка, d7, namespace, соглашения, crm]
sources: ["[[source-devbook-core-d7]]"]
related: ["[[concept-code-namespaces-and-autoloading]]", "[[concept-platform-reverse-engineering]]", "[[concept-crm-universal-api]]"]
aliases: ["bitrix24-naming-conventions"]
updated: "2026-09-18"
---

# Соглашения именования сущностей

**TL;DR:** за каждой бизнес-сущностью закреплено одно кодовое имя, и оно встречается во всех
связанных классах, таблицах и константах. Знаешь имя — знаешь, что грепать.

## Как это выглядит

Для сделки кодовое имя — `DEAL`:

| Артефакт | Пример | Слой |
|----------|--------|------|
| Старый C-класс | `CCrmDeal` | «старый» API |
| ORM-класс таблицы | `\Bitrix\Crm\DealTable` | D7 |
| Утилитарный класс | `\Bitrix\Crm\Recycling\DealBinder` | D7 |
| Глобальная константа | `SONET_CRM_DEAL_ENTITY` | соцсеть |

## Два поколения классов сосуществуют

- **Старое** — префикс `C…`: `CCrmDeal`, `CCrmLead`, `CCrmOwnerType`, `CCrmStatus`, `CCrmFieldMulti`.
- **Новое (D7)** — namespace `\Bitrix\…`: `\Bitrix\Crm\Service\Container`, `\Bitrix\Crm\Service\Factory`,
  `\Bitrix\Crm\Item`, `\Bitrix\Crm\Service\Operation\*`, `\Bitrix\Crm\StatusTable`.

Подробнее про размещение и автозагрузку своих классов — [[concept-code-namespaces-and-autoloading]].

## Ловушка: «новое» не всегда означает «вместо старого»

Для **справочников CRM** правило обратное общему: `\Bitrix\Crm\StatusTable` — только для чтения,
писать нужно через `CCrmStatus`. Это ломает интуицию «D7 — современный путь» и регулярно стоит
времени на отладке. См. [[concept-crm-dictionaries]].

## Компоненты и сервисы

| Что | Формат | Пример |
|-----|--------|--------|
| Компонент | `vendor:module.sub.sub` (вендор через `:`, иерархия через `.`) | `bitrix:main.ui.filter`, `bitrix:ui.toolbar`, `bitrix:ui.sidepanel.wrapper` |
| Сервис в ServiceLocator | `vendor.module.service` (всё через `.`) | `crm.service.factory.dynamic.<entityTypeId>` |

Имя сервиса фабрики важно практически: именно по нему подменяется фабрика конкретного
смарт-процесса — см. [[recipe-crm-history-all-fields]].

## Почему это про исследование, а не про стиль

Соглашение — второй из четырёх приёмов [[concept-platform-reverse-engineering]]: знание кодового
имени превращает «разобраться, как устроено» в один `grep`.

## Открытые вопросы
- Полный список кодовых имён сущностей CRM (`DEAL`, `LEAD`, `CONTACT`, `COMPANY`, `INVOICE`,
  `QUOTE`, …) — нужен отдельный источник.
- Исторические аномалии, где соглашение нарушено.

[← Ядро D7](_index-core-d7.md)
