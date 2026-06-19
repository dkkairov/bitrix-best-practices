---
title: "Обзор базы знаний"
type: concept
module: overview
edition: both
status: verified
updated: "2026-06-19"
---

# Обзор: лучшие практики внедрения Bitrix24

Накапливающаяся база знаний по методу **LLM Wiki** Карпати. Подробнее о том, как пользоваться —
[README](../README.md); правила и рабочие процессы — схема [CLAUDE.md](../CLAUDE.md).
Полный каталог страниц — [index.md](../index.md).

## Карта вики

### Продуктовые модули (облако и коробка)
- [CRM](modules/crm/_index.md) — сделки, лиды, воронки, контакты/компании, счета, аналитика
- [Задачи и проекты](modules/tasks-projects/_index.md) — задачи, проекты, скрам, шаблоны
- [Бизнес-процессы](modules/bizproc/_index.md) — БП, роботы, триггеры, дизайнер
- [Смарт-процессы (СПА)](modules/smart-process/_index.md) — свои сущности и автоматизация
- [Телефония](modules/telephony/_index.md) — звонки, интеграции с АТС, обзвоны
- [Сайты и магазины](modules/sites-stores/_index.md) — сайты, лендинги, CRM-формы, магазины
- [Коммуникации](modules/communications/_index.md) — чат, открытые линии, мессенджеры, почта
- [Совместная работа](modules/collaboration/_index.md) — новости, диск, документы, календарь
- [AI / CoPilot](modules/ai-copilot/_index.md) — AI-сценарии Bitrix24
- [HR](modules/hr/_index.md) — оргструктура, отсутствия, согласования
- [REST и интеграции](modules/rest-integrations/_index.md) — REST API, вебхуки, события, OAuth
- [Приложения маркетплейса](modules/marketplace-apps/_index.md) — подбор, установка, разработка
- [Права доступа](modules/permissions/_index.md) — роли, права, экстранет
- [Администрирование](modules/administration/_index.md) — портал, тарифы, домены, безопасность

### Разработка (коробка)
- [Ядро D7](development/core-d7/_index.md) — ORM, события, Result/Error, DI
- [Компоненты](development/components/_index.md) — компоненты, комплексные, AJAX
- [Свои модули](development/modules-custom/_index.md) — структура, установка, options
- [Шаблоны и вёрстка](development/templates-design/_index.md) — шаблоны сайта, composite
- [Производительность](development/performance/_index.md) — кэширование, индексы, масштабирование
- [Миграции](development/migrations/_index.md) — sprint.migration, обновления, перенос
- [Администрирование сервера](development/server-admin/_index.md) — окружение, бэкапы, мониторинг

### Сквозное
- [Playbooks (жизненный цикл)](cross-cutting/playbooks/_index.md) — пресейл → деплой → миграция → обучение → поддержка
- [Паттерны](cross-cutting/patterns/_index.md) — межмодульные решения
- [Антипаттерны](cross-cutting/antipatterns/_index.md) — типичные ошибки внедрения
- [Глоссарий](glossary/_index.md) — сущности и термины
- [Конспекты источников](sources/_index.md) — что встроено в вики и откуда

## С чего начать
1. Открой [index.md](../index.md) — каталог со всеми страницами по типам и статусу.
2. Новый материал → `/wiki:ingest`. Вопрос → `/wiki:query`. Проверка здоровья → `/wiki:lint`.
