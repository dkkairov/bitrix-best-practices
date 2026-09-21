# Журнал операций

Хронология операций над вики (ingest / query / lint / ручные правки). Новые записи — сверху.
Формат строки: `ДАТА — операция — что сделано (затронутые страницы)`.

---

## 2026-09

- **2026-09-21 — тестовый стенд коробки в Docker** — Развёрнут локальный стенд на официальном
  окружении 1С-Битрикс (`env-docker`): Битрикс24 коробка, main 26.750.0, bizproc 26.1075.0, PHP 8.2,
  Percona 8.0; сайт только на `127.0.0.1`, агенты на cron (33 запуска за 3 минуты), push-сервер
  отвечает. Новый [[recipe-box-test-stand-docker]]: override без правки файлов вендора, секреты сразу
  в файлы, сервер БД `mysql` вместо `localhost` (ошибка `(2002) No such file or directory`), адреса
  push — браузерные через `localhost`, серверные через внутренний `nginx`. Лицензия по офф. курсам:
  один ключ — не более двух установок, отметка «Установка для разработки» и её условия. По коду ядра:
  «Закрыть доступ для посетителей» пускает только пользователей с `edit_other_settings` и мешает
  тестам с сотрудниками. Обновлены [[checklist-dev-environment-and-git]] (официальное окружение
  вместо BitrixDock), [[recipe-mysql-connection-refused]] (не путать две ошибки 2002),
  [[pattern-bizproc-ai-assisted-generation]], хаб `_index-server-admin`, `index.md` (139 страниц).
  Стенд позволяет проверить 11 черновиков сверки с книгой и пилот загрузки шаблонов БП.

- **2026-09-21 — схема: «модулем или нет?» (§9)** — По решению пользователя после сверки с книгой
  правило в `CLAUDE.md` §9 дополнено: вопрос по-прежнему задаётся первым; модуль — для
  переиспользуемого кода ([[pattern-module-based-development-standard]]), иначе — решение в
  `/local/php_interface` по «Книге разработчика» ([[pattern-local-solution-structure]]). Правка
  внесена в ветке сверки и слита в `main` вместе с ней.

- **2026-09-21 — ingest (батч): сверка вики с «Книгой разработчика Bitrix24» (§6, §9)** — Книга
  (https://bx24devbook.website.yandexcloud.net/) пройдена целиком: 97 страниц, все разделы. В `raw/`
  — снимок-манифест без текста (адреса, якоря, хэши): `raw/sources/2026-09-21-bx24devbook-manifest.md`.
  Шесть конспектов `source-devbook-*`, сделанных переносом из архива, переписаны по сайту (ссылки на
  страницы с якорями, карта страниц, таблица сверки); новый [[source-devbook-tasks]]. Текст книги не
  копировали — пересказ; проверка n-граммами по 12 слов нашла совпадения только в идентификаторах.
  **Решения пользователя:** «модулем или нет?» остаётся первым вопросом, ветка «решение» — новая
  [[pattern-local-solution-structure]] (структура `/local/php_interface` по книге), модульный
  стандарт помечен как практика команды, `CLAUDE.md` не менялся; объём — все пробелы P1. Composer —
  решён отдельно (запись ниже). **Исправлено (главное):** пример `OnAfterCrmDealAdd` через
  `addEventHandler` → `…Compatible` (4 места); правила `urlrewrite` — в корневом `/urlrewrite.php`;
  «точное время → агент» (периодические агенты догоняют пропуски, с main 20.5.0 — фоновые работы);
  `disableAllChecks()` выключает 4 проверки; флаги СПА меняются без удаления типа; меню строки грида —
  `actions`; поиск по телефону — через индекс дубликатов; тезис о событиях ORM, ложно приписанный
  книге; `UserAbsence::getIblockId()` — общий ID; тулбар и зоны страницы; пара для `RETURN` —
  `SetPropertiesTypes()`. **Помечено как осознанная практика команды:** cron как база фоновых задач,
  подмена одной фабрики вместо контейнера, задание БП на `CBPActivity` (в книге —
  `CBPCompositeActivity`), `addEventHandler` для ORM, `addAction` с третьим аргументом, `isTrackable()`,
  имена сервисов строчными через точку, `includeModule('ui')`. **Возможное устаревание:**
  оргструктура на инфоблоке при наличии `humanresources.node.*` в REST (MCP) —
  [[concept-org-structure]]. **Новые страницы (16):** [[pattern-local-solution-structure]],
  [[concept-deferred-functions-and-page-areas]], [[recipe-cli-script-bootstrap]],
  [[recipe-d7-custom-validation-rule]], [[recipe-custom-list-page-filter-grid]],
  [[entity-crm-legacy-events]], [[recipe-crm-legacy-entity-crud]], [[recipe-crm-lead-conversion]],
  [[recipe-crm-todo-activity]], [[recipe-smart-process-factory-customization]],
  [[concept-tasks-api-v2]], [[recipe-tasks-v2-commands]], [[recipe-intranet-absence-import]],
  [[entity-bizproc-activity-description]], [[antipattern-bizproc-php-code-activity]],
  [[source-devbook-tasks]]; 11 из них — черновики до проверки на стенде. Обновлено 92 файла вики
  (кластеры: правила и `/local`, CRM, ядро и задачи, UI, интранет, БП), хабы, `index.md` (138 страниц:
  118 verified, 20 draft), [[sources-backlog]] (долг по книге закрыт, очередь проверок и пробелов
  P2/P3). Ссылки: 0 битых, 0 орфанов; 457 ссылок на книгу — страницы и якоря существуют.

- **2026-09-21 — корпус БП: экспорты из дизайнера коробки, а не роботы облака (§6)** — Команда
  прислала экспорт пустого шаблона и небольшого согласования и уточнила: всё выгружено из дизайнера
  БП кнопкой «Экспорт», клиенты — на коробке. Корень `Bizproc Automation template`, который мы
  считали признаком шаблона роботов, дизайнер пишет и в новом пустом шаблоне. У одного старого
  шаблона корень «Последовательный бизнес-процесс». В `tools/bpt` убран ключ `kind` (сборщик
  предупреждает о нём). Корень по умолчанию — как у дизайнера, свой заголовок — `root_title`. Каталог
  пересчитан на 16 файлах: новых типов нет, добавлено значение `alert` у сообщения в чат. Исправлены
  [[concept-bizproc-bpt-format]], [[concept-bizproc-activity-catalog]],
  [[antipattern-bizproc-hardcoded-portal-ids]], [[pattern-bizproc-ai-assisted-generation]], SPEC,
  DESIGN, README утилиты, навык. Контрпример: в одном шаблоне после «Сменить стадию» идёт сообщение
  в чат — 42 из 43. Прерывает ли смена стадии процесс, проверим на стенде. Тестовый стенд — коробка
  в Docker (официальное окружение 1С-Битрикс), NFR-облако — только для облачных вопросов.

- **2026-09-21 — навык Claude Code «БП по ТЗ»** — Создан `.claude/skills/building-bizproc-templates`
  (по TDD для навыков). Контрольный прогон без навыка на задаче «согласование суммы в смарт-процессе»
  дал годный результат, но дорого: ~316 тыс. токенов, 87 вызовов, ~18 мин и ревьюер ещё на ~152 тыс.
  Время ушло на поиск значений свойств в интернете, на свои скрипты перепроверки утилиты и на
  разнобой в составе файлов. Навык задаёт набор файлов, порядок работы и таблицу решений, которые
  нужно принять явно. С навыком — ~174 тыс. токенов, 39 вызовов, ~9 мин, замечания ревьюера учтены
  без ревьюера. По ходу найдено: в корпусе все 40 смен стадии стоят последним шагом ветки, а
  образец `invoice-approval` был наоборот — исправлен (отдельный коммит). Для рабочих файлов по
  клиентам заведена папка `work/` (в `.gitignore`, добавлена в карту `CLAUDE.md` §3). Обновлены
  [[pattern-bizproc-ai-assisted-generation]] (шаг 6 сделан) и хаб `_index-bizproc`, `README.md`,
  `tools/bpt/README.md`.

- **2026-09-21 — снято противоречие с Книгой разработчика: Composer (§6)** — Решение пользователя:
  следуем книге. [[recipe-composer-third-party-libraries]] переписан: по умолчанию `composer.json`
  и `vendor/` в `/local/php_interface/`, автозагрузчик — в `init.php` с проверкой наличия файла;
  прежний вариант `local/vendor/` помечен как отменённый, добавлены шаги переезда. Сверено дословно
  со страницами книги «Структура папки local» и «Свой код»; в книге неточность (в дереве `vendors/`,
  в коде `vendor/autoload.php`) — используем `vendor/`, как в коде. Вариант с модулем `vendor.core`
  оставлен как практика команды, с пометкой «не из книги». Теперь рецепт согласован с
  [[entity-local-directory]], где правило книги уже было.

- **2026-09-21 — схема: эталонные источники (§9)** — По просьбе пользователя в `CLAUDE.md` §9 и
  `README.md` закреплены эталонные источники: официальная документация (`apidocs.bitrix24.ru`,
  `dev.1c-bitrix.ru`) и «Книга разработчика Bitrix24» (bx24devbook) — ссылаемся на конкретную
  страницу книги, расхождения решаем явно. Замечено: 6 конспектов `source-devbook-*` сделаны
  переносом из архивной вики, а не с сайта книги. Первое расхождение:
  [[recipe-composer-third-party-libraries]] по умолчанию советует `local/vendor/`, а книга —
  держать `vendor` и `composer.json` в `/local/php_interface`. Страницы пока не правились:
  предложена отдельная сверка вики с книгой.

- **2026-09-21 — сборщик спецификаций БП и каталог действий** — Влита ветка `bpt-compiler`
  (11 коммитов, работа шла в worktree параллельно с другой сессией): `tools/bpt` разобран на
  классы; добавлены каталог 25 типов действий, чтение спецификаций YAML/JSON, сборщик (шаги,
  структура, ссылки `{=@id:…}`, проверки), снимок портала с плейсхолдерами `{{вид:Название}}`,
  разбор `.bpt` в спецификацию, схема Mermaid, пример и `tools/bpt/SPEC.md`. Тесты: 67, в том
  числе сверка на корпусе из 14 экспортов — разбор и обратная сборка воспроизводят каждый шаблон
  по смыслу, напрямую и через снимок. По ходу найдены и исправлены: путаница полей с одинаковым
  названием в снимке, поиск «сырых» ID не в той структуре, значения по умолчанию у ознакомления.
  Корпус вырос до 14 файлов — в каталог добавлен `CrmGetRelationsInfoActivity`. Создана
  [[concept-bizproc-activity-catalog]]; обновлены [[concept-bizproc-bpt-format]] (сборка и разбор),
  [[pattern-bizproc-ai-assisted-generation]] (шаги 2–3 сделаны, пример в настоящем формате; статус
  `draft` до пилота на портале), хаб bizproc, `index.md`. Итого страниц: 122.

- **2026-09-18 — lint, долг по источникам, снятие черновиков** — **Lint по всей вики (121 страница):**
  структурно чисто — 0 битых ссылок, 0 орфанов, 0 ошибок `edition` и frontmatter, имена и префиксы
  корректны, подпапок по типам и пустых папок нет, `aliases` не дублируются, устаревших `verified`
  нет. Единственная категория — REST без сверки; через MCP подтверждены `crm.type.add`,
  `tasks.task.list`, `bizproc.task.list`, `bizproc.task.complete`, `log.blogpost.add`;
  `tasks.task.history.list` в документации **не подтверждён** и убран из
  [[pattern-tasks-effectiveness-from-db]]. Ложные срабатывания проверки —
  `crm.service.factory.dynamic` (имя сервиса) и `crm.controller.item.update` (AJAX-действие).
  **Долг по источникам закрыт частично:** заведены шесть кластерных конспектов
  ([[source-devbook-dev-rules]], [[source-devbook-core-d7]], [[source-devbook-crm]],
  [[source-devbook-bizproc]], [[source-devbook-ui]], [[source-devbook-intranet]]) — по разделу
  книги, а не по главе; `sources` проставлен на **50 страницах**. Снимки глав в `raw/` сознательно
  не копировались: объёмный сторонний текст, для прослеживаемости хватает ссылки в конспекте.
  У эмпирических страниц `sources` намеренно пуст — они сами источник. **Черновики:**
  [[entity-loader]] и [[entity-config-option]] сверены по справочнику D7 на `dev.1c-bitrix.ru`
  и переведены в `verified`; у `Option` добавлен `getRealValue()` — штатный способ увидеть
  расхождение с кэшем ([[antipattern-cli-php-as-root]]). [[entity-module-manager]] остаётся
  `draft`: страница справочника не отдала содержимое, поиск выводит на функции старого ядра —
  причина записана прямо на странице. Обновлены `index.md`, хабы sources и core-d7,
  `sources-backlog`.
- **2026-09-18 — облачно-внедренческая половина: 9 страниц** — Выправление перекоса после переноса
  архива. **REST (сверено через MCP, `provenance: documented`, `edition: cloud`):**
  [[pattern-rest-batch-and-limits]] (`batch` REST 3.0: `cmd`/`halt`/`as`, связывание через
  `$result[...]`, разбор `result_error`/`result_total`/`result_next`; при `halt = 0` частичный
  отказ выглядит как успех) и [[pattern-rest-reliable-delivery]] (`event.offline.get` снимает
  события с очереди, `event.offline.list` — только читает; таблица «какие коды ошибок повторять,
  какие нет»; идемпотентность по `MESSAGE_ID`). **Playbooks — жизненный цикл закрыт целиком:**
  [[checklist-data-migration]], [[checklist-golive-deployment]], [[checklist-user-adoption]],
  [[checklist-support-handover]]. **Модули:** [[checklist-permissions-audit]],
  [[pattern-smart-process-vs-deal-fields]], [[checklist-tasks-regulations]].
  Семь методических страниц заведены как `draft` — это каркасы: последовательность шагов и типовые
  ошибки взяты из практики внедрения и уже накопленного в вики, но пороги, роли, сроки реакции и
  формулировки приёмки команда проставляет после первого применения. Так честнее, чем ставить
  `verified` на непроверенный регламент. Обновлены пять хабов и `index.md`.
- **2026-09-18 — снято противоречие: registerEventHandler и события D7 ORM** — Проверка после
  переноса: [[pattern-events-over-core-modification]] рекомендовала `registerEventHandler` для
  распространяемой функциональности без оговорок, а новый [[recipe-d7-orm-event-subscription]]
  показывает, что для событий D7 ORM этот путь молча не работает (`MESSAGE_ID` — `VARCHAR(50)`,
  имена событий длиннее). Противоречие внесено сегодня же при добавлении рецепта. По §6 схемы
  старая страница не переписана, а дополнена явной врезкой-исключением со ссылкой на рецепт;
  `verified` разделён на две части (курс 43 для базы, живая проверка для оговорки), `related`
  пополнен. Найдено вручную — `/wiki:lint` на новом состоянии ещё не гонялся.
- **2026-09-18 — справочник классов ядра: 40 entity-страниц** — По решению завести полный
  набор карточек классов (а не только частотные). Разложены по папкам своих областей, а не в
  `glossary/`: глоссарий остаётся сквозным словарём внедрения, классы лежат рядом с
  практиками. Хабы CRM, bizproc, administration, core-d7 и templates-design разделены на
  «Практики» и «Классы». **CRM (8):** Container, Factory, Item, Operation+Action,
  `<Type>Settings`, CCrmStatus, CCrmOwnerType, CCrmFieldMulti. **bizproc (5):** CBPActivity и
  BaseActivity, CBPActivityCondition, CBPTaskService, FieldType, GlobalsManager.
  **administration (2):** CIntranetUtils, UserAbsence. **core-d7 (13):** EventManager,
  Event/EventResult, Result/Error, CAgent, ValidationService, ValidationResult, каталоги
  `/local/` и `php_interface`, `urlrewrite.php`, Командная PHP-строка + три сверх архива —
  Loader, ModuleManager, Config\Option. **templates-design (12):** Toolbar, Button, компонент
  и типы полей фильтра, `Filter\Options`, свой фильтр, `BX.Main.Filter`, компонент грида,
  `Grid\Options`, `BX.Main.gridManager`, шаблон дизайна, ThemePicker.
  **Не дублировались:** ServiceLocator и DataManager — у них уже есть полноценные концепты.
  **Статусы:** три карточки (`Loader`, `ModuleManager`, `Option`) — `draft`: их не было в
  перенесённом материале, MCP `apidocs` покрывает REST и облако, а не PHP-ядро коробки, так что
  сверки с первоисточником не было; сказано и в самих страницах, и в хабе. Проверка: имена
  уникальны, 0 битых ссылок, 0 орфанов. Итого: 106 контент-страниц (было 66), из них 43 entity.
- **2026-09-18 — перенос архивной вики: playbook встречи + долг по источникам** — Последняя
  находка архива — процессная: [[checklist-requirements-workshop]] (разбор рабочей встречи по семи
  файлам — контекст / требования / решения с отклонёнными вариантами / задачи / открытые вопросы /
  технические детали / исходные заметки; три правила: не выдумывать требования, указывать
  противоречия явно, называть чего именно не хватает). В `sources-backlog` зафиксирован **долг по
  источникам**: страницы, перенесённые 18.09, пришли пересобранными, а не через `ingest` — у них нет
  `source-summary` и снимков в `raw/`, поэтому `sources` пустой, а происхождение указано в
  `verified`; закрывается батч-ингестом глав devbook с простановкой `sources` на существующих
  страницах. В `README.md` добавлен `tools/module-skeleton`. Проверка вики: имена уникальны,
  0 битых ссылок, 0 орфанов; 66 контент-страниц, 18 хабов. Итог переноса: 32 новые страницы,
  2 дополнены, схема расширена тремя правилами.
- **2026-09-18 — перенос архивной вики: концепции коробки (тир B) + скелет модуля** — Семь
  концепций, закрывающих базу разработки. **core-d7:** [[concept-request-lifecycle]] (две фазы,
  узловые шаги; `$USER` только с шага 9, `init.php` — до `OnPageStart`, агенты — в фоновых работах),
  [[concept-orm-datamanager-events]] (девять хуков; имя класса без суффикса `Table`, namespace в
  имени ловит фильтрованную диспетчеризацию, неверное имя = тишина; ловушка OPcache),
  [[concept-service-locator]] (три места регистрации, соглашение имён, подмена одного сервиса
  вместо контейнера), [[concept-validation-d7]] (атрибуты, правило vs валидатор, каталог правил;
  nullable-свойство без присвоения пропускается). Заведена папка **templates-design** с хабом и
  [[concept-ui-subsystem]] (тулбар/фильтр/грид/кнопки; `ui` и `main` — разные модули, вне шаблона
  Bitrix24 подсистема не работает). **bizproc:** [[concept-bizproc-engine]] (шаблон копируется в
  инстанс — правка шаблона не влияет на запущенные; робот = тот же класс с `TYPE`
  `robot_activity`). **administration:** [[concept-org-structure]] (подразделения = разделы
  инфоблока; `Option::get(..., '-1')`, а не `0`, иначе `getList` вернёт всё). **Код:**
  `tools/module-skeleton/` — обезличенный каркас модуля для `/local/modules/` (version.php,
  установщик с инжектом `init.php` по маркерам, регистронезависимый автозагрузчик, `lib/Guard.php`);
  README связывает каждое решение со страницей вики, откуда оно взято. Проверка: 0 битых ссылок,
  0 орфанов, имена уникальны. Итого контент-страниц: 65 (было 34).
- **2026-09-18 — перенос архивной вики: продуктовые модули (тир A)** — 13 страниц, всё обезличено
  и с версиями ядра в `verified`. Заведены три папки по факту первой страницы: **smart-process**
  ([[recipe-smart-process-programmatic-creation]] — `TypeTable` + `CUserTypeEntity`, идемпотентность,
  `getUserFieldEntityId()` вместо угадывания `ENTITY_ID`, `UF_CRM_<ID типа>` ≠ `UF_CRM_<ENTITY_TYPE_ID>`),
  **tasks-projects** ([[pattern-tasks-effectiveness-from-db]] — читать `b_tasks_effective` теми же
  запросами, что и ядро; REST упирается в 93 %), **communications** ([[recipe-post-to-livefeed]] —
  без `CBlogPost::Notify(bSoNet)` пост невидим; транзакция обязательна). **CRM:**
  [[concept-crm-universal-api]], [[concept-crm-dictionaries]] (новое API читает, старое пишет —
  исключение из «D7 современный путь»), [[pattern-crm-action-vs-event]] (в событии приходят только
  изменённые поля — источник ложных срабатываний), [[recipe-crm-history-all-fields]],
  [[recipe-crm-card-editor-js-access]] (`BX.Crm.EntityEditor.items`, `BX.UI` пуст),
  [[recipe-crm-hide-card-block-js]], [[pattern-crm-timeline-client-side]]. **bizproc:**
  [[recipe-bizproc-custom-task-activity]] (`<div>`-вёрстка формы — в новом UI `<tr>` даёт пустое
  тело задания; категория `task`; `SendExternalEvent` статически). **administration:**
  [[recipe-custom-left-menu-section]] (`Intranet\CustomSection` вместо правки `.top.menu_ext.php`;
  дефис в `CODE` страницы → 404). Обновлены хабы crm, bizproc, administration, три новых хаба,
  карта тем и представления `index.md`.
- **2026-09-18 — перенос архивной вики: коробочная разработка (тир A)** — Перенесены эмпирические
  находки архива по разработке, все с версиями ядра в `verified` и обезличенные (стенды, домены и
  названия заказчиков вычищены по §4.4). **core-d7:** [[recipe-d7-orm-event-subscription]]
  (`RegisterModuleDependences` молча не работает для D7-ORM-событий — `MESSAGE_ID` VARCHAR(50);
  рабочий путь `addEventHandler` + инжект `init.php` с маркерами; архитектура обработчика с ранним
  выходом и четырьмя исходами), [[pattern-agents-vs-cron]] (таблица выбора + три однозначных
  правила), [[antipattern-ajax-controller-lowercase-name]] (`Resolver` лоуэркейсит namespace →
  `DefaultController`; проверка из консоли врёт, воспроизводить в браузере). **modules-custom:**
  [[pattern-module-self-disabling-guard]] (три уровня защиты, сверка сигнатур рефлексией до
  объявления класса); дополнен [[recipe-module-structure-and-install]] — обязательный
  `install/version.php`, автозагрузчик через `spl_autoload_register` вместо
  `registerAutoLoadClasses`, таблица граблей `/local/modules/`. **server-admin:**
  [[recipe-safe-module-deploy]], [[recipe-git-deploy-to-production]],
  [[checklist-windows-to-linux-deploy]], [[recipe-mysql-connection-refused]],
  [[antipattern-cli-php-as-root]]. Обновлены три хаба и `index.md`. Часть `related` пока указывает
  на страницы модулей — они в следующем блоке.
- **2026-09-18 — перенос архивной вики: схема и сквозные правила** — Изучена предшествующая
  LLM-вики разработки (196 страниц, 75 источников, код трёх универсальных модулей) — она покрывает
  коробочную разработку, которой у нас почти нет. Принято решение переносить пересборкой под нашу
  схему, а не копированием. **Схема:** в `CLAUDE.md` добавлены §4.4 запрет секретов, персданных и
  клиентских привязок (с обязанностью вычищать при переносе); §8 батч-ингест (пачка связанных
  источников = один обзор, одна правка `index.md`, одна запись в лог) и архивация `log.md` в
  `log-archive/` после ~1000 строк; §9 обязательный вопрос «модулем или нет?» до первой строки кода
  и иерархия способов изменения. **Страницы:** [[concept-change-invasiveness-hierarchy]] (5 уровней
  инвазивности, 8 «никогда», 5 «как правильно», резолюция противоречия про `php_interface`),
  [[concept-platform-reverse-engineering]] (4 приёма: REST-регистрация, кодовое имя, `grep`,
  logpoint на `BX.onCustomEvent`), [[concept-bitrix-naming-conventions]] (`CCrmDeal` vs
  `\Bitrix\Crm\DealTable`, парадокс справочников, формат имён компонентов и сервисов). Обновлены
  хаб core-d7 и `index.md`.
- **2026-09-18 — обезличивание примеров (§4.4)** — По новому правилу схемы «никаких клиентских
  привязок» заменены реальные идентификаторы портала в примерах на синтетические того же формата:
  коды полей, имена действий `A…`, `user_*`, `group_*`, `DYNAMIC_*`, `PARENT_ID_*`, стадии `DT…`,
  ID шаблона и названия смарт-процесса/поля. Затронуты [[concept-bizproc-bpt-format]],
  [[entity-bizproc-template-rest-methods]], [[antipattern-bizproc-hardcoded-portal-ids]] и
  `tools/bpt/DESIGN.md`. Домена портала в репозитории не было. Факты и выводы не изменились.
- **2026-09-18 — дизайн: каталог действий и сборщик спецификаций БП** — Согласован
  `tools/bpt/DESIGN.md`: язык спецификаций повторяет Bitrix один в один (решение: спецификации
  пишет агент, люди проверяют схему), каталог 23 типов действий с формами вложенности, снимок
  портала с плейсхолдерами `{{вид:Название}}`, команды `catalog`, `compile`, `decompile`, `render`,
  `snapshot`, тесты на синтетике плюс сверка «туда-обратно» на корпусе. Вне блока: REST, навык
  Claude Code, хуки, процессы со статусами. Открытые вопросы: нет экспорта из дизайнера (служебные
  поля корня), не проверена загрузка с пустым `DOCUMENT_FIELDS`, смысл связки `"0"`/`"1"` в
  условиях. Правок вики нет.
- **2026-09-16 — дофайл по query: БП, `.bpt` и утилита** — Созданы 4 страницы:
  [[concept-bizproc-bpt-format]] (формат `.bpt`: структура, выражения, 4 формата условий, роботы
  vs дизайнер; verified на 11 экспортах облака); [[entity-bizproc-template-rest-methods]] (glossary,
  cloud; `bizproc.workflow.template.*` сверены через MCP: только контекст приложения, шаблоны
  роботов недоступны, `DOCUMENT_TYPE`, смежные методы); [[pattern-bizproc-ai-assisted-generation]]
  (**draft**: агент пишет спецификацию, код собирает, тестовый портал подтверждает; дообучение — не
  вариант); [[antipattern-bizproc-hardcoded-portal-ids]] (cross-cutting). Утилита
  `tools/bpt/bpt.php` (decode / encode / check / analyze / compact) проверена: 11 из 11 файлов
  байт-в-байт, негативные тесты, windows-1251 на синтетике. `analyze` нашёл в одном клиентском
  шаблоне 5 ссылок на удалённые шаги согласования. **Уточнение к записи ниже:** зашитые ID есть в
  логике 10 из 11 файлов (11 из 11 — если считать `DOCUMENT_FIELDS`). Обратные ссылки:
  [[pattern-robots-vs-bizproc-decision]] (строка «развёртывание через REST», provenance → mixed),
  [[entity-robots-triggers]], [[entity-smart-process]], [[recipe-rest-oauth-app-setup]],
  [[pattern-rest-webhooks-and-events]], [[recipe-migrations-as-code]]; хабы bizproc, glossary,
  antipatterns; `index.md`. Схема: `tools/` добавлен в карту каталогов (`CLAUDE.md` §2–3) и
  `README.md`; `*.bpt` и `*.bpt.json` — в `.gitignore`. Итого страниц: 34.
- **2026-09-16 — query: может ли Claude Code сам создавать БП** — Вопрос: реализуемость генерации и
  настройки БП агентом (MCP + вики + `.bpt`), дообучение модели, риски. Из вики использованы только
  [[pattern-robots-vs-bizproc-decision]] и [[entity-robots-triggers]] — по формату `.bpt` и
  REST-шаблонам страниц нет (**пробел покрытия**). Проанализированы 11 `.bpt` пользователя (вне
  репозитория, не сохранялись): все — шаблоны роботов стадий СПА, доработанные в дизайнере; формат =
  zlib + PHP `serialize`, обратимая сборка через JSON — 11/11 байт-в-байт; везде «зашиты» ID портала.
  MCP (apidocs): `bizproc.workflow.template.*` — только в контексте приложения и только для шаблонов
  дизайнера; шаблоны роботов через REST недоступны. Fine-tuning: публично — только Claude 3 Haiku в
  Amazon Bedrock. Предложено дофайлить: концепт формата `.bpt`, энтити REST-методов шаблонов, паттерн
  AI-генерации БП, антипаттерн «жёстко заданные ID портала» — ждёт согласия.

## 2026-06

- **2026-06-21 — дофайл: паттерн библиотеки модулей + согласование** — Создан
  [[pattern-module-library-monorepo]] (modules-custom, box): монорепо-библиотека отраслевых модулей,
  поставка всем набором + установка только нужных (отключение лишних), единая версия. MCP подтвердил:
  apidocs покрывает REST/облачные приложения, не коробочные модули/git-стратегию. **Снято противоречие
  (§6):** [[recipe-module-versioning-and-private-distribution]] переписан с multi-repo (отдельный репо
  на модуль) + версии per-module → монорепо + единая версия (multi-repo оставлен исключением); в
  [[pattern-module-based-development-standard]] уточнено «изоляция = от ядра» + ссылка. Входящие: хаб
  modules-custom, index.md. Итого страниц: 30.
- **2026-06-21 — дофайл: рецепт Composer-библиотек** — Создан
  [[recipe-composer-third-party-libraries]] (development/core-d7, edition box): где хранить сторонние
  пакеты (dompdf, PhpWord) — общий `local/vendor/` (по умолчанию) либо базовый модуль `vendor.core`;
  почему не в каждый модуль (конфликт версий) и не в `/bitrix/`; два автозагрузчика. Входящие: хаб
  core-d7, `index.md`. Итого страниц: 29.
- **2026-06-21 — дофайл: концепт «Framework vs Bitrix24»** — Создана
  [[concept-bitrix-framework-vs-bitrix24]] (development/core-d7, edition both): разграничение движок
  (Bitrix Framework / БУС) ↔ продукт (Bitrix24), слои, что где дорабатывается, облако vs коробка,
  два механизма «события» (PHP-событие коробки ↔ REST `onCrmDealAdd`). REST-событие сверено через MCP
  (`apidocs.bitrix24.ru`). Входящие: хаб core-d7, `index.md`, [[concept-code-namespaces-and-autoloading]].
  Итого страниц: 28.
- **2026-06-20 — правка: дерево `/local/`** — В [[concept-code-namespaces-and-autoloading]] раздел
  «Где размещать свой код» развёрнут в полное дерево `/local/` (php_interface, modules, components,
  templates, activities, js, gadgets, wizards) с пояснениями и приоритетом `/local/` над `/bitrix/`.
  Добавлена перелинковка с [[recipe-module-structure-and-install]].
- **2026-06-20 — refactor: уникальные имена хабов + чистка графа** — Хабы `_index.md` (14 шт.)
  переименованы в `_index-<папка>.md` (напр. `_index-core-d7.md`) для различимости в Obsidian
  (вкладки, поиск, переключение). Обновлены все относительные ссылки на хабы (футеры страниц, карта
  тем `index.md`, `sources-backlog`), правила в `CLAUDE.md` (§3–§5) и команды `ingest`/`query`/`lint`.
  Плейсхолдеры в 7 шаблонах обёрнуты в `code` (убраны висячие узлы графа); служебные `_templates/`,
  `raw/`, доки-схемы исключены из граф-вью.
- **2026-06-20 — refactor: упрощение структуры** — Снят раздутый каркас (модель Карпати сохранена).
  Убрана ID-система `<РАЗДЕЛ>-<ТИП><NN>` (коды, счётчики, реестр) → имена страниц `<тип>-<слаг>.md`.
  Подпапки решений (`guides/patterns/antipatterns/reference/`) упразднены — страницы плоско в папке
  модуля/области. Удалены 12 пустых хабов/папок; впредь папка заводится по факту первой страницы.
  Frontmatter урезан до ядра (убраны `id`, `confidence`). Навигация сжата: `overview.md` влит в
  `index.md` (убраны представления «По ID» и «Реестр разделов»). 27 страниц переименованы (`git mv`),
  ~189 `[[ссылок]]` и относительные пути пересчитаны. Согласованы `CLAUDE.md` (§3–§5, §8, §10),
  `README.md`, 7 шаблонов и команды `ingest`/`query`/`lint`. Папок в `wiki/`: ~50 → 17.
- **2026-06-20 — standards: разработка коробки** — Оформлен свод стандартов разработки (edition=box):
  обзор [[concept-dev-standards]]; модули D03 (PT01 модульная разработка, RC01 структура+install,
  RC02 версии+приватная дистрибуция); core-d7 (PT01 события, CN03 код-стайл/безопасность, CN04 тесты);
  D06-RC01 миграции как код; D07-CL01 окружение+git. Ингест курса 43 (раздел модулей) → конспект
  [[source-bxfw-course43-modules]] + raw. Обновлены 5 хабов и `index.md` (все представления).
- **2026-06-20 — lint + правки доков** — Полная перепроверка после рефакторинга нумерации: 0 битых
  ссылок (включая относительные к хабам), 0 орфанов, `id` уникальны и согласованы (имя↔id,
  префикс↔папка↔тип), `edition`/`verified` корректны, артефактов кодировки нет. Правки по итогам:
  `README.md` (добавлены система нумерации и подпапки), `wiki/overview.md` (проставлен `verified`),
  `CLAUDE.md` §8 (lint упоминает ID-проверки).

- **2026-06-20 — refactor: нумерация** — Введена сквозная адресация решений `<РАЗДЕЛ>-<ТИП><NN>`
  (см. `CLAUDE.md` §4.5): коды разделов M01–M14 / D01–D07 / X01–X03 / G / S, под-префиксы типов
  CL/RC/PT/AP/CN/EN/SS. Все 17 контент-страниц переименованы в `<id>-<слаг>.md` и разложены по
  подпапкам `guides/patterns/antipatterns/reference/` (через `git mv` — история сохранена); добавлены
  поля `id` и `aliases` (прежний слаг). Перелинкованы тела и `related` (формат `[[id-слаг|подпись]]`),
  обновлены 14 хабов `_index.md`, `index.md` (новые разделы «По ID» и «Реестр разделов»), 7 шаблонов и
  команды `ingest`/`lint`/`query`. Аудит: 0 битых ссылок, `id` уникальны, имена файлов ↔ `id` совпадают.

- **2026-06-19 — ingest** — Урок «Пространства имён» (курс 43, lesson 3524) → создана
  `wiki/development/core-d7/code-namespaces-and-autoloading.md` и конспект
  `wiki/sources/src-bxfw-course43-namespaces.md`; источник в `raw/sources/`. Обновлены хаб core-d7
  и `index.md`. edition=box, provenance=documented/mixed.
- **2026-06-19 — triage источников** — Зарегистрированы 4 источника от пользователя в
  `wiki/sources/sources-backlog.md` с классификацией и приоритетами: курс 43 (P1), devbook (P1),
  awesome-bitrix (каталог, P2), api_help (каталог/legacy, P3). Залинкован бэклог в хабе и `index.md`.
- **2026-06-19 — bootstrap** — Создан каркас БЗ по методу LLM Wiki Карпати: схема `CLAUDE.md`,
  `README.md`, `log.md`, `.gitignore`; структура каталогов `raw/`, `wiki/` (modules, development,
  cross-cutting, glossary, sources); 7 шаблонов в `wiki/_templates/`; 3 команды
  `.claude/commands/wiki/`; хабы `_index.md`; стартовый набор seed-страниц; каталог `index.md`.
  REST-страницы сверены с `apidocs.bitrix24.ru` через MCP.
