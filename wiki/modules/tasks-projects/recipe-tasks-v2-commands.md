---
title: "Команды задач V2: типовые операции и ловушки"
type: recipe
module: tasks-projects
edition: box
status: draft
provenance: mixed
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Модуль Задачи — Основные команды, Участники задач, Чек-листы, Работа с файлами, Связи и зависимости, Гант, Канбан, Отображение и поведение, Другие, Чат (tasks 26.0.0); примеры не прогонялись, без проверки на стенде"
tags: [задачи, tasks, v2, команды, участники, чек-лист, канбан, гант, файлы]
sources: ["[[source-devbook-tasks]]"]
related: ["[[concept-tasks-api-v2]]", "[[entity-main-result]]", "[[checklist-tasks-regulations]]", "[[pattern-module-based-development-standard]]", "[[entity-admin-php-console]]", "[[entity-loader]]"]
aliases: []
updated: "2026-09-21"
---

# Команды задач V2: типовые операции и ловушки

> **Черновик.** Код собран по сигнатурам из книги (модуль `tasks` 26.0.0) и на стенде не
> прогонялся; книга местами противоречит сама себе — такие места помечены «проверить на стенде».
> `draft` снимаем после прогона примеров на тест-стенде.

**Результат:** типовые изменения задач в коробке — создание, правка, срок, участники, чек-лист,
файлы, иерархия и Гант, канбан, копия, удаление, напоминание, чат — через команды
`Bitrix\Tasks\V2\Public\Command\…`, без ловушек, на которых спотыкаются примеры книги.

## Предусловия
- Коробка, модуль `tasks` 25+ (книга описывает 26.0.0; чат задачи — с 25.500).
- Решено, где живёт код: «решение» в `/local/php_interface` или модуль —
  [[pattern-local-solution-structure|структура решения]],
  [[pattern-module-based-development-standard|модулем или нет]].
- Понятна схема «сущность + конфиг → команда → `run()` → `Result`» —
  [[concept-tasks-api-v2|API задач: поколения и V2]].
- Тест-стенд: команды меняют данные, пишут в чат и рассылают уведомления.

## Где лежат команды

Корень — `Bitrix\Tasks\V2\Public\Command\`. Примечания в разделах книги называют общий корень,
точное подпространство видно по `use` в примерах — они сведены в таблицу:

| Подпространство | Команды |
|---|---|
| `Task\` | `AddTaskCommand`, `UpdateTaskCommand`, `DeleteTaskCommand` |
| `Task\Copy\` · `Task\Deadline\` | `CopyTaskCommand` · `UpdateDeadlineCommand` |
| `Task\Stakeholder\` | `DelegateCommand`, `SetAccomplicesCommand`, `SetAuditorsCommand`, `AddAuditorsCommand`, `DeleteAuditorsCommand`, `UpdateCreatorCommand` |
| `Task\Audit\` | `WatchTaskCommand`, `UnwatchTaskCommand` |
| `CheckList\` | `SaveCheckListCommand`, `CompleteCheckListItemsCommand`, `RenewCheckListItemsCommand`, `ExpandCheckListCommand`, `CollapseCheckListCommand` |
| `Task\Attachment\` | `AttachFilesCommand`, `DetachFilesCommand` |
| `Task\Relation\` | `SetParentRelationCommand`, `DeleteParentRelationCommand`, `AddRelatedTaskCommand`, `DeleteRelatedTaskCommand`, `AddMultiTaskChildrenCommand` |
| `Gantt\` | `AddDependenceCommand`, `UpdateDependenceCommand`, `DeleteDependenceCommand` |
| `Task\Kanban\` | `MoveTaskCommand`, `AddTaskStageRelationCommand`, `DeleteTaskStageRelationCommand`, `ClearTaskCommand`, `ClearStageCommand` |
| `Task\Favorite\` | `AddFavoriteCommand`, `DeleteFavoriteCommand`, `ToggleFavoriteCommand` |
| `Task\Attention\` | `PinTaskCommand`, `UnpinTaskCommand`, `PinInGroupTaskCommand`, `UnpinInGroupTaskCommand`, `MuteTaskCommand`, `UnmuteTaskCommand`, `SetHighTaskPriorityCommand`, `SetAverageTaskPriorityCommand`, `ViewCommand` |
| `Task\Reminder\` · `Task\Chat\` | `UpdateReminderCommand` · `SendMessageCommand` |

Конфиги — `Bitrix\Tasks\V2\Internal\Service\Task\Action\{Add|Update|Copy|Delete}\Config\…Config`.

## Шаги

В каждом шаге `use` — только для новых классов; импорты предыдущих шагов подразумеваются.

### 0. Обвязка: запуск и ошибки

Команда выполняется вызовом `run()`. В семи примерах книги (избранное, закрепление, высокий
приоритет, просмотр, родительская задача, удаление связанной задачи, напоминание) этот вызов
пропущен, а `$result` не определён — при переносе не повторять.

```php
use Bitrix\Main\Loader;
use Bitrix\Tasks\V2\Internal\Result\Result;

Loader::requireModule('tasks');

// В решении это метод своего сервиса. В пользовательском сценарии вместо исключения
// показываем getErrorMessages()
function runTaskCommand(object $command): Result
{
    $result = $command->run();
    if (!$result->isSuccess()) {
        throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
    }

    return $result;
}
```

### 1. Создать задачу

```php
use Bitrix\Tasks\V2\Internal\Entity\Task;
use Bitrix\Tasks\V2\Internal\Entity\Task\Group;
use Bitrix\Tasks\V2\Internal\Entity\User;
use Bitrix\Tasks\V2\Internal\Service\Task\Action\Add\Config\AddConfig;
use Bitrix\Tasks\V2\Public\Command\Task\AddTaskCommand;

$taskId = (int)runTaskCommand(new AddTaskCommand(
    new Task(
        title: 'Подготовить КП',
        description: 'Вводные — в карточке сделки',
        creator: new User(id: 128),
        responsible: new User(id: 130),
        deadlineTs: strtotime('+3 days 18:00'),
        group: new Group(id: 12),
    ),
    new AddConfig(userId: 128),                // от чьего имени создаём
))->getId();
```

- Обязательны `title` и `creator`; сроки — Unix timestamp.
- Соисполнителей и наблюдателей удобнее назначить командами шага 3: как собрать `UserCollection`
  для полей `accomplices` и `auditors`, книга не показывает.
- Флаги `AddConfig`, которые решаем осознанно: `skipBP` (не запускать бизнес-процессы), `fromAgent`
  (создание из агента), `checkFileRights` — по умолчанию `false`, то есть права на прикладываемые файлы
  не проверяются. Если ID файлов приходят от пользователя — включать (вывод команды).
- Как `deadlineTs` сочетается с часовым поясом пользователя (в конфигах есть `skipTimeZoneFields`),
  книга не объясняет — проверить на стенде.

### 2. Изменить задачу и срок

```php
use Bitrix\Tasks\V2\Internal\Service\Task\Action\Update\Config\UpdateConfig;
use Bitrix\Tasks\V2\Public\Command\Task\Deadline\UpdateDeadlineCommand;
use Bitrix\Tasks\V2\Public\Command\Task\UpdateTaskCommand;

// Только id и то, что меняем
runTaskCommand(new UpdateTaskCommand(
    new Task(id: $taskId, title: 'Подготовить КП и спецификацию'),
    new UpdateConfig(userId: 128),
));

// Срок — отдельной командой, с причиной переноса
runTaskCommand(new UpdateDeadlineCommand(
    taskId: $taskId,
    deadlineTs: strtotime('+5 days 18:00'),
    updateConfig: new UpdateConfig(userId: 128),
    reason: 'Клиент прислал вводные позже',
));
```

- В `UpdateTaskCommand` передаём **только изменяемые поля** — так требует книга.
- `UpdateConfig` собирается конструктором с именованными аргументами (`skipNotifications`,
  `skipComments`, `skipPush`, `skipBP`, `needCorrectDatePlan`, `correctDatePlanDependent`, …) или
  фабрикой `UpdateConfig::createFromArray(userId: …, parameters: […])` с ключами в верхнем регистре
  (`CORRECT_DATE_PLAN`, `AUTO_CLOSE`, `SKIP_NOTIFICATION`, `META::EVENT_GUID`, …). Соответствия ключей
  полям книга не приводит, а у `THROTTLE_MESSAGES` и `FIELDS_FOR_COMMENTS` пары в таблице конструктора
  нет. Вывод команды: ключи похожи на параметры старого API, фабрика — мост для переноса кода; в новом
  коде надёжнее конструктор. Проверить на стенде.
- Массовые правки (миграция, импорт): заранее решить про `skipNotifications`, `skipComments` и
  `skipPush`, иначе участники получат поток уведомлений (вывод команды).

### 3. Участники

```php
use Bitrix\Tasks\V2\Public\Command\Task\Audit\WatchTaskCommand;
use Bitrix\Tasks\V2\Public\Command\Task\Stakeholder\AddAuditorsCommand;
use Bitrix\Tasks\V2\Public\Command\Task\Stakeholder\DelegateCommand;
use Bitrix\Tasks\V2\Public\Command\Task\Stakeholder\SetAccomplicesCommand;
use Bitrix\Tasks\V2\Public\Command\Task\Stakeholder\UpdateCreatorCommand;

$config = new UpdateConfig(userId: 128);

runTaskCommand(new DelegateCommand(taskId: $taskId, responsibleId: 131, config: $config));
runTaskCommand(new AddAuditorsCommand(taskId: $taskId, auditorIds: [140, 141], config: $config));

// Соисполнители: команда ЗАМЕНЯЕТ список — передаём текущих вместе с новым
runTaskCommand(new SetAccomplicesCommand(
    taskId: $taskId,
    accompliceIds: array_values(array_unique([...$currentAccompliceIds, 150])),
    config: $config,
));

// Смена постановщика: нужен ID ТЕКУЩЕГО ответственного
runTaskCommand(new UpdateCreatorCommand(
    taskId: $taskId,
    creatorId: 129,
    responsibleId: 131,
    config: $config,
));

// Наблюдатель по одному: userId — кто действует, auditorId — кого добавляем
runTaskCommand(new WatchTaskCommand(taskId: $taskId, userId: 128, auditorId: 142));
```

| Нужно | Команда | На что смотреть |
|---|---|---|
| Сменить ответственного | `DelegateCommand` | ответственный у задачи один |
| Соисполнители | `SetAccomplicesCommand` | заменяет список; отдельных `Add`/`Delete` для соисполнителей в книге нет |
| Наблюдатели списком | `AddAuditorsCommand`, `DeleteAuditorsCommand`, `SetAuditorsCommand` | `Set…` заменяет список целиком |
| Наблюдатель по одному | `WatchTaskCommand`, `UnwatchTaskCommand` | без `UpdateConfig`; `userId` ≠ `auditorId` |
| Постановщик | `UpdateCreatorCommand` | передать текущего ответственного |

`$currentAccompliceIds` читаем заранее (V2 `TaskProvider::get` с `members: true`); как достать ID из
`UserCollection`, книга не показывает — проверить на стенде.

### 4. Чек-лист

```php
use Bitrix\Tasks\V2\Public\Command\CheckList\CompleteCheckListItemsCommand;
use Bitrix\Tasks\V2\Public\Command\CheckList\SaveCheckListCommand;

// Сохраняется ВСЯ структура: существующие пункты — с id, новые — без
runTaskCommand(new SaveCheckListCommand(
    task: new Task(id: $taskId, checklist: [
        ['id' => 501, 'text' => 'Собрать цены', 'isComplete' => 'Y', 'sortIndex' => 10],
        ['id' => 502, 'text' => 'Согласовать скидку', 'isComplete' => 'N', 'sortIndex' => 20],
        ['text' => 'Отправить КП', 'isComplete' => 'N', 'sortIndex' => 30],
    ]),
    updatedBy: 128,                            // автор изменений
));

runTaskCommand(new CompleteCheckListItemsCommand(ids: [502], userId: 128));
```

- `isComplete` — строка `'Y'` или `'N'`, не `bool`; вложенность — через `parentId` пункта.
- Команда за один вызов добавляет, обновляет и удаляет пункты. Вывод команды: пункт, которого нет в
  массиве, вероятно, будет удалён — сначала прочитать текущий чек-лист; проверить на стенде.
- `RenewCheckListItemsCommand` возобновляет выполненные пункты. `ExpandCheckListCommand` и
  `CollapseCheckListCommand` только разворачивают и сворачивают чек-лист в интерфейсе пользователя;
  что именно означает их `checkListId`, книга не уточняет.

### 5. Файлы

1. Загрузить файл в Диск и получить ID объекта Диска — в главе о задачах это не описано.
2. Прикрепить, передав ID строками в формате `n<ID>`:

```php
use Bitrix\Tasks\V2\Public\Command\Task\Attachment\AttachFilesCommand;

runTaskCommand(new AttachFilesCommand(
    taskId: $taskId,
    userId: 128,
    fileIds: array_map(static fn (int $id): string => 'n' . $id, $diskObjectIds),
));
```

- **Противоречие книги:** `AttachFilesCommand` ждёт строки (`'n10'`), а пример `DetachFilesCommand`
  передаёт числа (`[10, 20]`). Формат для открепления — проверить на стенде. Открепление не удаляет
  файл из Диска.

### 6. Иерархия, связи, Гант

```php
use Bitrix\Tasks\V2\Internal\Entity\Task\Gantt\LinkType;
use Bitrix\Tasks\V2\Internal\Service\Task\Action\Copy\Config\CopyConfig;
use Bitrix\Tasks\V2\Public\Command\Gantt\AddDependenceCommand;
use Bitrix\Tasks\V2\Public\Command\Task\Relation\AddMultiTaskChildrenCommand;
use Bitrix\Tasks\V2\Public\Command\Task\Relation\AddRelatedTaskCommand;
use Bitrix\Tasks\V2\Public\Command\Task\Relation\SetParentRelationCommand;

runTaskCommand(new SetParentRelationCommand(taskId: $childId, userId: 128, parentId: $taskId));
runTaskCommand(new AddRelatedTaskCommand(taskId: $taskId, relatedTaskId: 1044, userId: 128));

// По подзадаче-копии на каждого сотрудника из списка
runTaskCommand(new AddMultiTaskChildrenCommand(
    taskId: $taskId,
    userIds: [130, 131, 132],
    config: new CopyConfig(userId: 128, withCheckLists: true),
));

// Гант: $nextId стартует после завершения $prevId
runTaskCommand(new AddDependenceCommand(
    taskId: $nextId,             // зависимая (последующая)
    dependentId: $prevId,        // ПРЕДШЕСТВУЮЩАЯ — вопреки имени параметра
    linkType: LinkType::FinishStart,
    userId: 128,
));
```

- **Ловушка имён Ганта:** `taskId` — зависимая задача, `dependentId` — предшествующая. Переменные
  называем по смыслу (`$prevId`, `$nextId`), иначе связь встанет задом наперёд.
- `LinkType`: `StartStart`, `StartFinish`, `FinishStart`, `FinishFinish`.
- `UpdateDependenceCommand` (`taskId`, `dependentId`, `linkType`) и `DeleteDependenceCommand`
  (`taskId`, `dependentId`) идут без `userId`; от чьего имени проверяются права, книга не говорит.
- Что `AddMultiTaskChildrenCommand` копирует в подзадачи, видимо, задают флаги `with*` в `CopyConfig`
  (вывод команды) — проверить на стенде.

### 7. Канбан

Команды канбана работают со **связью задачи со стадией** (`relationId`), а не с самой задачей, и не
принимают `userId`.

```php
use Bitrix\Tasks\V2\Public\Command\Task\Kanban\AddTaskStageRelationCommand;
use Bitrix\Tasks\V2\Public\Command\Task\Kanban\MoveTaskCommand;

$relationId = (int)runTaskCommand(
    new AddTaskStageRelationCommand(taskId: $taskId, stageId: 21)
)->getId();                                    // ID связи — сохранить

runTaskCommand(new MoveTaskCommand(relationId: $relationId, stageId: 22));
```

- `DeleteTaskStageRelationCommand(relationIds: […])` отвязывает задачи от стадий, и они переходят в
  состояние «не назначено»; `ClearTaskCommand(taskId: …)` снимает все привязки одной задачи.
- `ClearStageCommand(stageId: …)` книга описывает как удаление всех задач из стадии: удаляются ли сами
  задачи или только их привязки — неясно. **Только тест-стенд**; на реальных данных не применять до
  выяснения.
- Как найти `relationId` уже существующей привязки и от чьего имени проверяются права (у
  `ClearStageCommand` среди ошибок есть `ACCESS_DENIED`), книга не говорит.
- Коды ошибок `MoveTaskCommand` по книге: `POSITIVE_NUMBER`, `STAGE_NOT_FOUND`, `TASK_NOT_FOUND`,
  `INVALID_STAGE_TRANSITION`, `BUSINESS_RULE_VIOLATION`.

### 8. Копировать и удалить

```php
use Bitrix\Tasks\V2\Internal\Service\Task\Action\Delete\Config\DeleteConfig;
use Bitrix\Tasks\V2\Public\Command\Task\Copy\CopyTaskCommand;
use Bitrix\Tasks\V2\Public\Command\Task\DeleteTaskCommand;

// Связанное копируется только по явным флагам
$copy = runTaskCommand(new CopyTaskCommand(
    $sourceTask,                               // Entity\Task исходной задачи
    new CopyConfig(
        userId: 128,
        withSubTasks: true,
        withCheckLists: true,
        withAttachments: true,
        withReminders: true,
    ),
))->getObject();

runTaskCommand(new DeleteTaskCommand(taskId: 1030, config: new DeleteConfig(userId: 128)));
```

- Флаги `CopyConfig` — `withSubTasks`, `withCheckLists`, `withAttachments`, `withRelatedTasks`,
  `withReminders`, `withGanttLinks` — по умолчанию `false`, хотя по описанию книги команда копирует
  задачу со всеми параметрами. С `targetTaskId` данные копируются в уже существующую задачу.
- Что передавать как `$sourceTask`, книга однозначно не говорит: в примере объект собран вручную, с
  комментарием, что это задача из базы. Вывод команды: брать задачу V2-провайдером
  ([[concept-tasks-api-v2]]); хватит ли `new Task(id: …)` — проверить на стенде.
- Уходит ли удалённая задача в корзину, книга не говорит — на стенде считать удаление необратимым.
  `DeleteConfig` умеет `skipBP`, `skipExchangeSync` и `byPassParameters` (данные для своих
  обработчиков и интеграций).

### 9. Напоминание, чат, отображение

```php
use Bitrix\Tasks\V2\Internal\Entity\Task\Reminder;
use Bitrix\Tasks\V2\Internal\Entity\Task\Reminder\RemindBy;
use Bitrix\Tasks\V2\Internal\Integration\Im\Entity\Message;
use Bitrix\Tasks\V2\Public\Command\Task\Chat\SendMessageCommand;
use Bitrix\Tasks\V2\Public\Command\Task\Reminder\UpdateReminderCommand;

// Напоминание: только изменение существующего, id обязателен
runTaskCommand(new UpdateReminderCommand(
    new Reminder(id: 77, remindBy: RemindBy::Deadline, before: 2 * 3600),   // за 2 часа до срока
));

// С tasks 25.500 обсуждение идёт в чате задачи
runTaskCommand(new SendMessageCommand(
    taskId: $taskId,
    userId: 128,
    message: new Message(text: 'КП отправлено'),
));
```

- `Reminder`: `remindBy` (`Deadline`, `Date`, `Recurring` с правилом `rrule`), `remindVia`
  (`Notification`, `Email`), `recipient` (`Responsible`, `Creator`, `Accomplice`, `Myself`), `before`
  в секундах, `nextRemindTs`. Команд создания и удаления напоминаний в книге нет; задаются ли
  напоминания полем `reminders` при создании задачи — проверить на стенде.
- У `Message` в книге показан только `text`; как приложить к сообщению файлы, не описано.
- Настройки отображения для пользователя `userId`: избранное (`AddFavoriteCommand`,
  `DeleteFavoriteCommand`, `ToggleFavoriteCommand` — у последнего новое состояние лежит в
  `getData()['isFavorite']`, а флаг `notifyLivefeed` по умолчанию `true`), закрепление в своём списке
  (`PinTaskCommand`, `UnpinTaskCommand`) и в группе (`PinInGroupTaskCommand`,
  `UnpinInGroupTaskCommand` — для себя или для всех участников группы, книга не уточняет),
  отключение уведомлений (`MuteTaskCommand`, `UnmuteTaskCommand`), отметка о просмотре
  (`ViewCommand`).
- Приоритет: есть только `SetHighTaskPriorityCommand` и `SetAverageTaskPriorityCommand`, а в модели
  задачи значения `low`, `medium`, `high` — есть ли «низкий», проверить на стенде.

## Сводка ловушек

| Ловушка | Как правильно | Книга |
|---|---|---|
| Команда создана, но не запущена | всегда `run()` и проверка `isSuccess()` | семь примеров без `run()` |
| Полный объект в `UpdateTaskCommand` | только `id` и изменяемые поля | [Редактирование](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Osnovnye_komandy.html#redaktirovanie-zadaci) |
| Ожидание копии «со всем» | флаги `with*` явно | [Копирование](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Osnovnye_komandy.html#kopirovanie-zadaci) |
| `Set…Command` вместо добавления | `Set…` заменяет список; наблюдателей — `Add`/`DeleteAuditorsCommand` | [Соисполнители](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Ucastniki_zadac.html#setaccomplicescommand) |
| Смена постановщика без ответственного | `responsibleId` — текущий ответственный | [UpdateCreatorCommand](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Ucastniki_zadac.html#updatecreatorcommand) |
| `userId` в `Watch`/`Unwatch` понят как «кого» | `userId` — кто действует, `auditorId` — кого | [Наблюдатели](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Ucastniki_zadac.html#nabludateli-audit) |
| Кусок чек-листа, `true`/`false` | вся структура, `'Y'`/`'N'`, автор — `updatedBy` | [Чек-листы](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Cek_listy.html#struktura-cek-lista) |
| Числовые ID файлов | сначала Диск, затем `'n<ID>'` | [Файлы](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Rabota_s_fajlami.html#attachfilescommand) |
| `taskId` в канбане | `relationId` связи со стадией | [Канбан](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Kanban.html#ponatie-svazi-relation) |
| `dependentId` понят как зависимая | `dependentId` — предшествующая | [Гант](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Gant.html#sozdat-svaz) |
| Правка `TaskParams` после `mapFromArray()` | флаги — в конструктор | [Поиск](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Poisk.html#taskprovider-get) |

## Проверка результата
- `isSuccess()`, затем `getId()` или `getObject()`: у `AddTaskCommand` — созданная задача, у
  `AddTaskStageRelationCommand` — ID связи.
- Перечитать задачу V2-провайдером с нужными флагами (`members`, `checkLists`, `gantt`, `stage`) и
  сверить поля ([[concept-tasks-api-v2]]).
- Открыть карточку под пользователем из `userId`: видна ли задача, какие уведомления пришли, что
  появилось в чате.
- Где прогонять: тест-стенд — [[entity-admin-php-console|Командная PHP-строка]] (веб-запрос от
  текущего пользователя) или CLI-скрипт ([[recipe-cli-script-bootstrap]]).

## Откат и проблемы
- Отмены у команд книга не описывает, а цепочка команд — не транзакция (вывод команды): сценарий
  пишем повторяемым, ID созданных задач и связей логируем, чтобы убрать их со стенда.
- «Класс не найден» — модуль ниже нужной версии или не то подпространство (`Task\Copy\`,
  `Task\Deadline\`, `CheckList\`, `Gantt\` — это не `Task\`).
- `ACCESS_DENIED` — вероятнее всего, не тот `userId` в параметрах или конфиге (вывод команды).
- Коды ошибок из примеров книги — иллюстрации; завязываться на них — после проверки на стенде.

## Источники и связанное
- Книга: [Основные команды](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Osnovnye_komandy.html),
  [Участники задач](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Ucastniki_zadac.html),
  [Чек-листы](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Cek_listy.html),
  [Работа с файлами](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Rabota_s_fajlami.html),
  [Связи и зависимости](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Svazi_i_zavisimosti.html),
  [Гант](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Gant.html),
  [Канбан](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Kanban.html),
  [Отображение и поведение](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Otobrazenie_i_povedenie.html),
  [Другие](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Drugie.html),
  [Чат](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Cat.html).
- [[concept-tasks-api-v2]] — поколения API, провайдеры чтения, `Result`.
- [[source-devbook-tasks]] — конспект главы и её противоречия.
- [[entity-main-result]] — базовый `Main\Result`.
- [[checklist-tasks-regulations]] — регламент постановки задач, который такие скрипты поддерживают
  (один ответственный, срок, чек-лист).

[← Задачи и проекты](_index-tasks-projects.md)
