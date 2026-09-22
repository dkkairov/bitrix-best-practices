---
title: "Каталог действий БП: свойства, вложенность, результаты"
type: concept
module: bizproc
edition: both
status: verified
provenance: mixed
verified: "2026-09-22 / коробка клиента: корпус 16 экспортов из дизайнера БП (VERSION 2); курс 57 dev.1c-bitrix.ru (снимок 2026-09-22): смысл полей и поведение; стенд Docker, bizproc 26.1075.0: ValidateProperties, validateTemplate без записи в базу, прогон процессов"
tags: [бизнес-процессы, действия, активити, каталог, bpt, генерация, роботы]
sources: ["[[source-course57-actions-core]]", "[[source-course57-actions-notify-other]]", "[[source-course57-actions-crm-disk]]"]
related: ["[[concept-bizproc-bpt-format]]", "[[pattern-bizproc-ai-assisted-generation]]", "[[concept-bizproc-engine]]", "[[entity-cbp-activity]]", "[[antipattern-bizproc-hardcoded-portal-ids]]", "[[entity-cbp-task-service]]", "[[concept-bizproc-expressions]]", "[[recipe-bizproc-approval-route]]", "[[recipe-bizproc-request-intake]]", "[[checklist-bizproc-template-review]]"]
aliases: []
updated: "2026-09-22"
---

# Каталог действий БП: свойства, вложенность, результаты

**TL;DR:** справочник 25 типов действий, которые встречаются в реальных шаблонах: какие у них
свойства, что обязательно, какие значения по умолчанию и допустимые варианты, что можно вкладывать,
что действие возвращает для ссылок `{=A…:Результат}` и как ведёт себя — например, что будет по
истечении срока задания. Состав свойств собран по корпусу экспортов и проверяется на нём тестом; смысл
значений и поведение сверены с официальным курсом 57 и кодом ядра. Источник истины —
[`tools/bpt/catalog/activities.php`](../../../tools/bpt/catalog/activities.php); таблицы ниже — его
печать (`php tools/bpt/bpt.php catalog` и `… catalog --notes`).

> **Изменено 2026-09-22 (курс 57 и ядро).** Добавлены варианты значений с расшифровкой, поведение по
> истечении срока, полные списки результатов; заголовки заданий подтверждены. По проверке импорта на
> стенде исправлены: у «Отправить сообщение сотруднику в чат» отправитель обязателен, у задачи
> обязателен постановщик `CREATED_BY`, у «Изменить наблюдателей» есть режим `replace`, «Пауза робота»
> принимает время или период. Смысл `MessageType` и связки условий больше не догадки.
>
> **Изменено 2026-09-21.** Корпус раньше описывался как «шаблоны роботов смарт-процессов в облаке».
> Команда уточнила: это экспорты из дизайнера БП с коробки клиента.

## Зачем он нужен
- **Сборка из спецификации.** Сборщик берёт из каталога значения по умолчанию и отвергает
  неизвестные действия и свойства — так модель не может «придумать» свойство
  ([[pattern-bizproc-ai-assisted-generation|AI-генерация БП]]). Значение вне вариантов дизайнера —
  предупреждение, вне проверяемых ядром значений — ошибка.
- **Ревью чужих шаблонов.** Видно, какое свойство отличается от обычного значения, что возвращает
  задание и что из этого берут другие шаги, чем закончится задание без исполнителя.
- **Перенос между порталами.** Свойства с типом `portal-id` (`TargetStatus`, `TemplateId`,
  `DynamicTypeId`, `ParentTypeId`) — это идентификаторы портала
  ([[antipattern-bizproc-hardcoded-portal-ids|Зашитые ID портала]]).

## Как читать таблицу
- **Алиас** — короткое имя действия в спецификации (`change_stage`); прочерк — служебный узел,
  который создаёт сборщик.
- **Свойства:** `!` — обязательное, `=значение` — значение по умолчанию, без отметки — необязательное
  без значения по умолчанию. Обязательность сверена с проверкой импорта на стенде.
- **Возвращает** — результаты, которые в корпусе брали по ссылкам. Остальные результаты, известные по
  курсу и ядру (`TaskId`, `IsTimeout`, `ErrorMessage`…), показывает `bpt.php catalog <алиас>`. У
  «получить информацию» список задаётся свойством самого действия.
- Заголовок по умолчанию — как `NAME` в `.description.php`: его ставит дизайнер при добавлении
  действия (`CreateActivity` в `js/bizproc/bizproc.js`).

## Формы вложенности

| Форма | Что внутри | Действия |
|-------|-----------|----------|
| `leaf` | ничего | изменение полей, смена стадии, уведомление, задача, пауза |
| `waiting` | ничего, но процесс ждёт людей | ознакомление, запрос информации |
| `waiting-branches` | две последовательности: «да» и «нет» | утверждение, запрос информации с отклонением |
| `ifelse` | ветки с условиями, шаги в ветке — без обёртки | условие |
| `parallel` | последовательности, минимум две | параллельное выполнение |
| `loop` | условие в свойствах и одна последовательность | цикл |
| `block` | одна последовательность | блок действий |
| `root`, `sequence`, `branch` | служебные узлы | корень, последовательность, ветка условия |

## Каталог

| Тип | Алиас | Вложенность | Свойства | Возвращает | В корпусе |
|-----|-------|-------------|----------|------------|-----------|
| `SequentialWorkflowActivity`<br>Последовательный бизнес-процесс | — | root | `Permission`=[] | — | 16 |
| `SequenceActivity`<br>Последовательность действий | — | sequence | — | — | 125 |
| `IfElseBranchActivity`<br>Ветка | — | branch | `fieldcondition`, `propertyvariablecondition`, `mixedcondition`, `truecondition` | — | 264 |
| `IfElseActivity`<br>Условие | `if` | ifelse | — | — | 113 |
| `ParallelActivity`<br>Параллельное выполнение | `parallel` | parallel | — | — | 21 |
| `WhileActivity`<br>Цикл | `while` | loop | `fieldcondition`, `propertyvariablecondition`, `mixedcondition`, `truecondition` | — | 11 |
| `EmptyBlockActivity`<br>Блок действий | `block` | block | — | — | 28 |
| `SetFieldActivity`<br>Изменение документа | `set_field` | leaf | `FieldValue`!, `ModifiedBy`=[], `MergeMultipleFields`=N | — | 210 |
| `SetVariableActivity`<br>Изменение переменных | `set_var` | leaf | `VariableValue`! | — | 62 |
| `CrmChangeStatusActivity`<br>Сменить стадию | `change_stage` | leaf | `TargetStatus`!, `ModifiedBy`=[] | — | 43 |
| `CrmSetObserverField`<br>Изменить наблюдателей | `observers` | leaf | `ActionOnObservers`=add, `Observers`! | — | 9 |
| `CrmGetDynamicInfoActivity`<br>Получить информацию об элементе CRM | `get_smart_item` | leaf | `DynamicTypeId`!, `ReturnFields`!, `OnlyDynamicEntities`=Y, `DynamicFilterFields`=[], `DynamicEntityFields`=[] | по `ReturnFields` | 7 |
| `CrmGetRelationsInfoActivity`<br>Получить информацию о привязанном элементе | `get_parent_item` | leaf | `ParentTypeId`!, `ParentEntityFields`=[] | по `ParentEntityFields` | 1 |
| `CrmEventAddActivity`<br>Запись события в crm | `crm_event` | leaf | `EventType`=INFO, `EventText`!, `EventUser`=[] | — | 99 |
| `CrmTimelineCommentAdd`<br>Добавить комментарий в элемент | `timeline_comment` | leaf | `CommentText`!, `CommentUser`=[] | — | 24 |
| `IMNotifyActivity`<br>Уведомление пользователя | `notify` | leaf | `MessageSite`!, `MessageOut`=, `MessageType`=2, `MessageUserFrom`!, `MessageUserTo`! | — | 20 |
| `ImMessageActivity`<br>Отправить сообщение сотруднику в чат | `chat_message` | leaf | `MessageUserFrom`!, `MessageUserTo`!, `MessageTemplate`=notify, `MessageFields`! | — | 3 |
| `Task2Activity`<br>Поставить задачу | `task` | leaf | `Fields`!, `HoldToClose`=N, `AUTO_LINK_TO_CRM_ENTITY`=Y, `AsChildTask`=, `CheckListItems`=[], `TimeEstimateHour`=, `TimeEstimateMin`= | — | 6 |
| `RobotDelayActivity`<br>Пауза робота | `delay` | leaf | `TimeoutTime`, `TimeoutDuration`, `TimeoutDurationType`, `TimeoutTimeIsLocal`=N, `WriteToLog`=Y, `WaitWorkDayUser`=[] | — | 13 |
| `StartWorkflowActivity`<br>Запустить бизнес-процесс | `start_workflow` | leaf | `DocumentId`!, `TemplateId`!, `UseSubscription`=N, `TemplateParameters`=[] | — | 4 |
| `ApproveActivity`<br>Утверждение документа | `approve` | waiting-branches | `Users`!, `Name`!, `Description`=, `StatusMessage`=, `SetStatusMessage`=Y, `ShowComment`=Y, `CommentRequired`=N, `CommentLabelMessage`=Пояснение, `TimeoutDuration`=, `TimeoutDurationType`=s, `OverdueDate`=, `AccessControl`=N, `DelegationType`=1, `ApproveType`=all, `ApproveMinPercent`=50, `ApproveWaitForAll`=N, `Parameters`=, `TaskButton1Message`=Утвердить документ, `TaskButton2Message`=Отклонить | Comments, LastApprover | 9 |
| `ReviewActivity`<br>Ознакомление с документом | `review` | waiting | `Users`!, `Name`!, `Description`=, `StatusMessage`=, `SetStatusMessage`=Y, `ShowComment`=Y, `CommentRequired`=N, `CommentLabelMessage`=Комментарий, `TimeoutDuration`=, `TimeoutDurationType`=s, `OverdueDate`=, `AccessControl`=N, `DelegationType`=1, `ApproveType`=all, `Parameters`=, `TaskButtonMessage`=Принято | Comments, LastReviewer | 18 |
| `RequestInformationActivity`<br>Запрос дополнительной информации | `request_info` | waiting | `Users`!, `Name`!, `Description`=, `StatusMessage`=, `SetStatusMessage`=Y, `ShowComment`=Y, `CommentRequired`=N, `CommentLabelMessage`=Пояснение, `TimeoutDuration`=, `TimeoutDurationType`=s, `OverdueDate`=, `AccessControl`=N, `DelegationType`=1, `RequestedInformation`!, `TaskButtonMessage`=Принято | Comments, InfoUser | 6 |
| `RequestInformationOptionalActivity`<br>Запрос доп.информации (с отклонением) | `request_info_optional` | waiting-branches | `Users`!, `Name`!, `Description`=, `StatusMessage`=, `SetStatusMessage`=Y, `ShowComment`=Y, `CommentRequired`=N, `CommentLabelMessage`=Пояснение, `TimeoutDuration`=, `TimeoutDurationType`=s, `OverdueDate`=, `AccessControl`=N, `DelegationType`=1, `RequestedInformation`!, `TaskButtonMessage`=Принято, `CancelType`=any, `TaskButtonCancelMessage`=Отклонить, `SaveVariables`=N | Comments, InfoUser | 3 |
| `CodeActivity`<br>PHP код | `php_code` | leaf | `ExecuteCode`! | — | 0 |

## Значения и поведение (курс 57 и ядро)

Урок курса указан в `activities.php` у каждого факта; здесь — сводка. Варианты без проверки ядром
(`options`) при сборке дают предупреждение, проверяемые ядром (`values`) — ошибку.

| Тип | Свойство | Значения и поведение |
|-----|----------|----------------------|
| `IfElseBranchActivity`, `WhileActivity` | `fieldcondition` | по полям документа: `[[поле, оператор, значение, связка]]`; связка: 0 — «и», 1 — «или»; «и» сильнее «или». «Или» в первой строке делает всё условие истинным |
| `IfElseBranchActivity`, `WhileActivity` | `propertyvariablecondition` | по параметрам и переменным: `[[код, оператор, значение, связка]]`; связка: 0 — «и», 1 — «или»; «и» сильнее «или». «Или» в первой строке делает всё условие истинным |
| `IfElseBranchActivity`, `WhileActivity` | `mixedcondition` | смешанное: `[{object, field, operator, value, joiner}]`; связка: 0 — «и», 1 — «или»; «и» сильнее «или». «Или» в первой строке делает всё условие истинным |
| `IfElseBranchActivity`, `WhileActivity` | `truecondition` | '1' — условие «Истина»: так делают ветку «иначе» (урок 3789) |
| `IfElseActivity` | — | ветки проверяются по порядку, выполняется первая с истинным условием; если не подошла ни одна — конструкция пропускается. Ветку «иначе» делают условием «Истина» |
| `ParallelActivity` | — | ветки выполняются слева направо, ожидание в одной не держит другие; дальше процесс идёт, когда завершены все ветки |
| `WhileActivity` | — | повторяет тело, пока условие истинно; условие проверяется перед каждой итерацией, начатая итерация доходит до конца. Не больше 1000 итераций (в облаке; в коробке — настройка модуля «Максимальное количество итераций цикла в рамках одного запуска») |
| `EmptyBlockActivity` | — | группирует шаги и сворачивается на схеме; выключенный блок выключает всё внутри (уроки 3808, 12321) |
| `SetFieldActivity` | — | ErrorMessage — текст ошибки изменения, пусто при успехе: проверяют смешанным условием «заполнено». Выражения внутри одного действия видят старые значения полей (урок 12407) |
| `SetFieldActivity` | `ModifiedBy` | «Изменять от имени» (урок 3785) |
| `SetFieldActivity` | `MergeMultipleFields` | `N` — перезаписать множественные поля; `Y` — дополнить множественные поля |
| `CrmChangeStatusActivity` | — | после смены стадии процесс сразу завершается — шаги после неё не выполняются. Больше двух смен на одну стадию документа за хит — ошибка «подозрение на рекурсию» и завершение |
| `CrmChangeStatusActivity` | `ModifiedBy` | «Изменить от имени» |
| `CrmSetObserverField` | `ActionOnObservers` | `add` — добавить; `replace` — заменить; `remove` — удалить |
| `CrmGetDynamicInfoActivity` | — | находит элемент по фильтру DynamicFilterFields; если не нашёл — ошибка «не найдено ни одного элемента» |
| `CrmGetDynamicInfoActivity` | `OnlyDynamicEntities` | `Y` — только смарт-процессы; `N` — любой тип элемента CRM |
| `CrmGetRelationsInfoActivity` | — | нет привязанного элемента этого типа — ошибка «не привязано ни одной сущности» |
| `CrmGetRelationsInfoActivity` | `ParentTypeId` | «Связь с»: тип привязанного элемента |
| `CrmEventAddActivity` | — | запись во вкладку «История» карточки CRM (урок 3778) |
| `CrmEventAddActivity` | `EventType` | `INFO` — Информация; `PHONE` — Телефонный звонок; `MESSAGE` — Отправлен email |
| `CrmEventAddActivity` | `EventUser` | «Автор» записи |
| `CrmTimelineCommentAdd` | `CommentText` | комментарий в таймлайн, BBCode (урок 20760) |
| `CrmTimelineCommentAdd` | `CommentUser` | «Автор» комментария |
| `IMNotifyActivity` | `MessageSite` | «Текст уведомления для сайта», BBCode |
| `IMNotifyActivity` | `MessageOut` | «Текст уведомления для email/jabber»; пусто — текст для сайта |
| `IMNotifyActivity` | `MessageType` | `2` — персонализированное (с аватаром) — от отправителя; `4` — от системы (без аватара) — отправитель не показывается |
| `IMNotifyActivity` | `MessageUserFrom` | нужен и при системном типе, хотя тогда не показывается |
| `ImMessageActivity` | — | личное сообщение в рабочий чат с отправителем (урок 26988) |
| `ImMessageActivity` | `MessageTemplate` | `plain` — Базовое; `news` — Объявление; `notify` — Уведомление; `important` — Важное; `alert` — Авария |
| `ImMessageActivity` | `MessageFields` | MessageText; для news и important нужен ещё MessageTitle |
| `Task2Activity` | `Fields` | обязательные ключи: `TITLE`, `CREATED_BY`, `RESPONSIBLE_ID\|FLOW_ID` |
| `Task2Activity` | `HoldToClose` | «Остановить процесс на время выполнения задачи»: Y — ждать закрытия, тогда есть ClosedBy и ClosedDate (урок 3805) |
| `Task2Activity` | `AUTO_LINK_TO_CRM_ENTITY` | «Привязать к текущей сущности CRM» |
| `Task2Activity` | `AsChildTask` | «Создать как подзадачу к текущей» — для процессов задач |
| `RobotDelayActivity` | — | паузу нельзя прервать; с паузы процесс снимает агент, поэтому он может проснуться позже заданного (урок 3807) |
| `RobotDelayActivity` | `TimeoutTime` | до какого времени ждать; дата в прошлом — пауза пропускается |
| `RobotDelayActivity` | `TimeoutDuration` | период паузы в единицах TimeoutDurationType; минимум — «Минимальное время ожидания для действий» (в облаке 5 минут) |
| `RobotDelayActivity`, `ApproveActivity`, `ReviewActivity`, `RequestInformationActivity`, `RequestInformationOptionalActivity` | `TimeoutDurationType` | `s` — секунды; `m` — минуты; `h` — часы; `d` — дни |
| `RobotDelayActivity` | `WriteToLog` | писать в журнал записи о паузе |
| `RobotDelayActivity` | `WaitWorkDayUser` | после паузы ждать начала рабочего дня сотрудника, без предела по времени (урок 25792) |
| `StartWorkflowActivity` | — | настройки и импорт — только администратор; запуск своего же шаблона блокируется (ошибка в журнал, процесс идёт дальше). Автор нового процесса — автор исходного |
| `StartWorkflowActivity` | `DocumentId` | документ того же типа, что у шаблона |
| `StartWorkflowActivity` | `UseSubscription` | `N` — не ждать; `Y` — ждать завершения запущенного процесса |
| `ApproveActivity` | — | ждёт решения; по истечении срока документ автоматически отклонён: ветка «нет», IsTimeout = 1. Comments — «ФИО (e-mail): Утвержден/Отклонен» и пояснение |
| `ApproveActivity`, `ReviewActivity`, `RequestInformationActivity`, `RequestInformationOptionalActivity` | `StatusMessage` | «Текст статуса» документа, пока ждём |
| `ApproveActivity`, `ReviewActivity`, `RequestInformationActivity`, `RequestInformationOptionalActivity` | `SetStatusMessage` | «Устанавливать текст статуса» |
| `ApproveActivity`, `ReviewActivity`, `RequestInformationActivity`, `RequestInformationOptionalActivity` | `ShowComment` | показывать исполнителю поле для пояснения |
| `ApproveActivity` | `CommentRequired` | `N` — нет; `Y` — да; `YA` — только при утверждении; `YR` — только при отклонении |
| `ApproveActivity`, `RequestInformationActivity`, `RequestInformationOptionalActivity` | `CommentLabelMessage` | подпись поля для пояснения |
| `ApproveActivity`, `ReviewActivity`, `RequestInformationActivity`, `RequestInformationOptionalActivity` | `TimeoutDuration` | срок задания в единицах TimeoutDurationType; пусто или 0 — без срока, процесс ждёт исполнителя. Минимум — «Минимальное время ожидания для действий» в настройках модуля |
| `ApproveActivity`, `ReviewActivity`, `RequestInformationActivity`, `RequestInformationOptionalActivity` | `OverdueDate` | срок задания в списке заданий, если таймаут не задан; процесс не двигает |
| `ApproveActivity`, `ReviewActivity`, `RequestInformationActivity`, `RequestInformationOptionalActivity` | `AccessControl` | «Ограничить доступ»: Y — текст задания видит только исполнитель, в живую ленту оно не попадает (урок 3771) |
| `ApproveActivity`, `ReviewActivity`, `RequestInformationActivity`, `RequestInformationOptionalActivity` | `DelegationType` | `0` — только подчинённым; `1` — всем сотрудникам; `2` — никому |
| `ApproveActivity` | `ApproveType` | `all` — все сотрудники: первое «нет» отклоняет, «да» — когда утвердили все; `any` — любой сотрудник: решает первый голос; `vote` — голосование: процент от всех назначенных |
| `ApproveActivity` | `ApproveMinPercent` | для vote: утверждено, когда процент утвердивших строго больше этого числа |
| `ApproveActivity` | `ApproveWaitForAll` | `N` — решить, как только наберётся процент; `Y` — ждать, пока проголосуют все |
| `ReviewActivity` | — | по истечении срока задание завершается автоматически (IsTimeout = 1), процесс идёт дальше |
| `ReviewActivity`, `RequestInformationActivity` | `CommentRequired` | `N` — нет; `Y` — да |
| `ReviewActivity` | `ApproveType` | `all` — должны ознакомиться все; `any` — достаточно любого |
| `RequestInformationActivity` | — | задание выполняет первый приступивший из Users; по истечении срока — автоматическое завершение (IsTimeout = 1, InfoUser пуст), процесс идёт дальше |
| `RequestInformationActivity` | `RequestedInformation` | поля запроса; ответы записываются в переменные процесса с теми же кодами |
| `RequestInformationOptionalActivity` | — | по истечении срока — автоматическое завершение (IsTimeout = 1) и ветка отклонения |
| `RequestInformationOptionalActivity` | `CommentRequired` | `N` — нет; `Y` — да; `YA` — только при утверждении (вводе); `YR` — только при отклонении |
| `RequestInformationOptionalActivity` | `CancelType` | `any` — отклоняет любой сотрудник; `all` — отклонено, когда отказали все |
| `RequestInformationOptionalActivity` | `SaveVariables` | «Сохранять значения в случае отказа» (с bizproc 20.200.0, урок 7839) |

## Проверено на стенде (коробка, bizproc 26.1075.0, crm 26.800.0, 2026-09-22)
- **Обязательность — по проверке ядра.** Импорт вызывает `ValidateProperties` каждого действия.
  «Уведомление пользователя» требует отправителя (`MessageUserFrom`), получателя и текст; не
  администратор может указать отправителем только себя. «Утверждение» требует `Users`, `Name` и
  `ApproveType` из `all`, `any`, `vote`. «Запись события в crm» — `EventType` и `EventText`.
  «Запустить бизнес-процесс» при импорте требует прав администратора. Каталог и сборщик это учитывают:
  `bpt.php catalog <алиас>` показывает допустимые значения.
- **Проверка импорта без записи в базу** — `CBPWorkflowTemplateLoader::validateTemplate`, тот же
  вызов, что делает импорт. Не пройдут: задача без `CREATED_BY` («не указан постановщик»), сообщение в
  чат без отправителя, системное уведомление без отправителя. Пройдут: комментарий без автора, пауза
  робота только с периодом, `replace` у наблюдателей, срок задания с неизвестной единицей (её ядро
  потом молча считает секундами). Свойства выключенных действий импорт не проверяет.
- **«Сменить стадию» завершает процесс.** Шаг после неё не выполнился; статус процесса —
  «Завершён в результате изменения стадии». Так же говорит курс
  ([урок 9011](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=9011)), в коде —
  `CBPDocument::TerminateWorkflow`. Исключения по коду: стадия не из воронки документа — ошибка в
  журнал и процесс идёт дальше; больше двух смен на одну стадию за хит — завершение с ошибкой
  «подозрение на рекурсию». В корпусе смена стадии стоит последней в 42 случаях из 43.
- **Результат `Comments` утверждения** — строка «ФИО (e-mail): Утвержден» или «…: Отклонен» и с
  новой строки «Пояснение: текст» (подпись — `CommentLabelMessage`). E-mail согласующего попадает
  туда, куда подставлен результат.
- **Автозапуск «при изменении»** срабатывает на каждую правку пользователя или API, в том числе при
  ещё не законченном процессе: создание и две правки дали три параллельных согласования. Изменения,
  которые делает сам БП (смена стадии, изменение поля), автозапуск не вызывают — в CRM так по
  умолчанию, пока не включена опция `start_bp_within_bp` ([[entity-robots-triggers]]).

## Что известно не наверняка
- **Минимум паузы в облаке** — в курсе 10 минут
  ([урок 3807](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=3807)) и 5 минут
  ([урок 25792](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=25792)).
- **`Parameters`** у заданий курс не описывает; смысл `OverdueDate` взят из кода ядра.
- **Кнопки утверждения** в корпусе всегда переименованы; значения по умолчанию — подписи дизайнера
  («Утвердить документ», «Отклонить»).
- **Корпус — шаблоны смарт-процессов одной коробки.** Действий для списков, Диска, задач и других
  документов, облачных экспортов и своих действий из `/local/activities/` здесь пока нет, как и
  «Итератора», «Параллельного ожидания действия», «Команды» и «Установить статус».

## Как пополнять
1. Положить новые экспорты в папку корпуса и запустить
   `php tools/bpt/tests/run.php --corpus=<папка>`: тест назовёт типы и свойства, которых нет.
2. Добавить запись в `catalog/activities.php`: форму вложенности, свойства с типами и значениями
   по умолчанию, `returns` по ссылкам из шаблонов. Смысл и варианты — `note` и `options` с уроком
   курса в комментарии; допустимые значения `values` — только если их проверяет ядро.
3. Прогнать тесты: разбор и сборка корпуса должны совпасть с оригиналом.
4. Обновить таблицы этой страницы (`php tools/bpt/bpt.php catalog` и `… catalog --notes`) и дату
   `verified`.

## Облако vs коробка
Корпус — экспорты коробки. В облаке набор действий может отличаться: облачные экспорты мы пока не
разбирали; курс облако и коробку почти не разделяет. В коробке, кроме того, бывают свои действия в
`/local/activities/` и PHP-код. Устройство действий описано в
[[concept-bizproc-engine|Устройство движка БП]] и [[entity-cbp-activity|CBPActivity и BaseActivity]].
Действие «PHP код» в каталоге помечено запрещённым: сборщик его не генерирует.

## Связанные страницы
- [[concept-bizproc-bpt-format|Формат шаблона .bpt]] — где эти действия лежат в файле
- [[pattern-bizproc-ai-assisted-generation|AI-генерация БП]] — как каталог используется при генерации
- [[entity-cbp-task-service|CBPTaskService]] — как устроены задания внутри
- [[antipattern-bizproc-hardcoded-portal-ids|Зашитые ID портала]]
- [[concept-bizproc-expressions|Выражения БП]] — что можно писать в свойствах
- [[recipe-bizproc-approval-route|Маршрут согласования]], [[recipe-bizproc-request-intake|Заявка]] — действия в типовых процессах
- [[checklist-bizproc-template-review|Ревью шаблона]] — ошибки проектирования
- Формат спецификации — [`tools/bpt/SPEC.md`](../../../tools/bpt/SPEC.md)

[← Бизнес-процессы](_index-bizproc.md)
