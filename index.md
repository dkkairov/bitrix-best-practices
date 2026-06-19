# Каталог базы знаний

Навигатор по страницам вики. Карта разделов и хабы — [wiki/overview.md](wiki/overview.md).
Правила и рабочие процессы — [CLAUDE.md](CLAUDE.md).

Представления: [по типу](#по-типу) · [по разделам](#по-разделам) · [по статусу](#по-статусу) ·
[по редакции](#по-редакции).

---

## По типу

### Чек-листы
- [[crm-launch-checklist]] — запуск CRM «под ключ» · CRM · both
- [[portal-initial-setup-checklist]] — первичная настройка портала · Администрирование · both
- [[box-performance-checklist]] — производительность коробки · Разработка · box
- [[presale-audit]] — playbook предпроектного аудита · Playbooks · both

### Паттерны
- [[crm-sales-funnel-design-pattern]] — проектирование воронки и стадий · CRM · both
- [[robots-vs-bizproc-decision]] — роботы/триггеры vs бизнес-процессы · Бизнес-процессы · both
- [[rest-webhooks-and-events-pattern]] — вебхуки и события для интеграций · REST · both

### Антипаттерны
- [[crm-stage-explosion]] — взрыв стадий воронки · CRM · both
- [[everything-in-one-funnel]] — всё в одной воронке · CRM · both
- [[box-core-modification]] — правка ядра коробки · Разработка · box

### Рецепты
- [[rest-oauth-app-setup]] — OAuth-приложение и токены · REST · both
- [[crm-permissions-recipe]] — права доступа в CRM · Права · both

### Концепты
- [[code-namespaces-and-autoloading]] — пространства имён и автозагрузка · Разработка · box

### Сущности и термины (глоссарий)
- [[smart-process]] — смарт-процесс (СПА)
- [[robots-triggers]] — роботы и триггеры

### Конспекты источников
- [[sources-backlog]] — бэклог источников (очередь на ингест)
- [[src-b24-crm-deal-add]] — офф. метод `crm.deal.add` (apidocs.bitrix24.ru)

---

## По разделам

- **Продуктовые модули:** [CRM](wiki/modules/crm/_index.md) ·
  [Задачи](wiki/modules/tasks-projects/_index.md) ·
  [Бизнес-процессы](wiki/modules/bizproc/_index.md) ·
  [СПА](wiki/modules/smart-process/_index.md) ·
  [Телефония](wiki/modules/telephony/_index.md) ·
  [Сайты и магазины](wiki/modules/sites-stores/_index.md) ·
  [Коммуникации](wiki/modules/communications/_index.md) ·
  [Совместная работа](wiki/modules/collaboration/_index.md) ·
  [AI/CoPilot](wiki/modules/ai-copilot/_index.md) · [HR](wiki/modules/hr/_index.md) ·
  [REST и интеграции](wiki/modules/rest-integrations/_index.md) ·
  [Маркетплейс](wiki/modules/marketplace-apps/_index.md) ·
  [Права](wiki/modules/permissions/_index.md) ·
  [Администрирование](wiki/modules/administration/_index.md)
- **Разработка (коробка):** [Ядро D7](wiki/development/core-d7/_index.md) ·
  [Компоненты](wiki/development/components/_index.md) ·
  [Свои модули](wiki/development/modules-custom/_index.md) ·
  [Шаблоны](wiki/development/templates-design/_index.md) ·
  [Производительность](wiki/development/performance/_index.md) ·
  [Миграции](wiki/development/migrations/_index.md) ·
  [Сервер](wiki/development/server-admin/_index.md)
- **Сквозное:** [Playbooks](wiki/cross-cutting/playbooks/_index.md) ·
  [Паттерны](wiki/cross-cutting/patterns/_index.md) ·
  [Антипаттерны](wiki/cross-cutting/antipatterns/_index.md) ·
  [Глоссарий](wiki/glossary/_index.md) · [Источники](wiki/sources/_index.md)

---

## По статусу

- **verified:** все 15 текущих seed-страниц проверены (`verified: 2026-06-19`).
- **draft:** —
- **deprecated:** —

> При устаревании практики ставь `status: deprecated` и ссылку на замену; `/wiki:lint` следит за
> давностью `verified`.

---

## По редакции

- **box (специфично для коробки):** [[box-performance-checklist]], [[box-core-modification]], [[code-namespaces-and-autoloading]]
- **cloud (специфично для облака):** — (пока нет; помечай `edition: cloud` по мере добавления)
- **both:** остальные страницы.
