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
- [Бизнес-процессы](wiki/modules/bizproc/_index-bizproc.md) — БП, роботы, триггеры, дизайнер, формат `.bpt`, генерация агентом
- [REST и интеграции](wiki/modules/rest-integrations/_index-rest-integrations.md) — REST API, вебхуки, события, OAuth
- [Права доступа](wiki/modules/permissions/_index-permissions.md) — роли, права, экстранет
- [Администрирование](wiki/modules/administration/_index-administration.md) — портал, тарифы, домены, безопасность
- [Смарт-процессы (СПА)](wiki/modules/smart-process/_index-smart-process.md) — типы, поля, стадии, карточка элемента
- [Задачи и проекты](wiki/modules/tasks-projects/_index-tasks-projects.md) — задачи, проекты, эффективность
- [Коммуникации](wiki/modules/communications/_index-communications.md) — живая лента, уведомления
- _Планируются:_ Телефония · Сайты и магазины · Совместная работа · AI / CoPilot · HR ·
  Приложения маркетплейса

### Разработка (коробка)
- [Ядро D7](wiki/development/core-d7/_index-core-d7.md) — ORM, события, Result/Error, DI, размещение кода
- [Свои модули](wiki/development/modules-custom/_index-modules-custom.md) — структура, установка, версии, дистрибуция
- [Производительность](wiki/development/performance/_index-performance.md) — кэширование, индексы, масштабирование
- [Миграции](wiki/development/migrations/_index-migrations.md) — sprint.migration, обновления, перенос
- [Администрирование сервера](wiki/development/server-admin/_index-server-admin.md) — окружение, git, бэкапы
- [Шаблоны и вёрстка](wiki/development/templates-design/_index-templates-design.md) — UI-подсистема, тулбар, фильтр, грид
- _Планируются:_ Компоненты

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
- [[checklist-windows-to-linux-deploy|Заливка с Windows на Linux-сервер: 6 граблей]] · Разработка · box
- [[checklist-presale-audit|Playbook предпроектного аудита]] · Playbooks · both
- [[checklist-requirements-workshop|Playbook рабочей встречи по требованиям]] · Playbooks · both

### Паттерны
- [[pattern-crm-sales-funnel-design|Проектирование воронки и стадий]] · CRM · both
- [[pattern-robots-vs-bizproc-decision|Роботы/триггеры vs бизнес-процессы]] · Бизнес-процессы · both
- [[pattern-bizproc-ai-assisted-generation|AI-генерация БП: агент проектирует, код собирает]] · Бизнес-процессы · both · черновик
- [[pattern-rest-webhooks-and-events|Вебхуки и события для интеграций]] · REST · both
- [[pattern-events-over-core-modification|Расширение через события]] · Разработка · box
- [[pattern-module-based-development-standard|Модульная разработка: когда и как]] · Разработка · box
- [[pattern-module-library-monorepo|Библиотека модулей агентства (монорепо)]] · Разработка · box
- [[pattern-module-self-disabling-guard|Сторож модуля: портал важнее модуля]] · Разработка · box
- [[pattern-agents-vs-cron|Агенты или cron: выбор фонового запуска]] · Разработка · box
- [[pattern-crm-action-vs-event|Operation\Action или обработчик события]] · CRM · box
- [[pattern-crm-timeline-client-side|Таймлайн CRM на клиенте: догрузка и фильтрация]] · CRM · box
- [[pattern-tasks-effectiveness-from-db|Эффективность задач 1:1 с виджетом]] · Задачи · box

### Антипаттерны
- [[antipattern-crm-stage-explosion|Взрыв стадий воронки]] · CRM · both
- [[antipattern-everything-in-one-funnel|Всё в одной воронке]] · CRM · both
- [[antipattern-box-core-modification|Правка ядра коробки]] · Разработка · box
- [[antipattern-bizproc-hardcoded-portal-ids|Зашитые ID портала в шаблонах БП]] · Бизнес-процессы · both
- [[antipattern-ajax-controller-lowercase-name|Строчное имя контроллера в AJAX-действии]] · Разработка · box
- [[antipattern-cli-php-as-root|Консольный PHP от root портит кэш портала]] · Разработка · box

### Рецепты
- [[recipe-rest-oauth-app-setup|OAuth-приложение и токены]] · REST · both
- [[recipe-crm-permissions|Права доступа в CRM]] · Права · both
- [[recipe-module-structure-and-install|Структура модуля и установка]] · Разработка · box
- [[recipe-module-versioning-and-private-distribution|Версии и приватная дистрибуция]] · Разработка · box
- [[recipe-migrations-as-code|Миграции как код]] · Разработка · box
- [[recipe-composer-third-party-libraries|Сторонние Composer-пакеты (dompdf, PhpWord)]] · Разработка · box
- [[recipe-d7-orm-event-subscription|Подписка модуля на событие D7 ORM]] · Разработка · box
- [[recipe-safe-module-deploy|Безопасная заливка модуля: guard, линт, откат]] · Разработка · box
- [[recipe-git-deploy-to-production|Доставка правки на прод через git]] · Разработка · box
- [[recipe-mysql-connection-refused|MySQL (2002) Connection refused]] · Разработка · box
- [[recipe-crm-history-all-fields|История смарт-процесса: все поля + источник]] · CRM · box
- [[recipe-crm-card-editor-js-access|Карточка CRM из JS: редактор и модель]] · CRM · box
- [[recipe-crm-hide-card-block-js|Скрыть блок в карточке смарт-процесса]] · CRM · box
- [[recipe-smart-process-programmatic-creation|Создать смарт-процесс и поля из инсталлятора]] · СПА · box
- [[recipe-bizproc-custom-task-activity|Своё действие БП с заданием (CBPTaskService)]] · БП · box
- [[recipe-custom-left-menu-section|Свой раздел в левом меню (CustomSection)]] · Администрирование · box
- [[recipe-post-to-livefeed|Пост в живую ленту из PHP]] · Коммуникации · box

### Концепты
- [[concept-bitrix-framework-vs-bitrix24|Bitrix Framework vs Bitrix24: движок и продукт]] · Разработка · both
- [[concept-dev-standards|Стандарт разработки (коробка)]] · Разработка · box
- [[concept-change-invasiveness-hierarchy|Иерархия способов изменения]] · Разработка · box
- [[concept-platform-reverse-engineering|Исследование платформы: 4 приёма]] · Разработка · box
- [[concept-bitrix-naming-conventions|Соглашения именования сущностей]] · Разработка · box
- [[concept-code-namespaces-and-autoloading|Пространства имён и автозагрузка]] · Разработка · box
- [[concept-coding-standards|Код-стайл и безопасность]] · Разработка · box
- [[concept-testing-approach|Подход к тестированию]] · Разработка · box
- [[concept-bizproc-bpt-format|Формат шаблона БП (.bpt)]] · Бизнес-процессы · both
- [[concept-crm-universal-api|Universal API CRM: Container → Factory → Item]] · CRM · box
- [[concept-crm-dictionaries|Справочники CRM: новое читает, старое пишет]] · CRM · box
- [[concept-request-lifecycle|Жизненный цикл HTTP-запроса]] · Разработка · box
- [[concept-orm-datamanager-events|События ORM DataManager]] · Разработка · box
- [[concept-service-locator|ServiceLocator: регистрация и подмена сервисов]] · Разработка · box
- [[concept-validation-d7|Валидация D7: PHP-атрибуты]] · Разработка · box
- [[concept-ui-subsystem|UI-подсистема: тулбар, фильтр, грид, кнопки]] · Разработка · box
- [[concept-bizproc-engine|Устройство движка БП: шаблон, инстанс, активити]] · БП · box
- [[concept-org-structure|Оргструктура портала]] · Администрирование · both

### Сущности: термины внедрения (глоссарий)
- [[entity-smart-process|Смарт-процесс (СПА)]]
- [[entity-robots-triggers|Роботы и триггеры]]
- [[entity-bizproc-template-rest-methods|REST-методы шаблонов БП]] · cloud

### Сущности: классы API коробки

Справочник по классам ядра — лежат рядом с практиками, в папке своей области.

**CRM** — [[entity-crm-container|Container]] · [[entity-crm-factory|Factory]] ·
[[entity-crm-item|Item]] · [[entity-crm-operation|Operation + Action]] ·
[[entity-crm-settings|<Type>Settings]] · [[entity-ccrm-status|CCrmStatus / StatusTable]] ·
[[entity-ccrm-owner-type|CCrmOwnerType]] · [[entity-ccrm-field-multi|CCrmFieldMulti]]

**Бизнес-процессы** — [[entity-cbp-activity|CBPActivity / BaseActivity]] ·
[[entity-cbp-activity-condition|CBPActivityCondition]] · [[entity-cbp-task-service|CBPTaskService]] ·
[[entity-bizproc-field-type|FieldType]] · [[entity-bizproc-globals-manager|GlobalsManager]]

**Интранет** — [[entity-cintranet-utils|CIntranetUtils]] · [[entity-user-absence|UserAbsence]]

**Ядро** — [[entity-loader|Loader]] · [[entity-module-manager|ModuleManager]] ·
[[entity-config-option|Config\Option]] · [[entity-event-manager|EventManager]] ·
[[entity-main-event|Event / EventResult]] · [[entity-main-result|Result / Error]] ·
[[entity-cagent|CAgent]] · [[entity-validation-service|ValidationService]] ·
[[entity-validation-result|ValidationResult / ValidationError]] ·
[[entity-local-directory|/local/]] · [[entity-php-interface|php_interface]] ·
[[entity-urlrewrite|urlrewrite.php]] · [[entity-admin-php-console|Командная PHP-строка]]

**UI и шаблоны** — [[entity-toolbar|UI\Toolbar]] · [[entity-ui-button|Button]] ·
[[entity-filter-component|main.ui.filter]] · [[entity-filter-field-adapter|FieldAdapter]] ·
[[entity-filter-options|Filter\Options]] · [[entity-custom-filter|Main\Filter]] ·
[[entity-bx-main-filter|BX.Main.Filter]] · [[entity-grid-component|main.ui.grid]] ·
[[entity-grid-options|Grid\Options]] · [[entity-bx-main-grid|BX.Main.gridManager]] ·
[[entity-site-template|Шаблон дизайна]] · [[entity-theme-picker|ThemePicker]]

### Конспекты источников
- [[sources-backlog]] — бэклог источников (очередь на ингест) · служебный
- [[source-b24-crm-deal-add|Офф. метод crm.deal.add]] · apidocs.bitrix24.ru
- [[source-bxfw-course43-namespaces|Курс 43: пространства имён]] · dev.1c-bitrix.ru · box
- [[source-bxfw-course43-modules|Курс 43: раздел о модулях]] · dev.1c-bitrix.ru · box

---

## По статусу

- **verified:** 102 страницы из 106 (`verified`: 2026-06-01 … 2026-09-18).
- **draft:** 4 страницы —
  [[pattern-bizproc-ai-assisted-generation]] (схема не подтверждена пилотом);
  [[entity-loader]], [[entity-module-manager]], [[entity-config-option]] — карточки заведены
  сверх перенесённого материала по употреблению в наших страницах; первоисточник не сверялся
  (`apidocs.bitrix24.ru` покрывает REST и облако, не PHP-ядро коробки).
- **deprecated:** —

> При устаревании практики ставь `status: deprecated` и ссылку на замену; `/wiki:lint` следит за
> давностью `verified`.

---

## По редакции

- **box (86 страниц):** вся ветка разработки `development/*`, коробочная часть модулей и
  справочник по 43 классам и компонентам ядра.
- **cloud (1 страница):** [[entity-bizproc-template-rest-methods]] (сверено по документации облака).
- **both (19 страниц):** практики внедрения, не зависящие от редакции.

> Перекос в сторону `box` — следствие переноса архивной вики разработки (2026-09-18) и
> последующего справочника классов. Облачная половина (no-code, REST, маркетплейс) остаётся
> главной зоной роста: сейчас на неё приходится одна страница из 106.
