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
- [Бизнес-процессы](wiki/modules/bizproc/_index-bizproc.md) — БП, роботы, триггеры, дизайнер, формат `.bpt`, типовые процессы, свои действия, генерация агентом
- [REST и интеграции](wiki/modules/rest-integrations/_index-rest-integrations.md) — REST API, вебхуки, события, OAuth
- [Права доступа](wiki/modules/permissions/_index-permissions.md) — роли, права, экстранет
- [Администрирование](wiki/modules/administration/_index-administration.md) — портал, тарифы, домены, безопасность
- [Смарт-процессы (СПА)](wiki/modules/smart-process/_index-smart-process.md) — типы, поля, стадии, карточка элемента
- [Задачи и проекты](wiki/modules/tasks-projects/_index-tasks-projects.md) — задачи, проекты, эффективность, API задач V2
- [Коммуникации](wiki/modules/communications/_index-communications.md) — живая лента, уведомления
- _Планируются:_ Телефония · Сайты и магазины · Совместная работа · AI / CoPilot · HR ·
  Приложения маркетплейса

### Разработка (коробка)
- [Ядро D7](wiki/development/core-d7/_index-core-d7.md) — ORM, события, Result/Error, DI, размещение кода, структура решения в `/local`
- [Свои модули](wiki/development/modules-custom/_index-modules-custom.md) — структура, установка, версии, дистрибуция
- [Производительность](wiki/development/performance/_index-performance.md) — кэширование, индексы, масштабирование
- [Миграции](wiki/development/migrations/_index-migrations.md) — sprint.migration, обновления, перенос
- [Администрирование сервера](wiki/development/server-admin/_index-server-admin.md) — окружение, git, бэкапы
- [Шаблоны и вёрстка](wiki/development/templates-design/_index-templates-design.md) — UI-подсистема, тулбар, фильтр, грид
- [Компоненты](wiki/development/components/_index-components.md) — структура, кэш, шаблоны, AJAX

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
- [[checklist-data-migration|Playbook миграции данных]] · Playbooks · both · черновик
- [[checklist-golive-deployment|Playbook запуска портала в работу]] · Playbooks · both · черновик
- [[checklist-user-adoption|Playbook обучения и адаптации]] · Playbooks · both · черновик
- [[checklist-support-handover|Playbook передачи в поддержку]] · Playbooks · both · черновик
- [[checklist-permissions-audit|Аудит прав доступа]] · Права · both · черновик
- [[checklist-tasks-regulations|Регламент постановки задач]] · Задачи · both · черновик
- [[checklist-bizproc-template-review|Ревью шаблона БП: ошибки проектирования]] · БП · both
- [[checklist-box-security|Безопасность коробки перед сдачей]] · Разработка · box

### Паттерны
- [[pattern-crm-sales-funnel-design|Проектирование воронки и стадий]] · CRM · both
- [[pattern-robots-vs-bizproc-decision|Роботы/триггеры vs бизнес-процессы]] · Бизнес-процессы · both
- [[pattern-bizproc-roles-from-project-card|Роли процесса — из карточки проекта, а не из шаблона]] · Бизнес-процессы · both · проверено на коробке
- [[pattern-bizproc-ai-assisted-generation|AI-генерация БП: агент проектирует, код собирает]] · Бизнес-процессы · both · черновик
- [[pattern-rest-webhooks-and-events|Вебхуки и события для интеграций]] · REST · both
- [[pattern-events-over-core-modification|Расширение через события]] · Разработка · box
- [[pattern-module-based-development-standard|Модульная разработка: когда и как]] · Разработка · box
- [[pattern-local-solution-structure|Решение в /local/php_interface: структура проекта]] · Разработка · box
- [[pattern-module-library-monorepo|Библиотека модулей агентства (монорепо)]] · Разработка · box
- [[pattern-module-self-disabling-guard|Сторож модуля: портал важнее модуля]] · Разработка · box
- [[pattern-agents-vs-cron|Агенты или cron: выбор фонового запуска]] · Разработка · box
- [[pattern-stepper-long-operations|Длинная операция шагами: Stepper]] · Разработка · box · проверено
- [[pattern-crm-action-vs-event|Operation\Action или обработчик события]] · CRM · box
- [[pattern-crm-timeline-client-side|Таймлайн CRM на клиенте: догрузка и фильтрация]] · CRM · box
- [[pattern-tasks-effectiveness-from-db|Эффективность задач 1:1 с виджетом]] · Задачи · box
- [[pattern-rest-batch-and-limits|Батчи и лимиты REST]] · REST · cloud
- [[pattern-rest-reliable-delivery|Надёжная доставка событий]] · REST · cloud
- [[pattern-smart-process-vs-deal-fields|Смарт-процесс или поля в сделке]] · СПА · both · черновик

### Антипаттерны
- [[antipattern-crm-stage-explosion|Взрыв стадий воронки]] · CRM · both
- [[antipattern-everything-in-one-funnel|Всё в одной воронке]] · CRM · both
- [[antipattern-box-core-modification|Правка ядра коробки]] · Разработка · box
- [[antipattern-bizproc-hardcoded-portal-ids|Зашитые ID портала в шаблонах БП]] · Бизнес-процессы · both
- [[antipattern-bizproc-php-code-activity|Действие «PHP код» в шаблонах БП]] · Бизнес-процессы · box
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
- [[recipe-d7-orm-crud|Запись через ORM: add, update, delete и проверки]] · Разработка · box
- [[recipe-d7-transactions|Транзакции D7: начать, зафиксировать, откатить]] · Разработка · box · проверено
- [[recipe-http-client|HTTP-запрос из коробки: HttpClient]] · Разработка · box · проверено
- [[recipe-engine-controller-action|Контроллер и действие: Engine\Controller]] · Разработка · box · проверено
- [[recipe-box-debugging|Отладка коробки: дампы, замеры, панель]] · Разработка · box · проверено
- [[recipe-image-processing|Обработка изображений: Main\File\Image]] · Разработка · box · проверено
- [[recipe-box-backup|Резервная копия коробки: снять и восстановить]] · Разработка · box
- [[recipe-console-commands|Консольные команды ядра: bitrix.php и свои команды]] · Разработка · box · проверено
- [[recipe-encrypt-sensitive-data|Шифровать чувствительные данные: Cipher, CryptoField]] · Разработка · box · проверено
- [[recipe-cli-script-bootstrap|Консольный и cron-скрипт: подключение ядра]] · Разработка · box · проверено
- [[recipe-d7-custom-validation-rule|Свой валидатор и правило валидации D7]] · Разработка · box · проверено
- [[recipe-custom-list-page-filter-grid|Своя страница-список: фильтр, грид, тулбар]] · Разработка · box · проверено
- [[recipe-safe-module-deploy|Безопасная заливка модуля: guard, линт, откат]] · Разработка · box
- [[recipe-git-deploy-to-production|Доставка правки на прод через git]] · Разработка · box
- [[recipe-mysql-connection-refused|MySQL (2002) Connection refused]] · Разработка · box
- [[recipe-box-test-stand-docker|Тестовый стенд коробки в Docker]] · Разработка · box
- [[recipe-crm-history-all-fields|История смарт-процесса: все поля + источник]] · CRM · box
- [[recipe-crm-card-editor-js-access|Карточка CRM из JS: редактор и модель]] · CRM · box
- [[recipe-crm-hide-card-block-js|Скрыть блок в карточке смарт-процесса]] · CRM · box
- [[recipe-crm-legacy-entity-crud|Лид, контакт, компания, сделка через CCrm*]] · CRM · box · проверено
- [[recipe-crm-lead-conversion|Конвертация лида из кода]] · CRM · box · проверено
- [[recipe-crm-todo-activity|Универсальное дело (ToDo) из кода]] · CRM · box · проверено
- [[recipe-smart-process-programmatic-creation|Создать смарт-процесс и поля из инсталлятора]] · СПА · box
- [[recipe-smart-process-factory-customization|Своя фабрика смарт-процесса]] · СПА · box · проверено
- [[recipe-bizproc-approval-route|Маршрут согласования: срок, доработка, итог]] · БП · both
- [[recipe-bizproc-request-intake|Заявка: исполнитель, задача со сроком, контроль]] · БП · both
- [[recipe-bizproc-debugging|Отладка БП: журнал, зависшие процессы]] · БП · both
- [[recipe-bizproc-custom-activity-baseactivity|Своё действие БП на BaseActivity]] · БП · box
- [[recipe-bizproc-custom-task-activity|Своё действие БП с заданием (CBPTaskService)]] · БП · box
- [[recipe-tasks-v2-commands|Команды задач V2: операции и ловушки]] · Задачи · box · проверено
- [[recipe-custom-left-menu-section|Свой раздел в левом меню (CustomSection)]] · Администрирование · box
- [[recipe-intranet-absence-import|Запись отсутствий из кода (импорт отпусков)]] · Администрирование · box · проверено
- [[recipe-post-to-livefeed|Пост в живую ленту из PHP]] · Коммуникации · box
- [[recipe-cache-with-tags|Кэш выборки с тегами и инвалидацией]] · Производительность · box · проверено
- [[recipe-slow-query-diagnostics|Найти и вылечить медленные запросы]] · Производительность · box · проверено

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
- [[concept-bizproc-activity-catalog|Каталог действий БП (25 типов)]] · Бизнес-процессы · both
- [[concept-bizproc-expressions|Выражения БП: функции, модификаторы, коды]] · Бизнес-процессы · both
- [[concept-bizproc-state-machine|БП со статусами: устройство и выбор]] · Бизнес-процессы · both
- [[concept-crm-universal-api|Universal API CRM: Container → Factory → Item]] · CRM · box
- [[concept-crm-dictionaries|Справочники CRM: новое читает, старое пишет]] · CRM · box
- [[concept-request-lifecycle|Жизненный цикл HTTP-запроса]] · Разработка · box
- [[concept-deferred-functions-and-page-areas|Отложенные функции и зоны страницы]] · Разработка · box · проверено
- [[concept-d7-orm-entity|Сущность ORM: Table-класс и описание полей]] · Разработка · box
- [[concept-d7-orm-query|Выборка ORM: getList, Query и два формата фильтра]] · Разработка · box
- [[concept-d7-orm-objects|Объекты и коллекции ORM: EO_-классы вместо массивов]] · Разработка · box
- [[concept-d7-orm-relations|Связи ORM: Reference, OneToMany, ManyToMany]] · Разработка · box · проверено
- [[concept-orm-datamanager-events|События ORM DataManager]] · Разработка · box
- [[concept-d7-sql-layer|Прямой SQL: соединение, SqlHelper, SqlExpression]] · Разработка · box · проверено
- [[concept-postgresql-compatibility|Код, совместимый с PostgreSQL]] · Разработка · box
- [[concept-routing|Маршрутизация: свои адреса вместо urlrewrite]] · Разработка · box · проверено
- [[concept-messenger-queues|Очереди сообщений ядра (альфа)]] · Разработка · box · проверено
- [[concept-d7-session-storage|Сессия D7, LocalSession и временное хранилище]] · Разработка · box · проверено
- [[concept-js-extensions|Расширения JS и CSS: свой bundle]] · Разработка · box · проверено
- [[concept-component-structure|Компонент 2.0: структура, кэш, AJAX]] · Разработка · box · проверено
- [[concept-web-vulnerabilities-bitrix|XSS, CSRF, SSRF, инъекции: чем закрывает ядро]] · Разработка · box · проверено
- [[concept-proactive-security|Проактивная защита: уровни, 2FA, побочные эффекты]] · Администрирование · box
- [[concept-d7-logging|Логирование D7: логгеры, уровни, настройка]] · Разработка · box · проверено
- [[concept-datetime-and-timezones|Дата и время: Date, DateTime, часовые пояса]] · Разработка · box · проверено
- [[concept-localization-lang-files|Локализация: Loc и языковые файлы]] · Разработка · box · проверено
- [[concept-multisite|Многосайтовость: один домен и разные домены]] · Разработка · box
- [[concept-service-locator|ServiceLocator: регистрация и подмена сервисов]] · Разработка · box
- [[concept-validation-d7|Валидация D7: PHP-атрибуты]] · Разработка · box
- [[concept-ui-subsystem|UI-подсистема: тулбар, фильтр, грид, кнопки]] · Разработка · box
- [[concept-bizproc-engine|Устройство движка БП: шаблон, инстанс, активити]] · БП · box
- [[concept-org-structure|Оргструктура портала]] · Администрирование · both
- [[concept-tasks-api-v2|API задач в коробке: поколения и командный V2]] · Задачи · box
- [[concept-box-caching|Кэш коробки: виды, классы, движки]] · Производительность · box · проверено
- [[concept-composite-site|Композитный сайт: что кэшируется и как вырезать динамику]] · Производительность · box
- [[concept-split-session-modes|Разделённая сессия: горячие и холодные данные]] · Производительность · box
- [[concept-box-scaling|Масштабирование: кластер, репликация, шардинг]] · Производительность · box

### Сущности: термины внедрения (глоссарий)
- [[entity-smart-process|Смарт-процесс (СПА)]]
- [[entity-robots-triggers|Роботы и триггеры]]
- [[entity-bizproc-template-rest-methods|REST-методы шаблонов БП]] · cloud

### Сущности: классы API коробки

Справочник по классам ядра — лежат рядом с практиками, в папке своей области.

**CRM** — [[entity-crm-container|Container]] · [[entity-crm-factory|Factory]] ·
[[entity-crm-item|Item]] · [[entity-crm-operation|Operation + Action]] ·
[[entity-crm-settings|<Type>Settings]] · [[entity-ccrm-status|CCrmStatus / StatusTable]] ·
[[entity-ccrm-owner-type|CCrmOwnerType]] · [[entity-ccrm-field-multi|CCrmFieldMulti]] ·
[[entity-crm-legacy-events|События старого ядра CRM]]

**Бизнес-процессы** — [[entity-cbp-activity|CBPActivity / BaseActivity]] ·
[[entity-bizproc-activity-description|.description.php]] ·
[[entity-cbp-activity-condition|CBPActivityCondition]] · [[entity-cbp-task-service|CBPTaskService]] ·
[[entity-bizproc-field-type|FieldType]] · [[entity-bizproc-globals-manager|GlobalsManager]]

**Интранет** — [[entity-cintranet-utils|CIntranetUtils]] · [[entity-user-absence|UserAbsence]] ·
[[entity-quality-monitor|Монитор качества]]

**Ядро** — [[entity-main-application|Application]] · [[entity-loader|Loader]] ·
[[entity-module-manager|ModuleManager]] ·
[[entity-config-option|Config\Option]] · [[entity-event-manager|EventManager]] ·
[[entity-main-event|Event / EventResult]] · [[entity-main-result|Result / Error]] ·
[[entity-cagent|CAgent]] · [[entity-validation-service|ValidationService]] ·
[[entity-validation-result|ValidationResult / ValidationError]] ·
[[entity-local-directory|/local/]] · [[entity-php-interface|php_interface]] ·
[[entity-urlrewrite|urlrewrite.php]] · [[entity-admin-php-console|Командная PHP-строка]] ·
[[entity-numerator|Numerator]] · [[entity-user-consent|UserConsent]] ·
[[entity-settings-php|.settings.php]] · [[entity-web-cookie|Web\Cookie]] ·
[[entity-http-request-response|HttpRequest / HttpResponse]]

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
- [[source-course43-orm-events|Курс 43: ORM и события]] · dev.1c-bitrix.ru · box
- [[source-devbook-dev-rules|Книга разработчика: правила разработки]] · box
- [[source-devbook-core-d7|Книга разработчика: ядро D7]] · box
- [[source-devbook-crm|Книга разработчика: модуль CRM]] · box
- [[source-devbook-bizproc|Книга разработчика: бизнес-процессы]] · box
- [[source-devbook-ui|Книга разработчика: UI-подсистема]] · box
- [[source-devbook-intranet|Книга разработчика: интранет]] · box
- [[source-devbook-tasks|Книга разработчика: модуль задач]] · box
- [[source-course57-basics|Курс 57 «Бизнес-процессы»: основы]] · dev.1c-bitrix.ru · both
- [[source-course57-templates-designer|Курс 57: объекты, дизайнер, шаблоны]] · both
- [[source-course57-expressions|Курс 57: «Вставка значения», выражения, ошибки]] · both
- [[source-course57-actions-core|Курс 57: действия — документ, задания, конструкции]] · both
- [[source-course57-actions-notify-other|Курс 57: действия — уведомления и прочее]] · both
- [[source-course57-actions-crm-disk|Курс 57: действия CRM и Диска]] · both
- [[source-course57-examples|Курс 57: каталог примеров]] · both
- [[source-course57-developer|Курс 57: глава для разработчика]] · box

Все семь конспектов «Книги разработчика» сверены постранично с сайтом книги 2026-09-21; снимок-манифест
страниц — `raw/sources/2026-09-21-bx24devbook-manifest.md`. Курс 57 «Бизнес-процессы» пройден целиком
2026-09-22 (223 урока), снимок-манифест без текста — `raw/sources/2026-09-22-course57-bizproc-manifest.md`;
файлы-примеры уроков (11 шаблонов `.bpt`) — `raw/sources/2026-09-22-course57-bizproc-files/`.

---

## По статусу

- **verified:** 153 страницы из 161 (`verified`: 2026-06-01 … 2026-09-23).
- **draft:** 8 страниц, по двум причинам —
  **методические каркасы**, которые уточняются после первого применения на проекте
  ([[checklist-data-migration]], [[checklist-golive-deployment]], [[checklist-user-adoption]],
  [[checklist-support-handover]], [[checklist-permissions-audit]],
  [[checklist-tasks-regulations]], [[pattern-smart-process-vs-deal-fields]]);
  **пилот пройден только на коробке, облако и оценка на задачах — впереди** ([[pattern-bizproc-ai-assisted-generation]]).
  Все черновики, которые зависели только от нас, закрыты.
- **Снято с черновика 2026-09-22:** семь страниц по курсу 57 и **все десять страниц «Книги
  разработчика»** — каждая прогнана на коробке в Docker. Прогон нашёл и ошибки в примерах книги:
  `ToDo::load()` оказался методом экземпляра, `addInstanceLazy` не принимает массив-колбэк, пункт
  чек-листа задач требует `nodeId`, события отсутствий приходят от модуля `intranet`, а зоны шаблона
  `bitrix24` в оформлении AIR другие.
- **deprecated:** —

> При устаревании практики ставь `status: deprecated` и ссылку на замену; `/wiki:lint` следит за
> давностью `verified`.

---

## По редакции

- **box (111 страниц):** ветка разработки `development/*`, коробочная часть модулей и справочник
  по 42 классам, файлам и компонентам ядра.
- **cloud (3 страницы):** [[entity-bizproc-template-rest-methods]],
  [[pattern-rest-batch-and-limits]], [[pattern-rest-reliable-delivery]] — сверено через MCP
  по документации облака.
- **both (40 страниц):** практики внедрения, не зависящие от редакции, — playbooks жизненного
  цикла, права, задачи, смарт-процессы, бизнес-процессы и конспекты курса 57.

> Перекос в сторону `box` сохраняется: он следствие того, что перенесённый архив был про
> коробку. Облачно-внедренческая часть пополнена 18.09 (playbooks жизненного цикла, REST-лимиты
> и надёжная доставка, права, задачи, смарт-процессы), но остаётся зоной роста — особенно
> no-code, телефония, маркетплейс и типовые интеграции.
