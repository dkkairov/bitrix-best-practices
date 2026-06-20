# Каталог базы знаний

Навигатор по страницам вики. Карта разделов и хабы — [wiki/overview.md](wiki/overview.md).
Правила и рабочие процессы — [CLAUDE.md](CLAUDE.md). Схема нумерации (ID) — [CLAUDE.md §4.5](CLAUDE.md).

Представления: [по типу](#по-типу) · [по ID](#по-id) · [реестр разделов](#реестр-разделов) ·
[по разделам](#по-разделам) · [по статусу](#по-статусу) · [по редакции](#по-редакции).

---

## По типу

### Чек-листы
- **M01-CL01** [[m01-cl01-crm-launch-checklist|Запуск CRM «под ключ»]] · CRM · both
- **M14-CL01** [[m14-cl01-portal-initial-setup-checklist|Первичная настройка портала]] · Администрирование · both
- **D05-CL01** [[d05-cl01-box-performance-checklist|Производительность коробки]] · Разработка · box
- **D07-CL01** [[d07-cl01-dev-environment-and-git|Окружение разработки и git]] · Разработка · box
- **X01-CL01** [[x01-cl01-presale-audit|Playbook предпроектного аудита]] · Playbooks · both

### Паттерны
- **M01-PT01** [[m01-pt01-crm-sales-funnel-design-pattern|Проектирование воронки и стадий]] · CRM · both
- **M03-PT01** [[m03-pt01-robots-vs-bizproc-decision|Роботы/триггеры vs бизнес-процессы]] · Бизнес-процессы · both
- **M11-PT01** [[m11-pt01-rest-webhooks-and-events-pattern|Вебхуки и события для интеграций]] · REST · both
- **D01-PT01** [[d01-pt01-events-over-core-modification|Расширение через события]] · Разработка · box
- **D03-PT01** [[d03-pt01-module-based-development-standard|Модульная разработка: когда и как]] · Разработка · box

### Антипаттерны
- **X03-AP01** [[x03-ap01-crm-stage-explosion|Взрыв стадий воронки]] · CRM · both
- **X03-AP02** [[x03-ap02-everything-in-one-funnel|Всё в одной воронке]] · CRM · both
- **D01-AP01** [[d01-ap01-box-core-modification|Правка ядра коробки]] · Разработка · box

### Рецепты
- **M11-RC01** [[m11-rc01-rest-oauth-app-setup|OAuth-приложение и токены]] · REST · both
- **M13-RC01** [[m13-rc01-crm-permissions-recipe|Права доступа в CRM]] · Права · both
- **D03-RC01** [[d03-rc01-module-structure-and-install|Структура модуля и установка]] · Разработка · box
- **D03-RC02** [[d03-rc02-module-versioning-and-private-distribution|Версии и приватная дистрибуция]] · Разработка · box
- **D06-RC01** [[d06-rc01-migrations-as-code|Миграции как код]] · Разработка · box

### Концепты
- **D01-CN01** [[d01-cn01-code-namespaces-and-autoloading|Пространства имён и автозагрузка]] · Разработка · box
- **D01-CN02** [[d01-cn02-dev-standards|Стандарт разработки (коробка)]] · Разработка · box
- **D01-CN03** [[d01-cn03-coding-standards|Код-стайл и безопасность]] · Разработка · box
- **D01-CN04** [[d01-cn04-testing-approach|Подход к тестированию]] · Разработка · box

### Сущности и термины (глоссарий)
- **G-EN01** [[g-en01-smart-process|Смарт-процесс (СПА)]]
- **G-EN02** [[g-en02-robots-triggers|Роботы и триггеры]]

### Конспекты источников
- [[sources-backlog]] — бэклог источников (очередь на ингест) · служебный, без ID
- **S-SS01** [[s-ss01-src-b24-crm-deal-add|Офф. метод crm.deal.add]] · apidocs.bitrix24.ru
- **S-SS02** [[s-ss02-src-bxfw-course43-namespaces|Курс 43: пространства имён]] · dev.1c-bitrix.ru
- **S-SS03** [[s-ss03-src-bxfw-course43-modules|Курс 43: раздел о модулях]] · dev.1c-bitrix.ru

---

## По ID

Реестр всех решений, отсортирован по идентификатору. Формат ID — `<РАЗДЕЛ>-<ТИП><NN>`
(см. [CLAUDE.md §4.5](CLAUDE.md)); номера монотонны в пределах пары (раздел × тип), не переиспользуются.

| ID | Страница | Тип | Edition |
|----|----------|-----|---------|
| M01-CL01 | [[m01-cl01-crm-launch-checklist|Запуск CRM «под ключ»]] | checklist | both |
| M01-PT01 | [[m01-pt01-crm-sales-funnel-design-pattern|Проектирование воронки]] | pattern | both |
| M03-PT01 | [[m03-pt01-robots-vs-bizproc-decision|Роботы vs бизнес-процессы]] | pattern | both |
| M11-PT01 | [[m11-pt01-rest-webhooks-and-events-pattern|Вебхуки и события]] | pattern | both |
| M11-RC01 | [[m11-rc01-rest-oauth-app-setup|OAuth-приложение и токены]] | recipe | both |
| M13-RC01 | [[m13-rc01-crm-permissions-recipe|Права доступа в CRM]] | recipe | both |
| M14-CL01 | [[m14-cl01-portal-initial-setup-checklist|Первичная настройка портала]] | checklist | both |
| D01-CN01 | [[d01-cn01-code-namespaces-and-autoloading|Пространства имён и автозагрузка]] | concept | box |
| D01-CN02 | [[d01-cn02-dev-standards|Стандарт разработки (коробка)]] | concept | box |
| D01-CN03 | [[d01-cn03-coding-standards|Код-стайл и безопасность]] | concept | box |
| D01-CN04 | [[d01-cn04-testing-approach|Подход к тестированию]] | concept | box |
| D01-PT01 | [[d01-pt01-events-over-core-modification|Расширение через события]] | pattern | box |
| D01-AP01 | [[d01-ap01-box-core-modification|Правка ядра коробки]] | antipattern | box |
| D03-PT01 | [[d03-pt01-module-based-development-standard|Модульная разработка]] | pattern | box |
| D03-RC01 | [[d03-rc01-module-structure-and-install|Структура модуля и установка]] | recipe | box |
| D03-RC02 | [[d03-rc02-module-versioning-and-private-distribution|Версии и приватная дистрибуция]] | recipe | box |
| D05-CL01 | [[d05-cl01-box-performance-checklist|Производительность коробки]] | checklist | box |
| D06-RC01 | [[d06-rc01-migrations-as-code|Миграции как код]] | recipe | box |
| D07-CL01 | [[d07-cl01-dev-environment-and-git|Окружение разработки и git]] | checklist | box |
| X01-CL01 | [[x01-cl01-presale-audit|Предпроектный аудит]] | checklist | both |
| X03-AP01 | [[x03-ap01-crm-stage-explosion|Взрыв стадий воронки]] | antipattern | both |
| X03-AP02 | [[x03-ap02-everything-in-one-funnel|Всё в одной воронке]] | antipattern | both |
| G-EN01 | [[g-en01-smart-process|Смарт-процесс (СПА)]] | entity | both |
| G-EN02 | [[g-en02-robots-triggers|Роботы и триггеры]] | entity | both |
| S-SS01 | [[s-ss01-src-b24-crm-deal-add|Конспект crm.deal.add]] | source-summary | both |
| S-SS02 | [[s-ss02-src-bxfw-course43-namespaces|Конспект: курс 43, пространства имён]] | source-summary | box |
| S-SS03 | [[s-ss03-src-bxfw-course43-modules|Конспект: курс 43, модули]] | source-summary | box |

---

## Реестр разделов

Префиксы разделов и привязка к папкам (полная схема — [CLAUDE.md §4.5](CLAUDE.md)).
`G` и `S` — единственные в своём роде разделы, потому без номера.

| Код | Раздел | Папка | Решений |
|-----|--------|-------|---------|
| M01 | CRM | `modules/crm/` | 2 |
| M02 | Задачи и проекты | `modules/tasks-projects/` | 0 |
| M03 | Бизнес-процессы | `modules/bizproc/` | 1 |
| M04 | Смарт-процессы | `modules/smart-process/` | 0 |
| M05 | Телефония | `modules/telephony/` | 0 |
| M06 | Сайты и магазины | `modules/sites-stores/` | 0 |
| M07 | Коммуникации | `modules/communications/` | 0 |
| M08 | Совместная работа | `modules/collaboration/` | 0 |
| M09 | AI / CoPilot | `modules/ai-copilot/` | 0 |
| M10 | HR | `modules/hr/` | 0 |
| M11 | REST и интеграции | `modules/rest-integrations/` | 2 |
| M12 | Маркетплейс | `modules/marketplace-apps/` | 0 |
| M13 | Права доступа | `modules/permissions/` | 1 |
| M14 | Администрирование | `modules/administration/` | 1 |
| D01 | Ядро D7 | `development/core-d7/` | 6 |
| D02 | Компоненты | `development/components/` | 0 |
| D03 | Свои модули | `development/modules-custom/` | 3 |
| D04 | Шаблоны и вёрстка | `development/templates-design/` | 0 |
| D05 | Производительность | `development/performance/` | 1 |
| D06 | Миграции | `development/migrations/` | 1 |
| D07 | Сервер | `development/server-admin/` | 1 |
| X01 | Playbooks | `cross-cutting/playbooks/` | 1 |
| X02 | Паттерны (межмодульные) | `cross-cutting/patterns/` | 0 |
| X03 | Антипаттерны | `cross-cutting/antipatterns/` | 2 |
| G | Глоссарий | `glossary/` | 2 |
| S | Источники | `sources/` | 3 |

Итого нумерованных решений: **27** (хабы `_index.md` и `sources-backlog` — без ID).

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

- **verified:** все 27 текущих решений проверены (`verified`: 2026-06-19 / 2026-06-20).
- **draft:** —
- **deprecated:** —

> При устаревании практики ставь `status: deprecated` и ссылку на замену; `/wiki:lint` следит за
> давностью `verified`.

---

## По редакции

- **box (специфично для коробки):** вся ветка разработки `development/*` (разделы D01–D07) и конспекты курса 43 (S-SS02, S-SS03). Полный список с редакцией — таблица [По ID](#по-id).
- **cloud (специфично для облака):** — (пока нет; помечай `edition: cloud` по мере добавления)
- **both:** остальные страницы.
