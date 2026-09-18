# Журнал операций

Хронология операций над вики (ingest / query / lint / ручные правки). Новые записи — сверху.
Формат строки: `ДАТА — операция — что сделано (затронутые страницы)`.

---

## 2026-09

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
