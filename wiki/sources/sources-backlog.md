---
title: "Бэклог источников"
type: concept
module: sources
edition: both
status: verified
confidence: high
provenance: documented
verified: "2026-06-19"
tags: [источники, бэклог, ингест, очередь]
sources: []
related: ["[[src-b24-crm-deal-add]]"]
updated: "2026-06-19"
---

# Бэклог источников

Очередь источников на ингест с классификацией и привязкой к таксономии. Так метод масштабируется:
**источники-каталоги** не читаются разом — они порождают список конкретных под-источников, которые
ингестим по одному. Снимок в `raw/sources/` создаётся при ингесте конкретного источника.

## Как обрабатываем по типу
- **Каталог-указатель** (список ссылок) → завести в бэклог под-источники, приоритизировать, ингестить инкрементально.
- **Содержание** (статья/урок/глава) → конспект в `wiki/sources/` + вплетение фактов в страницы модулей.
- **REST API** → через MCP (`apidocs.bitrix24.ru`), а не скрейпингом. `api_help` = старое ядро (коробка), legacy.

## Принятые источники — 2026-06-19

| # | Источник | Тип | Куда мапится | Приоритет | План ингеста |
|---|----------|-----|--------------|-----------|--------------|
| 1 | [Курс 43 «Разработчик Bitrix Framework»](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43) (урок [Пространства имён](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=3524)) | содержание (серия уроков) | [development/](../development/core-d7/_index.md) | **P1** | поурочно; начать с «Пространства имён» → `core-d7` |
| 2 | [bx24devbook — Книга разработчика](https://bx24devbook.website.yandexcloud.net/Dokumentacia/Spravocnik.html) | содержание (многоглавный) | [development/](../development/core-d7/_index.md) + [modules/](../modules/crm/_index.md) | **P1** | по главам (см. карту ниже) |
| 3 | [awesome-bitrix](https://github.com/awesomebitrix/awesome-bitrix) | каталог-указатель | мета (порождает под-источники) | **P2** | разобрать по категориям → завести под-источники |
| 4 | [api_help (старое ядро)](https://dev.1c-bitrix.ru/api_help/) | каталог-указатель (legacy) | [development/](../development/core-d7/_index.md) | **P3** | по требованию: только когда нужен конкретный метод старого ядра; сначала смотреть D7/devbook/MCP |

## Карта глав devbook → страницы вики
- Ядро, URI, страницы, шаблоны → [development/core-d7](../development/core-d7/_index.md), [templates-design](../development/templates-design/_index.md)
- Git, структура `/local/`, миграции → [development/migrations](../development/migrations/_index.md), [core-d7](../development/core-d7/_index.md)
- Технологии: отложенные функции, агенты, события, service locator, валидация → [core-d7](../development/core-d7/_index.md), [performance](../development/performance/_index.md)
- UI-компоненты: тулбары, фильтры, кнопки, таблицы → [development/components](../development/components/_index.md)
- Модуль CRM (лиды, сделки, СПА, заказы) → [modules/crm](../modules/crm/_index.md), [smart-process](../modules/smart-process/_index.md)
- Интранет (оргструктура, сотрудники) → [modules/hr](../modules/hr/_index.md), [collaboration](../modules/collaboration/_index.md)
- Бизнес-процессы (свои действия) → [modules/bizproc](../modules/bizproc/_index.md)

## Под-источники из awesome-bitrix (категория → раздел вики)
- Учебные курсы → [playbooks](../cross-cutting/playbooks/_index.md) / development
- Документация (dev-доки, JS, D7; REST — через MCP) → [rest-integrations](../modules/rest-integrations/_index.md), development
- Инструменты (BitrixVM, server-test, Docker, сниппеты) → [server-admin](../development/server-admin/_index.md), [performance](../development/performance/_index.md)
- Библиотеки PHP/JS (DI, Webpack, Vue) → [core-d7](../development/core-d7/_index.md), [templates-design](../development/templates-design/_index.md)
- Дистрибутивы → [administration](../modules/administration/_index.md)
- Блоги / Статьи (Intervolga, Prominado, D7, MySQL, интеграция с 1С) → по теме статьи в соответствующий модуль
- GitHub-организации (модули, компоненты, SDK) → [modules-custom](../development/modules-custom/_index.md), [rest-integrations](../modules/rest-integrations/_index.md)
- Видеодоклады / Конференции / Другое (KB, FAQ, форумы, Telegram) → завести как точечные под-источники по мере надобности

[← Конспекты источников](_index.md)
