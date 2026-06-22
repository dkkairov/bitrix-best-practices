---
title: "Bitrix Framework vs Bitrix24: движок и продукт"
type: concept
module: core-d7
edition: both
status: verified
provenance: mixed
verified: "2026-06-21 / Bitrix Framework + Bitrix24 (cloud/box); REST-событие onCrmDealAdd — apidocs.bitrix24.ru"
tags: [платформа, архитектура, framework, bitrix24, облако, коробка]
sources: ["[[source-b24-crm-deal-add]]"]
related: ["[[concept-code-namespaces-and-autoloading]]", "[[concept-dev-standards]]", "[[antipattern-box-core-modification]]", "[[pattern-rest-webhooks-and-events]]"]
aliases: ["bitrix-framework-vs-bitrix24"]
updated: "2026-06-21"
---

# Bitrix Framework vs Bitrix24: движок и продукт

**TL;DR:** **Bitrix Framework** — движок (PHP-платформа, продаётся как «1С-Битрикс: Управление
сайтом»). **Bitrix24** — готовый продукт (CRM/портал) поверх этого движка. Граница определяет,
*что где дорабатывается*: ядро, `/local/`, свои модули — Framework (коробка); CRM/задачи/БП — модули
Bitrix24; **облако** дорабатывается только через REST.

## Bitrix Framework (движок)
- PHP-платформа и CMS-ядро: модуль `main`, ORM (D7), события, компоненты, шаблоны, механизм модулей.
- Поставляется отдельно как **«1С-Битрикс: Управление сайтом»** (БУС).
- По смыслу — «битриксовый фреймворк» для построения сайтов и приложений.

## Bitrix24 (продукт)
- Корпоративный портал: CRM, задачи и проекты, бизнес-процессы, чат, диск, телефония.
- Технически — **набор модулей** (`crm`, `tasks`, `bizproc`, `im`, `socialnetwork`…) **поверх Framework**.
- Две редакции: **облако** (SaaS) и **коробка** (self-hosted).

## Слои
```text
Bitrix24 (продукт):  crm · tasks · bizproc · im · …      ← модули продукта
──────────────────────────────────────────────────
Bitrix Framework:    main · ORM(D7) · события ·          ← движок
                     компоненты · шаблоны · модули
```
Bitrix24 не заменяет Framework, а надстраивается над ним: всё «движковое» (события, ORM, `/local/`,
свои модули) в Bitrix24-коробке работает так же, как в БУС.

## Что к чему относится
| Элемент | Чьё | Пояснение |
|---------|-----|-----------|
| Ядро `main`, ORM D7, компоненты, шаблоны | **Framework** | механизмы движка |
| `/local/` и все его папки | **Framework** | способ расширения движка (**только коробка**) |
| Модули `crm`, `tasks`, `bizproc`, `im` | **Bitrix24** | сущности и логика продукта |
| PHP-обработчик в `init.php` на изменение сделки | **Framework + Bitrix24** | механизм событий — движок; сущность «сделка» — продукт |
| REST `crm.deal.add`, событие `onCrmDealAdd`, приложения | **Bitrix24 (REST)** | способ дорабатывать **облако** |

Граница проходит **не по папкам, а по содержимому**: механизм `init.php` — Framework; обработчик
на сущность CRM внутри него — Bitrix24.

## Два разных «события» — частая путаница
Одно и то же «создалась сделка» обрабатывается **по-разному** в зависимости от редакции:
- **Коробка (PHP-событие Framework):** обработчик в `local/php_interface/init.php`, регистрируется
  через `EventManager` (D7) / `AddEventHandler`. Прямой доступ к ядру и БД.
- **Облако/приложения (REST-событие Bitrix24):** `onCrmDealAdd` (scope `crm`), подписка через
  `event.bind`, обработчик получает POST-запрос на свой URL — см.
  [[pattern-rest-webhooks-and-events|Вебхуки и события]].

## Почему это важно для внедрения
- **Облако ≠ коробка.** Коробка: `/local/`, PHP, события ядра, свои модули. Облако: только
  REST/вебхуки и приложения Маркетплейса — PHP-доработку из коробки в облако не перенести.
- **Компоненты/шаблоны — наследие «сайтов».** Интерфейс портала Bitrix24 на них почти не строится
  (он на React); они нужны для публичной части/лендингов.
- При выборе решения первый вопрос — **облако или коробка**: от него зависит весь способ доработки
  (см. [[antipattern-box-core-modification|не править ядро]], [[concept-code-namespaces-and-autoloading|код в /local/]]).

## Связанное
- [[concept-code-namespaces-and-autoloading|Организация кода и /local/]] — как расширять коробку
- [[concept-dev-standards|Стандарт разработки (коробка)]]
- [[pattern-rest-webhooks-and-events|Вебхуки и события]] — как дорабатывают облако
- [[antipattern-box-core-modification|Правка ядра коробки]]

[← Ядро D7](_index-core-d7.md)
