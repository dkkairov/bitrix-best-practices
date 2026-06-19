---
id: "S-SS01"
title: "Конспект: офф. документация метода crm.deal.add"
type: source-summary
module: rest-integrations
edition: both
status: verified
confidence: high
provenance: documented
verified: "2026-06-19 / apidocs.bitrix24.ru"
tags: [rest, crm, метод, deal]
sources: []
related: ["[[m11-pt01-rest-webhooks-and-events-pattern]]", "[[m01-pt01-crm-sales-funnel-design-pattern]]"]
aliases: ["src-b24-crm-deal-add"]
updated: "2026-06-20"
---

# Конспект: офф. документация метода `crm.deal.add`

## Об источнике
| Поле | Значение |
|------|----------|
| Тип | официальная документация REST |
| Издатель | Bitrix24 |
| URL | https://apidocs.bitrix24.ru/api-reference/crm/deals/crm-deal-add.html |
| Сверено через | MCP (`bitrix-method-details`) |

## TL;DR
`crm.deal.add` создаёт новую сделку. Принимает объект `fields` (поля сделки) и необязательный
`params`; возвращает ID созданной сделки. Scope: `crm`.

## Ключевые тезисы
- **Параметры:** `fields` (обязательный, объект полей), `params` (необязательный).
- **Возврат:** `result` — ID сделки; `time` — тайминги запроса.
- **Стадия и направление:** `STAGE_ID` задаётся в формате `C{категория}:{статус}` (например,
  `C1:NEW`), `CATEGORY_ID` — направление. Это подтверждает модель из
  [[m01-pt01-crm-sales-funnel-design-pattern|Проектирование воронки]].
- **Полезные поля:** `TITLE`, `OPPORTUNITY`/`CURRENCY_ID`, `CONTACT_IDS`, `COMPANY_ID`,
  `BEGINDATE`/`CLOSEDATE`, `SOURCE_ID`, UTM-поля, пользовательские `UF_CRM_*`.
- **`params.REGISTER_SONET_EVENT`** — регистрировать ли событие в Живой ленте.
- **Типичные ошибки:** `ACCESS_DENIED`/`403`, `QUERY_LIMIT_EXCEEDED`/`503` (лимиты),
  `expired_token`/`401`, `insufficient_scope`/`403`.
- Доступны примеры вызова: входящий вебхук (cURL), `BX24.callMethod` (JS), CRest/SDK (PHP).

## Что встроено в вики
- [[m11-pt01-rest-webhooks-and-events-pattern|Вебхуки и события]] — использован как пример дозапроса данных и вызова метода.
- [[m01-pt01-crm-sales-funnel-design-pattern|Проектирование воронки]] — подтверждён формат `CATEGORY_ID` + `STAGE_ID`.

## Противоречия с текущими страницами
- Нет.

## Открытые вопросы
- Зафиксировать аналогичные конспекты для `crm.deal.update`, `crm.deal.get` и событий сделок.

[← Конспекты источников](_index.md)
