---
title: "Бэклог источников"
type: concept
module: sources
edition: both
status: verified
provenance: documented
verified: "2026-09-24"
tags: [источники, бэклог, ингест, очередь]
sources: []
related: ["[[source-b24-crm-deal-add]]", "[[source-devbook-dev-rules]]", "[[source-devbook-bizproc]]", "[[source-course57-basics]]"]
updated: "2026-09-24"
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
| 1 | [Курс 43 «Разработчик Bitrix Framework»](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43) (урок [Пространства имён](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=3524)) | содержание (серия уроков) | [development/](../development/core-d7/_index-core-d7.md) | **P1** | поурочно; взяты: пространства имён (S-SS02), модули (S-SS03), **ORM и события (19 уроков, 2026-09-23 → [[source-course43-orm-events]])**; далее — связи сущностей (`ReferenceField`, N:M) и аннотации ORM для IDE |
| 2 | [bx24devbook — Книга разработчика](https://bx24devbook.website.yandexcloud.net/) | содержание (многоглавный), **эталон** (`CLAUDE.md` §9) | [development/](../development/core-d7/_index-core-d7.md) + [modules/](../modules/crm/_index-crm.md) | **сделано** | **пройдена целиком 2026-09-21** — 97 страниц, все разделы навигации: семь конспектов `source-devbook-*`, снимок-манифест `raw/sources/2026-09-21-bx24devbook-manifest.md`. Дальше — пересверка только страниц, у которых изменился хэш в манифесте |
| 3 | [awesome-bitrix](https://github.com/awesomebitrix/awesome-bitrix) | каталог-указатель | мета (порождает под-источники) | **P2** | разобрать по категориям → завести под-источники |
| 4 | [api_help (старое ядро)](https://dev.1c-bitrix.ru/api_help/) | каталог-указатель (legacy) | [development/](../development/core-d7/_index-core-d7.md) | **P3** | по требованию: только когда нужен конкретный метод старого ядра; сначала смотреть D7/devbook/MCP |
| 5 | [Курс 57 «Бизнес-процессы»](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&INDEX=Y) | содержание (многоглавный), **эталон** — официальная документация (`CLAUDE.md` §9) | [modules/bizproc/](../modules/bizproc/_index-bizproc.md) | **сделано** | **пройден целиком 2026-09-22** — 223 урока, 28 глав: восемь конспектов `source-course57-*`, снимок-манифест `raw/sources/2026-09-22-course57-bizproc-manifest.md`. Дальше — пересверка уроков, у которых изменился хэш |
| 6 | [docs.1c-bitrix.ru — документация Bitrix Framework](https://docs.1c-bitrix.ru/) | каталог-указатель + содержание; **эталон** (`CLAUDE.md` §9), новый формат официальной документации | [development/](../development/core-d7/_index-core-d7.md) | **P1** | 221 страница, снимок-манифест `raw/sources/2026-09-24-docs-1c-bitrix-manifest.md`. Взято: `orm` (сверка), **`performance` (6), `database` (12), `advanced` (12 из 16) — 2026-09-24**; в `advanced` отложены `uuid`, `encoding`, `geolocation` (по требованию) и `vue` (с разделом `ui`); **`framework`, `security`, `ui` пройдены — 2026-09-24**; из `modules` взяты highload-блоки и инфоблоки, а `catalog` (10) и `sale` (25) отложены как тема «1С-Битрикс: Управление сайтом» — вне фокуса вики. Очередь: `cms-basics` (16) → `get-started` (20) → при необходимости `modules/architecture` |

> **Долг по «Книге разработчика» закрыт (2026-09-21).** История: 2026-09-18 при переносе архивной
> вики заведены шесть кластерных конспектов — по тому, что взято в вики, без перечитывания книги;
> `sources` проставлен на 50 страницах. 2026-09-21 книга пройдена постранично с сайта: конспекты
> переписаны (ссылки на страницы с якорями, карта «страница книги → страница вики», таблица сверки),
> добавлен конспект модуля задач, расхождения помечены по §6 на самих страницах.
>
> Что остаётся:
> - **текст глав в `raw/` не копируем** — авторский текст; вместо него снимок-манифест с адресами,
>   якорями и хэшами, по которому видно, что книга изменилась;
> - у **эмпирических** страниц `sources` намеренно пуст: они сами — источник, происхождение
>   указано в `verified`;
> - проверки на стенде — список ниже.

## Новая документация фреймворка — разведка 2026-09-24

Курс 43 начинает каждый урок ссылкой «тему можно изучить в новом формате» на
[docs.1c-bitrix.ru](https://docs.1c-bitrix.ru/) — содержание курса переносят туда. Что даёт сверка
раздела ORM с нашими страницами (написаны 2026-09-23 по курсу + стенду):

| Где документация впереди курса | Где наши страницы впереди документации |
|---|---|
| полный состав классов полей (`Decimal`, `Array`, `Json`, `Object`, `Crypto`, `Secret`) против восьми скалярных в курсе — взято в [[concept-d7-orm-entity]] | каскадное удаление и политики связей: в [`entity-relations`](https://docs.1c-bitrix.ru/pages/orm/entity-relations.html) их нет |
| беглый API `configure*` как основная форма записи (курс показывает массивы) | `whereExpr`, подзапросы, кэш выборки: [`query-builder`](https://docs.1c-bitrix.ru/pages/orm/query-builder.html) ограничен `set*`/`add*`/`registerRuntimeField` |
| версии модуля проставлены в тексте (20.5.200, 24.100.0) | `SqlExpression` с плейсхолдерами и защитой от инъекции разобран подробнее ([[recipe-d7-orm-crud]]) |
| — | два способа подписки на события ORM (`addEventHandler` против `registerEventHandler`) и разбор, когда какой ([[entity-event-manager]]) |

**Вывод:** документация — не замена нашим страницам, а третий эталон рядом с курсом и книгой.
Дат и версий документа на страницах нет, поэтому изменения ловим так же, как у книги, — снимком
с хэшами. Раздел ORM закрыт сверкой выше; остальные разделы ингестим по строке 6 таблицы.

## Карта разделов devbook → страницы вики
Подробная карта «страница книги → страница вики» — в каждом конспекте `source-devbook-*`.
- С чего начать, Документация, Разработка (введение, GIT, структура `/local`, технологии, свой код)
  → [core-d7](../development/core-d7/_index-core-d7.md),
  [modules-custom](../development/modules-custom/_index-modules-custom.md),
  [server-admin](../development/server-admin/_index-server-admin.md) —
  [[source-devbook-dev-rules]], [[source-devbook-core-d7]]
- Общие сведения (uri, ядро, страница, шаблон) → [core-d7](../development/core-d7/_index-core-d7.md),
  [templates-design](../development/templates-design/_index-templates-design.md) —
  [[source-devbook-core-d7]], [[source-devbook-ui]]
- Разработка → UI (тулбар, кнопки, фильтр, таблицы) →
  [templates-design](../development/templates-design/_index-templates-design.md) — [[source-devbook-ui]]
- Модуль CRM (словари, Universal API, сущности, смарт-процессы, дела) →
  [crm](../modules/crm/_index-crm.md), [smart-process](../modules/smart-process/_index-smart-process.md) —
  [[source-devbook-crm]]
- Модуль Интранет (оргструктура, отсутствия, темы) →
  [administration](../modules/administration/_index-administration.md) — [[source-devbook-intranet]]
- Модуль Бизнес-процессы (действия, окружение, PHP код, свои действия и условия) →
  [bizproc](../modules/bizproc/_index-bizproc.md) — [[source-devbook-bizproc]]
- Модуль Задачи (провайдеры, команды V2) → [tasks-projects](../modules/tasks-projects/_index-tasks-projects.md) —
  [[source-devbook-tasks]]

## Очередь после сверки с книгой (2026-09-21)

**Проверки на стенде** (коробка в Docker) — снять `draft` или поправить страницы:
- 10 черновиков, написанных по книге без прогона: [[concept-deferred-functions-and-page-areas]],
  [[recipe-cli-script-bootstrap]], [[recipe-d7-custom-validation-rule]],
  [[recipe-custom-list-page-filter-grid]], [[recipe-crm-legacy-entity-crud]],
  [[recipe-crm-lead-conversion]], [[recipe-crm-todo-activity]],
  [[recipe-smart-process-factory-customization]], [[recipe-tasks-v2-commands]],
  [[recipe-intranet-absence-import]]. [[entity-bizproc-activity-description]] снят с черновика
  2026-09-22 (курс 57 и код ядра).
- Вопросы с расхождением «книга ↔ практика команды»: перенос глобалов между порталами
  ([[entity-bizproc-globals-manager]]). **Сняты 2026-09-22** курсом 57, кодом ядра и стендом: базовый
  класс задания БП — оба, по наличию веток ([[recipe-bizproc-custom-task-activity]]); результат из
  `RETURN` дизайнер видит без `ADDITIONAL_RESULT` (в роботах — открыто); ошибки `ErrorCollection`,
  исключения и `Faulting` процесс не останавливают, фатальная ошибка PHP его вешает
  ([[entity-cbp-activity]]).
- Возможное устаревание (§6): модель оргструктуры на свежей коробке — инфоблок или `humanresources`
  ([[concept-org-structure]]).

**Пробелы, найденные при сверке (P2/P3):**
| Тема | Будущая страница | Приоритет |
|------|------------------|-----------|
| Панель групповых действий грида | `entity-grid-action-panel` | P2 |
| Валидация в контроллерах D7 | `recipe-d7-controller-validation` | P2 |
| Своё действие БП на `BaseActivity` (без задания) | [[recipe-bizproc-custom-activity-baseactivity]] — черновик 2026-09-22 | сделано |
| Смарт-счета в коробке | `entity-crm-smart-invoice` | P2 |
| Поиск задач и доступ (провайдеры) | `recipe-tasks-search-and-access` | P3 |

## Очередь после курса 57 (2026-09-22)

**Проверки на стенде — сделано 2026-09-22.** Все семь черновиков курса прогнаны в отдельном тестовом
смарт-процессе «Черновики курса 57 (тест)» и сняты с черновика; найдено и исправлено: лишний запрос
доработки в [[recipe-bizproc-approval-route]], номер договора в
[[recipe-bizproc-custom-activity-baseactivity]], логические функции и `%` в
[[concept-bizproc-expressions]]. Остаётся:
- права статуса у списков и инфоблоков, лимит одновременных процессов, рекурсивная смена стадий —
  не прогонялись (нужны другие документы или настройка модуля);
- вопрос вендору: команда в процессе со статусами выполняется от пользователя не из списка «Выполнить
  команду могут», если событие отправлено в обход интерфейса ([[concept-bizproc-state-machine]]).
- Видны ли результаты своих действий (`RETURN`) в роботах.
- Открытые вопросы конспектов курса: [[source-course57-basics]], [[source-course57-templates-designer]],
  [[source-course57-expressions]], [[source-course57-actions-core]],
  [[source-course57-actions-notify-other]], [[source-course57-actions-crm-disk]],
  [[source-course57-developer]] — раздел «Открытые вопросы» в каждом.

**Не сделано и почему:**
- Файлы-примеры: 11 шаблонов `.bpt` скачаны 2026-09-22 с разрешения пользователя и лежат в
  `raw/sources/2026-09-22-course57-bizproc-files/`; архивы уроков 2903 и 7771 не скачались. Разбор — в
  [[source-course57-examples]].
- Каталог `tools/bpt` не знает 20 типов действий из этих примеров («Запись в отчет», «Пауза в
  выполнении», «Установка прав», «Выбор сотрудника», «Итератор», действия Диска, статусы) — P3,
  пополнять по мере появления клиентских экспортов с ними.
- `tools/bpt` не собирает процессы со статусами (`StateMachineWorkflowActivity`) — отдельная задача.

## Под-источники из awesome-bitrix (категория → раздел вики)
- Учебные курсы → [playbooks](../cross-cutting/playbooks/_index-playbooks.md) / development
- Документация (dev-доки, JS, D7; REST — через MCP) → [rest-integrations](../modules/rest-integrations/_index-rest-integrations.md), development
- Инструменты (BitrixVM, server-test, Docker, сниппеты) → [server-admin](../development/server-admin/_index-server-admin.md), [performance](../development/performance/_index-performance.md)
- Библиотеки PHP/JS (DI, Webpack, Vue) → [core-d7](../development/core-d7/_index-core-d7.md), templates-design
- Дистрибутивы → [administration](../modules/administration/_index-administration.md)
- Блоги / Статьи (Intervolga, Prominado, D7, MySQL, интеграция с 1С) → по теме статьи в соответствующий модуль
- GitHub-организации (модули, компоненты, SDK) → [modules-custom](../development/modules-custom/_index-modules-custom.md), [rest-integrations](../modules/rest-integrations/_index-rest-integrations.md)
- Видеодоклады / Конференции / Другое (KB, FAQ, форумы, Telegram) → завести как точечные под-источники по мере надобности

[← Конспекты источников](_index-sources.md)
