---
title: "API задач в коробке: поколения и командный V2"
type: concept
module: tasks-projects
edition: box
status: verified
provenance: mixed
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Модуль Задачи — О модуле, Поиск, Основные команды и справочные разделы по командам (tasks 26.0.0); без проверки на стенде"
tags: [задачи, tasks, v2, cqrs, провайдер, команды, legacy, права]
sources: ["[[source-devbook-tasks]]"]
related: ["[[recipe-tasks-v2-commands]]", "[[concept-crm-universal-api]]", "[[entity-main-result]]", "[[pattern-tasks-effectiveness-from-db]]", "[[concept-bitrix-naming-conventions]]", "[[entity-loader]]"]
aliases: []
updated: "2026-09-21"
---

# API задач в коробке: поколения и командный V2

**TL;DR:** в модуле `tasks` одновременно живут три поколения API. Книга описывает модуль версии
**26.0.0** (многие классы есть уже в 25-й) и называет главным публичным API **команды V2**: собрать
объект команды → вызвать `run()` → получить `Result`. Читать задачи предлагается провайдерами:
совместимым со старым кодом `Provider\TaskProvider`, текущим низкоуровневым `Provider\TaskList` и
экспериментальным V2 `TaskProvider`. `CTasks::getList` книга считает устаревшим.

## Три поколения

| Поколение | Классы | Вход → выход | Статус по книге |
|---|---|---|---|
| Старое | `CTasks::getList` | в главе не разбирается | устарел |
| Переходное: фасад | `Bitrix\Tasks\Provider\TaskProvider` | массивы `$arOrder`, `$arFilter`, `$arSelect`, `$arParams`, `$arGroup` → `CDBResult` (и у `getList`, и у `getCount`) | замена legacy с обратной совместимостью; надстройка над `TaskList` |
| Переходное: низкоуровневое | `Bitrix\Tasks\Provider\TaskList` + `Provider\Query\TaskQuery` | `TaskQuery` → `array` (`getList`) или `int` (`getCount`) | текущий провайдер |
| V2: чтение | `Bitrix\Tasks\V2\Public\Provider\TaskProvider` | `TaskParams` → `?Task`; `TaskListParams` → `TaskCollection` или `int` | экспериментальный |
| V2: запись | `Bitrix\Tasks\V2\Public\Command\…` | сущность и конфиг → `run()` → `Bitrix\Tasks\V2\Internal\Result\Result` | главный публичный API |

Источник: [Поиск задач](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Poisk.html#poisk-zadac),
[Архитектура модуля](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/O_module.html#arhitektura-modula).
Как менять задачи старыми классами, глава не описывает.

**Ловушка одинаковых имён.** `TaskProvider` — это два разных класса: `Bitrix\Tasks\Provider\TaskProvider`
(массивы) и `Bitrix\Tasks\V2\Public\Provider\TaskProvider` (доменные сущности). Ошибка в `use` меняет
весь контракт. Похожая путаница с «внутренними» классами: старое пространство
`Bitrix\Tasks\Internals\…` (в вики — `Internals\Effective` из
[[pattern-tasks-effectiveness-from-db|расчёта эффективности]], в книге — агент
`Internals\Counter\Agent` из [раздела об агентах](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Agenty.html#primenenie))
и новое `Bitrix\Tasks\V2\Internal\…`. Судя по именам, это разные поколения (вывод команды).

## Как устроен V2

Модуль прошёл путь от статических методов и ORM к схеме **CQRS**: основной механизм изменения
состояния — команды, чтение идёт через провайдеры.

```
Слой представления: REST-контроллеры (Infrastructure/Rest/Controller/*),
                    контроллеры (Infrastructure/Controller/*), ваш код
        │  собирает команду и вызывает run()
        ▼
Команды: Public/Command/*                  ← публичный API
        │  передаются обработчикам
        ▼
Бизнес-логика: Internal/Service/*, Internal/Repository/*, Internal/Entity/*
```

Книга перечисляет плюсы подхода: стабильный публичный интерфейс, строгая валидация входа, одна точка
входа (команда или провайдер) и единая обработка ошибок через результат. Плата — привычное «одно
действие» распадается на несколько команд, и каждую надо сконфигурировать вручную
([Сравнение версий](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/O_module.html#sravnenie-versij)).

### Из чего собирается вызов

| Часть | Где лежит | Примеры |
|---|---|---|
| Доменная сущность | `Bitrix\Tasks\V2\Internal\Entity\…` | `Task`, `User`, `Task\Group`, `Task\Priority`, `Task\Reminder`, `Task\Gantt\LinkType` |
| Конфиг действия | `Bitrix\Tasks\V2\Internal\Service\Task\Action\<Действие>\Config\…` | `AddConfig`, `UpdateConfig`, `CopyConfig`, `DeleteConfig` |
| Команда | `Bitrix\Tasks\V2\Public\Command\…` | `Task\AddTaskCommand`, `CheckList\SaveCheckListCommand`, `Gantt\AddDependenceCommand` |

Простым командам (избранное, канбан, родительская и связанные задачи) сущность и конфиг не нужны —
хватает ID задачи и пользователя. Сущности и конфиги лежат в `Internal`, хотя стабильным API книга
называет слой команд. Вывод команды: после обновления модуля сверять сигнатуры и этих классов, а не
только команд.

Канонический сценарий — создать задачу:

```php
use Bitrix\Main\Loader;
use Bitrix\Tasks\V2\Internal\Entity\Task;
use Bitrix\Tasks\V2\Internal\Entity\User;
use Bitrix\Tasks\V2\Internal\Service\Task\Action\Add\Config\AddConfig;
use Bitrix\Tasks\V2\Public\Command\Task\AddTaskCommand;

Loader::requireModule('tasks');            // модуль подключаем явно

$task = new Task(
    title: 'Сверить акт с контрагентом',   // обязательно
    creator: new User(id: 128),            // обязательно
    responsible: new User(id: 128),
);
$result = (new AddTaskCommand($task, new AddConfig(userId: 128)))->run();

$taskId = $result->isSuccess() ? $result->getId() : null;   // иначе getErrorMessages() — в лог
```

Типовые операции и ловушки остальных команд — [[recipe-tasks-v2-commands|Команды задач V2]].

### Модель задачи `Entity\Task`

Команды работают не с массивами, а с объектами
([Объект задачи](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Osnovnye_komandy.html#ob-ekt-zadaci)):

- при создании обязательны `title` и `creator`, `id` не передаётся;
- люди: `creator`, `responsible` (`User`), `accomplices`, `auditors` (`UserCollection`);
- сроки — Unix timestamp: `deadlineTs`, `startPlanTs`, `endPlanTs`;
- связи: `parentId`, `group`, `stage`, `flow`, `epicId`;
- прочее: `description`, `priority`, `status`, `checklist`, `fileIds`, `tags`, `reminders`, `chatId`,
  `source`, `storyPoints`.

Где книга расходится сама с собой:
- `flow` в таблице полей описан как бизнес-процесс задачи, а в параметрах провайдера доступ к нему
  проверяется как доступ к потоку. Что это за сущность, проверить на стенде; с модулем
  бизнес-процессов не отождествлять.
- `priority`: в таблице значения `low`, `medium`, `high`, в коде — `Priority::High`, а команды есть
  только `SetHighTaskPriorityCommand` и `SetAverageTaskPriorityCommand`. Точные значения enum —
  проверить на стенде.
- Поля сущности в примерах читаются то свойством (`$task->id`), то геттером (`$task->getId()`).
  Работают ли оба способа — проверить на стенде.

`status` в примере книги читается через `getStatus()?->value` (в таблице — значения вроде `new`,
`inprogress`, `completed`), а в БД статус числовой (`b_tasks.STATUS`, см.
[[pattern-tasks-effectiveness-from-db]]). Соответствия книга не даёт — слои не смешивать (вывод
команды).

### Результат команды

`Bitrix\Tasks\V2\Internal\Result\Result` наследует `Bitrix\Main\Result` ([[entity-main-result]]):
`isSuccess()`, `getErrors()` и `getErrorMessages()` на месте, плюс
([Обработка результата](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Osnovnye_komandy.html#obrabotka-rezul-tata)):

| Метод | Что даёт |
|---|---|
| `getId()` | ID созданной или изменённой сущности (`int`, `string` или `null`) |
| `getObject()` | сущность; у `AddTaskCommand` — созданная `Entity\Task` |
| `getCollection()` | коллекцию сущностей |
| `getDataByKey($key)` | значение из data-массива результата |

Коды ошибок в книге (`TASK_NOT_FOUND`, `ACCESS_DENIED`) приведены как примеры вывода, полного перечня
нет; собственная таблица кодов есть только у канбана. Вывод команды: ветвить логику по кодам — после
проверки на стенде, человеку показывать `getErrorMessages()`.

## Чтение: три провайдера

### `Provider\TaskProvider` — перенос старого кода

Сигнатура повторяет старый массивный стиль, результат — `CDBResult`; даже `getCount()` отдаёт
`CDBResult`, а число лежит в поле `CNT`. Поведение задаётся `$arParams`: `nPageTop`, `USER_ID` и
`TARGET_USER_ID`, `NAV_PARAMS` (одна из трёх схем постраничности), `MAKE_ACCESS_FILTER`,
`CHECK_PERMISSIONS`, `FILTER_PARAMS` с ключом `SEARCH_TASK_ONLY`
([TaskProvider](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Poisk.html#taskprovider)).

**Ловушка прав:** `CHECK_PERMISSIONS` читается и из `$arParams`, и из `$arFilter`. `'N'` хотя бы в
одном из массивов — и проверки прав нет вовсе.

### `Provider\TaskList` + `TaskQuery` — гибкий низкоуровневый

`new TaskQuery(userId: …)`, затем `setSelect()`, `setWhere()` (ключи фильтра — как у массивного
`$arFilter`, например `'%TITLE'`) и при необходимости `skipAccessCheck()`. `getList()` отдаёт массив,
`getCount()` — `int`. Раз `TaskProvider` — надстройка над `TaskList`, ради гибкости и небольшого
выигрыша в скорости книга советует сразу брать `TaskList`
([TaskList](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Poisk.html#tasklist)).

### V2 `TaskProvider` — доменные сущности

- `get(TaskParams)` по умолчанию **грузит всё связанное** (группа, поток, стадия, участники,
  чек-листы, теги, CRM, подзадачи, Гант, пользовательские поля и т. д.) и **включает все пять проверок
  доступа**: `checkTaskAccess`, `checkGroupAccess`, `checkFlowAccess`, `checkCrmAccess`,
  `checkParentAccess`. Ненужное выключается явно; `userId: 0` допустим для системных действий
  ([TaskProvider::get](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Poisk.html#taskprovider-get)).
- **Противоречие книги:** свойства `TaskParams` объявлены `public readonly`, а короткий пример
  присваивает `checkTaskAccess = false` уже после `TaskParams::mapFromArray()`. PHP не даёт менять
  инициализированное readonly-свойство (бросает `Error`), значит, неточна либо сигнатура, либо пример.
  Надёжно — передать флаги в конструктор; принимает ли их массив `mapFromArray()`, проверить на стенде.
- `getList(TaskListParams)` и `getCount()`: `userId`, **обязательная** пагинация `Pager`, фильтр
  `TaskListArrayFilter` (массив с ключом `logic` и условиями-тройками «поле, оператор, значение»),
  `TaskListSort`, `TaskListSelect` и `skipAccessCheck` (по умолчанию `false` — права проверяются).
  Имена полей — в camelCase (`title`, `createdDate`), перечень — в `…\Params\TaskList\FieldsEnum`
  ([TaskProvider::getList](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Poisk.html#taskprovider-getlist)).

```php
use Bitrix\Main\Provider\Params\Pager;
use Bitrix\Main\Type\DateTime;
use Bitrix\Tasks\V2\Public\Provider\Params\TaskList\TaskListArrayFilter;
use Bitrix\Tasks\V2\Public\Provider\Params\TaskList\TaskListParams;
use Bitrix\Tasks\V2\Public\Provider\Params\TaskList\TaskListSelect;
use Bitrix\Tasks\V2\Public\Provider\Params\TaskList\TaskListSort;
use Bitrix\Tasks\V2\Public\Provider\Params\TaskParams;
use Bitrix\Tasks\V2\Public\Provider\TaskProvider;

$provider = new TaskProvider();

// Одна задача: только поля и участники; права — пользователя 128
$task = $provider->get(new TaskParams(
    taskId: 1030,
    userId: 128,
    group: false, flow: false, stage: false, checkLists: false, tags: false, crm: false,
    email: false, subTasks: false, relatedTasks: false, gantt: false, placements: false,
    containsCommentFiles: false, favorite: false, options: false, parameters: false,
    results: false, reminders: false, userFields: false, scenarios: false,
));

// Список: названия на «Договор», созданные с 1 сентября 2026, новые сверху, первые 50
$tasks = $provider->getList(new TaskListParams(
    userId: 128,
    pagination: new Pager(limit: 50, offset: 0),
    filter: new TaskListArrayFilter([
        'logic' => 'and',
        ['title', 'like', 'Договор%'],
        ['createdDate', '>=', DateTime::createFromTimestamp(mktime(0, 0, 0, 9, 1, 2026))],
    ]),
    sort: new TaskListSort(['createdDate' => 'desc']),
    select: new TaskListSelect(['id', 'title']),
));
```

### Что выбрать (вывод команды)

| Ситуация | Выбор |
|---|---|
| Перенос кода на `CTasks::getList` | `Provider\TaskProvider`: та же форма массивов, минимум правок |
| Новый код, нужны массивы и гибкие выборки | `TaskList` + `TaskQuery` |
| Новый код рядом с командами, нужны сущности | V2 `TaskProvider`, помня про экспериментальный статус: версию модуля фиксировать, после обновлений перепроверять |
| Любое изменение задачи | команды V2 — [[recipe-tasks-v2-commands]] |

## Почему важно при внедрении

- **Три разных выключателя прав:** `CHECK_PERMISSIONS = 'N'`, `TaskQuery::skipAccessCheck()`,
  `check*Access: false` или `skipAccessCheck: true` в V2. Уместны только в технических сценариях
  (агент, миграция, отчёт); код «от имени сотрудника» передаёт его `userId` и оставляет проверки
  (вывод команды).
- **Пользователь передаётся явно.** Провайдеры и большинство команд получают `userId` параметром или
  в конфиге, поэтому агент или CLI-скрипт сам решает, от чьего имени действует. Исключения по книге:
  команды канбана, изменение и удаление связей Ганта и напоминание (там `userId` — поле `Reminder`,
  по умолчанию текущий пользователь).
- **Версия модуля.** Книга описывает 26.0.0, многое есть с 25-й, чат задачи — с 25.500, V2-провайдер
  экспериментальный. Код, который должен пережить разные версии, страхуем проверкой
  `class_exists()` нужного класса (вывод команды).
- **Аналогия с [[concept-crm-universal-api|Universal API CRM]]** (вывод команды): `run()` у команды
  задач соответствует `Operation::launch()` в CRM, результат в обоих случаях из семейства
  `Main\Result`, а поведение настраивается до запуска — у задач объектом конфига (`skipBP`,
  `skipNotifications`, …), в CRM методами `enable…`/`disable…`. Разница: для CRM описана точка
  вмешательства в процесс (`Operation\Action`), для команд задач глава её не даёт — событий модуля в
  ней нет, на свои обработчики намекает только параметр `byPassParameters`.
- **«Модулем или нет?»** Код, вызывающий команды, — доработка коробки: сначала решаем, «решение» это в
  `/local/php_interface` или модуль ([[pattern-local-solution-structure]],
  [[pattern-module-based-development-standard]]).

## Облако vs коробка

Всё описанное — PHP API коробки. В облаке доступен только REST, а в главе он не описан. По схеме
книги REST-контроллеры модуля — слой представления над теми же командами, но практики под
PHP-команды в облако не переносятся: REST-методы задач сверяются отдельно, по документации REST.

## Открытые вопросы
- Смена статуса (начать, завершить, отложить, возобновить): отдельных команд в главе нет; меняется ли
  `status` через `UpdateTaskCommand` — проверить на стенде.
- Шаблоны и повторяющиеся задачи, учёт времени, события модуля, модель прав, REST — в главе не
  описаны.
- Что делает флаг `useConsistency` («консистентность данных») — не раскрыто.
- Принимают ли команды `userId: 0`, как провайдер через `TaskParams`.
- Как в линейку поколений вписывается `Bitrix\Tasks\Item\Task` — класс мелькает в
  [примере книги о валидации](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Validacia/Susestvuusie_pravila.html#elementstype).

## Связанные страницы
- [[recipe-tasks-v2-commands]] — типовые операции командами V2 и их ловушки
- [[source-devbook-tasks]] — конспект главы и перечень её противоречий
- [[concept-crm-universal-api]] — родственная архитектура в CRM
- [[entity-main-result]] — базовый класс результата
- [[entity-loader]] — `Loader::requireModule()` и `includeModule()`
- [[concept-bitrix-naming-conventions]] — как сосуществуют поколения классов

[← Задачи и проекты](_index-tasks-projects.md)
