---
title: "Конспект: курс 57 «Бизнес-процессы» — глава для разработчика"
type: source-summary
module: bizproc
edition: box
status: verified
provenance: documented
verified: "2026-09-22 / dev.1c-bitrix.ru, курс 57, снимок-манифест 2026-09-22; сверка с кодом стенда (коробка, bizproc 26.1075.0) и apidocs.bitrix24.ru через MCP"
tags: [bizproc, курс-57, разработка, свои-действия, rest, автозапуск, php-код, движок]
sources: []
related: ["[[concept-bizproc-engine]]", "[[entity-bizproc-activity-description]]", "[[entity-cbp-activity]]", "[[entity-cbp-task-service]]", "[[recipe-bizproc-custom-task-activity]]", "[[antipattern-bizproc-php-code-activity]]", "[[entity-bizproc-template-rest-methods]]", "[[entity-robots-triggers]]", "[[source-devbook-bizproc]]"]
aliases: []
updated: "2026-09-22"
---

# Конспект: курс 57 — для разработчика

## Об источнике
| Поле | Значение |
|------|----------|
| Тип | официальный учебный курс 1С-Битрикс «Бизнес-процессы» — эталонный источник (`CLAUDE.md` §9) |
| Издатель | 1С-Битрикс, dev.1c-bitrix.ru |
| Главы и уроки | «Бизнес-процессы для разработчика»: 23030, [13378 «Рекомендации по разработке»](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=13378), [3465](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=3465), [2130](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=2130); «Действия»: 23032, [3472](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=3472), [3471](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=3471), [3470](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=3470); «Создание пользовательских действий»: [23034](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=23034), 2903, 2904; [5815](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=5815); «REST»: [23036](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=23036), 7771; «Произвольный PHP код»: [23038](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=23038), 2905, 2906, 2907, 2908, 2172, 15308, 1898; [20686 «Автоматический запуск…»](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=20686); тест 21576 — 24 урока |
| Дата | уроки изменены с 2021-09-02 по 2026-03-31; написаны в основном в 2011–2013 годах |
| Где лежит | снимок-манифест без текста: [`raw/sources/2026-09-22-course57-bizproc-manifest.md`](../../raw/sources/2026-09-22-course57-bizproc-manifest.md) |

## TL;DR
Теория движка: шаблон — массив действий, экземпляр живёт отдельно от шаблона и при ожидании
сохраняется в базе, всё в процессе — действия. Дальше свои действия на PHP, выполнение задания из кода,
действия приложений по REST, правила для «PHP кода» и вызовы автозапуска CRM из своего кода. Курс
старый: свои действия кладёт только в `/bitrix/activities/custom/`, о `/local` и роботах молчит, часть
примеров не работает на текущем ядре и PHP 8. Урок 13378 — действующие правила; он уже был процитирован
в вики.

## Карта уроков
| Урок | О чём | Куда встроено |
|------|-------|---------------|
| 3465, 2130, 23030 | шаблон как массив, экземпляр, сохранение в базе | [[concept-bizproc-engine]] |
| 3472, 3471, 3470, 23032 | базовые классы, свойства действий, составные действия | [[entity-cbp-activity]] |
| 23034, 2903, 2904 | алгоритм своего действия, `.description.php`, примеры | [[entity-bizproc-activity-description]] |
| 5815 | выполнение задания за пользователя из PHP | [[entity-cbp-task-service]] |
| 23036, 7771 | действия приложений по REST | [[entity-bizproc-template-rest-methods]] |
| 13378, 23038, 2905–2908, 2172, 15308, 1898 | правила и примеры «PHP кода» | [[antipattern-bizproc-php-code-activity]] |
| 20686 | автозапуск БП и роботов CRM из своего кода | [[entity-robots-triggers]] |

## Ключевые тезисы
- **Шаблон** — список элементов `Type` (класс без `CBP`), `Name`, `Properties`, `Children`; корень —
  `SequentialWorkflowActivity` или `StateMachineWorkflowActivity`. Это тот же массив, что лежит в
  `TEMPLATE` файла `.bpt`.
- **Экземпляр** создаётся `CBPRuntime::GetRuntime()->CreateWorkflow(...)` и `->Start()`; ожидающий
  процесс сохраняется в базе и выгружается из памяти; после завершения остаётся только статус. Одна
  копия одновременно — иначе «заблокирован другим процессом».
- **Свойства действия** объявляются в конструкторе (`arProperties`), параметры процесса — свойства
  корневого действия; ссылка на свойство выполненного раньше действия — `{=ИмяДействия:Свойство}`.
- **Своё действие (23034).** Папка — имя класса без `CBP` строчными; `.description.php` с `NAME`,
  `DESCRIPTION`, `TYPE` (`activity` или `condition`), `CLASS`, `JSCLASS`, `CATEGORY`; `ADDITIONAL_RESULT`
  — с bizproc 17.0.3; методы `Execute()`, `GetPropertiesDialog()`, `GetPropertiesDialogValues()`,
  `ValidateProperties()`. Каталог по курсу — `/bitrix/activities/custom/`.
- **Задание из PHP (5815).** Внешнее событие действию с `USER_ID` и `APPROVE`. REST-аналог —
  `bizproc.task.complete`.
- **REST (23036).** Разрешение «Бизнес-процессы» даёт `bizproc.activity.add/delete/list/log` и
  `bizproc.event.send`; действие появляется в разделе «Действия приложений»; обработчик получает
  `workflow_id`, `event_token`, свойства; ответ — `bizproc.event.send`. По курсу действия приложения
  удаляются при его удалении и обновлении.
- **Правила «PHP кода» (13378, 23038).** Модули подключать самим; не использовать `{=…}` в тексте кода,
  `$USER` и `$GLOBALS`; не повышать права через `$USER->Authorize()`; внешние данные в SQL фильтровать;
  у роботов и триггеров нет пользователя-инициатора. В облаке своя логика — только через свои действия
  по REST.
- **Автозапуск в CRM (20686).** Это обязанность модуля CRM: своя логика после создания или изменения
  сущности вызывает `\CCrmBizProcHelper::AutoStartWorkflows(...)`, для роботов — `\Bitrix\Crm\Automation\
  Starter` с `runOnAdd()` / `runOnUpdate()`.

## Что встроено в вики
- [[concept-bizproc-engine]] — экземпляр, порядок поиска действий подтверждён кодом.
- [[entity-bizproc-activity-description]] — ключи по курсу, `ADDITIONAL_RESULT` как свойство-карта,
  опасность одноимённых папок (примеры курса `logactivity`, `task2activity`).
- [[entity-cbp-activity]], [[entity-cbp-task-service]], [[recipe-bizproc-custom-task-activity]] —
  базовые классы штатных заданий, внешнее событие из PHP.
- [[antipattern-bizproc-php-code-activity]] — пункты 13378, которых не было: `Authorize()`, SQL,
  пользователь у роботов.
- [[entity-bizproc-template-rest-methods]] — действия приложений, `bizproc.task.complete`.
- [[entity-robots-triggers]] — автозапуск из кода и в `Service\Operation`.

## Сверка 2026-09-22
| Страница вики | Что было | Что в курсе и ядре | Решение |
|---------------|----------|--------------------|---------|
| [[entity-bizproc-activity-description]], [[concept-bizproc-engine]] | «одноимённый каталог перекрывает штатное действие» — вывод команды, на стенде не проверялось | курс молчит; ядро: `ActivitySearcher\Searcher` берёт первое найденное | подтверждено кодом ядра |
| [[recipe-bizproc-custom-task-activity]], [[entity-cbp-task-service]] | спор: задание на `CBPActivity` (команда) или `CBPCompositeActivity` (книга) | курс не решает; ядро: `CBPReviewActivity` — `CBPActivity`, `CBPApproveActivity` и `CBPRequestInformationActivity` — `CBPCompositeActivity` | **спор снят**: задание без веток штатно на `CBPActivity`, с ветками — на `CBPCompositeActivity` |
| [[entity-bizproc-activity-description]] | `RETURN` / `ADDITIONAL_RESULT` — книга противоречит себе | курс: `ADDITIONAL_RESULT` с 17.0.3; ядро: `ADDITIONAL_RESULT` перечисляет свойства-карты (`EntityFields`, `DynamicEntityFields`) | снято: постоянные результаты — `RETURN`, динамические — свойство-карта в `ADDITIONAL_RESULT` |
| [[entity-bizproc-activity-description]] | свои действия — в `/local/activities/custom/` | курс: `/bitrix/activities/custom/` | оставляем `/local`: ядро ищет его раньше, курс устарел |

## Ошибки в примерах курса — не копировать
- 5815: `CBPDocument::SendExternalEvent` вызван с тремя аргументами, а в текущем ядре четвёртый
  (`&$arErrors`) обязателен. `CBPRuntime::SendExternalEvent` принимает три.
- 3470: неверный интерфейс и `protected OnEvent`; 2904: `count()` от строки (ошибка в PHP 8).
- 2903: путь к файлу лога задаёт тот, кто правит шаблон, — так можно записать код в файл сайта.
- 2906, 2904: папки `logactivity` и `task2activity` совпадают со штатными «Запись в отчет» и «Поставить
  задачу» — своя папка подменит штатное действие во всех шаблонах.
- 2172, 2905: `{=…}` прямо в PHP-коде — вопреки правилам 13378.
- 23036: обработчик пишет в файл весь запрос вместе с токеном.

## Открытые вопросы
- Удаляются ли действия приложения при его обновлении сейчас (есть `bizproc.activity.update`).
- Модуль в ID документа инфоблока: `bizproc` или `iblock` — в уроках по-разному.

[← Конспекты источников](_index-sources.md)
