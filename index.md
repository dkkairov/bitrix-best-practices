# Каталог базы знаний

Единый навигатор по вики «Лучшие практики внедрения Bitrix24» (метод LLM Wiki Карпати).
Правила и рабочие процессы — [CLAUDE.md](CLAUDE.md). Для людей — [README](README.md).

Представления: [карта тем](#карта-тем) · [по типу](#по-типу) · [по статусу](#по-статусу) ·
[по редакции](#по-редакции).

Имена страниц = `<тип>-<слаг>`; связи — `[[имя]]` (Obsidian резолвит по имени файла). Хаб папки —
`_index-<папка>.md`. Папку и хаб заводим **по факту первой страницы** — пустых не плодим.

---

## Карта тем

Ссылка ведёт на хаб раздела. Темы без ссылки пока не наполнены — заведём папку при первой странице.

### Продуктовые модули (облако и коробка)
- [CRM](wiki/modules/crm/_index-crm.md) — сделки, лиды, воронки, контакты/компании, аналитика
- [Бизнес-процессы](wiki/modules/bizproc/_index-bizproc.md) — БП, роботы, триггеры, дизайнер
- [REST и интеграции](wiki/modules/rest-integrations/_index-rest-integrations.md) — REST API, вебхуки, события, OAuth
- [Права доступа](wiki/modules/permissions/_index-permissions.md) — роли, права, экстранет
- [Администрирование](wiki/modules/administration/_index-administration.md) — портал, тарифы, домены, безопасность
- _Планируются:_ Задачи и проекты · Смарт-процессы (СПА) · Телефония · Сайты и магазины ·
  Коммуникации · Совместная работа · AI / CoPilot · HR · Приложения маркетплейса

### Разработка (коробка)
- [Ядро D7](wiki/development/core-d7/_index-core-d7.md) — ORM, события, Result/Error, DI, размещение кода
- [Свои модули](wiki/development/modules-custom/_index-modules-custom.md) — структура, установка, версии, дистрибуция
- [Производительность](wiki/development/performance/_index-performance.md) — кэширование, индексы, масштабирование
- [Миграции](wiki/development/migrations/_index-migrations.md) — sprint.migration, обновления, перенос
- [Администрирование сервера](wiki/development/server-admin/_index-server-admin.md) — окружение, git, бэкапы
- _Планируются:_ Компоненты · Шаблоны и вёрстка

### Сквозное
- [Playbooks](wiki/cross-cutting/playbooks/_index-playbooks.md) — жизненный цикл: пресейл → деплой → поддержка
- [Антипаттерны](wiki/cross-cutting/antipatterns/_index-antipatterns.md) — типичные ошибки внедрения
- [Глоссарий](wiki/glossary/_index-glossary.md) — сущности и термины
- [Конспекты источников](wiki/sources/_index-sources.md) — что встроено в вики и откуда
- _Планируются:_ Паттерны (межмодульные)

---

## По типу

### Чек-листы
- [[checklist-crm-launch|Запуск CRM «под ключ»]] · CRM · both
- [[checklist-portal-initial-setup|Первичная настройка портала]] · Администрирование · both
- [[checklist-box-performance|Производительность коробки]] · Разработка · box
- [[checklist-dev-environment-and-git|Окружение разработки и git]] · Разработка · box
- [[checklist-presale-audit|Playbook предпроектного аудита]] · Playbooks · both

### Паттерны
- [[pattern-crm-sales-funnel-design|Проектирование воронки и стадий]] · CRM · both
- [[pattern-robots-vs-bizproc-decision|Роботы/триггеры vs бизнес-процессы]] · Бизнес-процессы · both
- [[pattern-rest-webhooks-and-events|Вебхуки и события для интеграций]] · REST · both
- [[pattern-events-over-core-modification|Расширение через события]] · Разработка · box
- [[pattern-module-based-development-standard|Модульная разработка: когда и как]] · Разработка · box

### Антипаттерны
- [[antipattern-crm-stage-explosion|Взрыв стадий воронки]] · CRM · both
- [[antipattern-everything-in-one-funnel|Всё в одной воронке]] · CRM · both
- [[antipattern-box-core-modification|Правка ядра коробки]] · Разработка · box

### Рецепты
- [[recipe-rest-oauth-app-setup|OAuth-приложение и токены]] · REST · both
- [[recipe-crm-permissions|Права доступа в CRM]] · Права · both
- [[recipe-module-structure-and-install|Структура модуля и установка]] · Разработка · box
- [[recipe-module-versioning-and-private-distribution|Версии и приватная дистрибуция]] · Разработка · box
- [[recipe-migrations-as-code|Миграции как код]] · Разработка · box
- [[recipe-composer-third-party-libraries|Сторонние Composer-пакеты (dompdf, PhpWord)]] · Разработка · box

### Концепты
- [[concept-bitrix-framework-vs-bitrix24|Bitrix Framework vs Bitrix24: движок и продукт]] · Разработка · both
- [[concept-dev-standards|Стандарт разработки (коробка)]] · Разработка · box
- [[concept-code-namespaces-and-autoloading|Пространства имён и автозагрузка]] · Разработка · box
- [[concept-coding-standards|Код-стайл и безопасность]] · Разработка · box
- [[concept-testing-approach|Подход к тестированию]] · Разработка · box

### Сущности и термины (глоссарий)
- [[entity-smart-process|Смарт-процесс (СПА)]]
- [[entity-robots-triggers|Роботы и триггеры]]

### Конспекты источников
- [[sources-backlog]] — бэклог источников (очередь на ингест) · служебный
- [[source-b24-crm-deal-add|Офф. метод crm.deal.add]] · apidocs.bitrix24.ru
- [[source-bxfw-course43-namespaces|Курс 43: пространства имён]] · dev.1c-bitrix.ru · box
- [[source-bxfw-course43-modules|Курс 43: раздел о модулях]] · dev.1c-bitrix.ru · box

---

## По статусу

- **verified:** все 29 страниц проверены (`verified`: 2026-06-19 … 2026-06-21).
- **draft:** —
- **deprecated:** —

> При устаревании практики ставь `status: deprecated` и ссылку на замену; `/wiki:lint` следит за
> давностью `verified`.

---

## По редакции

- **box (специфично для коробки):** вся ветка разработки `development/*` и конспекты курса 43
  ([[source-bxfw-course43-namespaces]], [[source-bxfw-course43-modules]]).
- **cloud (специфично для облака):** — (пока нет; помечай `edition: cloud` по мере добавления).
- **both:** остальные страницы.
