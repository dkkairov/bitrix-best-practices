---
title: "Каталог действий БП: свойства, вложенность, результаты"
type: concept
module: bizproc
edition: both
status: verified
provenance: empirical
verified: "2026-09-22 / коробка клиента: корпус 16 экспортов из дизайнера БП (VERSION 2); стенд Docker, bizproc 26.1075.0: ValidateProperties и прогон процессов"
tags: [бизнес-процессы, действия, активити, каталог, bpt, генерация, роботы]
sources: []
related: ["[[concept-bizproc-bpt-format]]", "[[pattern-bizproc-ai-assisted-generation]]", "[[concept-bizproc-engine]]", "[[entity-cbp-activity]]", "[[antipattern-bizproc-hardcoded-portal-ids]]"]
aliases: []
updated: "2026-09-22"
---

# Каталог действий БП: свойства, вложенность, результаты

**TL;DR:** справочник 25 типов действий, которые встречаются в реальных шаблонах: какие у них
свойства, что обязательно, какие значения по умолчанию, что можно вкладывать и что действие
возвращает для ссылок `{=A…:Результат}`. Официальной документации по свойствам действий нет —
каталог собран по корпусу экспортов и проверяется на нём тестом. Источник истины —
[`tools/bpt/catalog/activities.php`](../../../tools/bpt/catalog/activities.php); эта таблица —
его печать (`php tools/bpt/bpt.php catalog`).

> **Изменено 2026-09-21.** Корпус раньше описывался как «шаблоны роботов смарт-процессов в облаке».
> Команда уточнила: это экспорты из дизайнера БП с коробки клиента. Добавлены два новых экспорта
> (пустой шаблон и согласование), счётчики пересчитаны; новых типов и свойств нет.

## Зачем он нужен
- **Сборка из спецификации.** Сборщик берёт из каталога значения по умолчанию и отвергает
  неизвестные действия и свойства — так модель не может «придумать» свойство
  ([[pattern-bizproc-ai-assisted-generation|AI-генерация БП]]).
- **Ревью чужих шаблонов.** Видно, какое свойство отличается от обычного значения, что возвращает
  задание и что из этого берут другие шаги.
- **Перенос между порталами.** Свойства с типом `portal-id` (`TargetStatus`, `TemplateId`,
  `DynamicTypeId`, `ParentTypeId`) — это идентификаторы портала
  ([[antipattern-bizproc-hardcoded-portal-ids|Зашитые ID портала]]).

## Как читать таблицу
- **Алиас** — короткое имя действия в спецификации (`change_stage`); прочерк — служебный узел,
  который создаёт сборщик.
- **Свойства:** `!` — обязательное, `=значение` — значение по умолчанию, без отметки — необязательное
  без значения по умолчанию.
- **Возвращает** — результаты, которые в корпусе брали по ссылкам. У «получить информацию» список
  задаётся свойством самого действия.
- *(заголовок под вопросом)* — в корпусе у всех таких действий был свой заголовок, поэтому
  заголовок по умолчанию выбран по смыслу; сверить в дизайнере.

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
| `CrmGetDynamicInfoActivity`<br>Получить информацию об элементе смарт-процесса | `get_smart_item` | leaf | `DynamicTypeId`!, `ReturnFields`!, `OnlyDynamicEntities`=Y, `DynamicFilterFields`=[], `DynamicEntityFields`=[] | по `ReturnFields` | 7 |
| `CrmGetRelationsInfoActivity`<br>Получить информацию о привязанном элементе | `get_parent_item` | leaf | `ParentTypeId`!, `ParentEntityFields`=[] | по `ParentEntityFields` | 1 |
| `CrmEventAddActivity`<br>Запись события в crm | `crm_event` | leaf | `EventType`=INFO, `EventText`!, `EventUser`=[] | — | 99 |
| `CrmTimelineCommentAdd`<br>Добавить комментарий в элемент | `timeline_comment` | leaf | `CommentText`!, `CommentUser`=[] | — | 24 |
| `IMNotifyActivity`<br>Уведомление пользователя | `notify` | leaf | `MessageSite`!, `MessageOut`=, `MessageType`=2, `MessageUserFrom`!, `MessageUserTo`! | — | 20 |
| `ImMessageActivity`<br>Отправить сообщение сотруднику в чат | `chat_message` | leaf | `MessageUserFrom`=, `MessageUserTo`!, `MessageTemplate`=notify, `MessageFields`! | — | 3 |
| `Task2Activity`<br>Поставить задачу | `task` | leaf | `Fields`!, `HoldToClose`=N, `AUTO_LINK_TO_CRM_ENTITY`=Y, `AsChildTask`=, `CheckListItems`=[], `TimeEstimateHour`=, `TimeEstimateMin`= | — | 6 |
| `RobotDelayActivity`<br>Пауза робота | `delay` | leaf | `TimeoutTime`!, `TimeoutTimeIsLocal`=N, `WriteToLog`=Y, `WaitWorkDayUser`=[] | — | 13 |
| `StartWorkflowActivity`<br>Запустить бизнес-процесс | `start_workflow` | leaf | `DocumentId`!, `TemplateId`!, `UseSubscription`=N, `TemplateParameters`=[] | — | 4 |
| `ApproveActivity`<br>Утверждение документа *(заголовок под вопросом)* | `approve` | waiting-branches | `Users`!, `Name`!, `Description`=, `StatusMessage`=, `SetStatusMessage`=Y, `ShowComment`=Y, `CommentRequired`=N, `CommentLabelMessage`=Пояснение, `TimeoutDuration`=, `TimeoutDurationType`=s, `OverdueDate`=, `AccessControl`=N, `DelegationType`=1, `ApproveType`=all, `ApproveMinPercent`=50, `ApproveWaitForAll`=N, `Parameters`=, `TaskButton1Message`=Утвердить, `TaskButton2Message`=Отклонить | Comments, LastApprover | 9 |
| `ReviewActivity`<br>Ознакомление *(заголовок под вопросом)* | `review` | waiting | `Users`!, `Name`!, `Description`=, `StatusMessage`=, `SetStatusMessage`=Y, `ShowComment`=Y, `CommentRequired`=N, `CommentLabelMessage`=Комментарий, `TimeoutDuration`=, `TimeoutDurationType`=s, `OverdueDate`=, `AccessControl`=N, `DelegationType`=1, `ApproveType`=all, `Parameters`=, `TaskButtonMessage`=Принято | Comments, LastReviewer | 18 |
| `RequestInformationActivity`<br>Запрос дополнительной информации *(заголовок под вопросом)* | `request_info` | waiting | `Users`!, `Name`!, `Description`=, `StatusMessage`=, `SetStatusMessage`=Y, `ShowComment`=Y, `CommentRequired`=N, `CommentLabelMessage`=Пояснение, `TimeoutDuration`=, `TimeoutDurationType`=s, `OverdueDate`=, `AccessControl`=N, `DelegationType`=1, `RequestedInformation`!, `TaskButtonMessage`=Принято | Comments, InfoUser | 6 |
| `RequestInformationOptionalActivity`<br>Запрос информации с возможностью отклонить *(заголовок под вопросом)* | `request_info_optional` | waiting-branches | `Users`!, `Name`!, `Description`=, `StatusMessage`=, `SetStatusMessage`=Y, `ShowComment`=Y, `CommentRequired`=N, `CommentLabelMessage`=Пояснение, `TimeoutDuration`=, `TimeoutDurationType`=s, `OverdueDate`=, `AccessControl`=N, `DelegationType`=1, `RequestedInformation`!, `TaskButtonMessage`=Принято, `CancelType`=any, `TaskButtonCancelMessage`=Отклонить, `SaveVariables`=N | Comments, InfoUser | 3 |
| `CodeActivity`<br>Выполнение PHP-кода | `php_code` | leaf | `ExecuteCode`! | — | 0 |

## Проверено на стенде (коробка, bizproc 26.1075.0, crm 26.800.0, 2026-09-22)
- **Обязательность — по проверке ядра.** Импорт вызывает `ValidateProperties` каждого действия.
  «Уведомление пользователя» требует отправителя (`MessageUserFrom`), получателя и текст; не
  администратор может указать отправителем только себя. «Утверждение» требует `Users`, `Name` и
  `ApproveType` из `all`, `any`, `vote`. «Запись события в crm» — `EventType` и `EventText`.
  «Запустить бизнес-процесс» при импорте требует прав администратора. Каталог и сборщик это учитывают:
  `bpt.php catalog <алиас>` показывает допустимые значения.
- **«Сменить стадию» завершает процесс.** Шаг после неё не выполнился; статус процесса —
  «Завершён в результате изменения стадии». В корпусе смена стадии стоит последней в 42 случаях из
  43; в оставшемся шаблоне сообщение после неё, по этому опыту, не отправляется.
- **Результат `Comments` утверждения** — строка «ФИО (e-mail): Утвержден» или «…: Отклонен» и с
  новой строки «Пояснение: текст» (подпись — `CommentLabelMessage`). E-mail согласующего попадает
  туда, куда подставлен результат.
- **Автозапуск «при изменении»** срабатывает на каждую правку пользователя или API, в том числе при
  ещё не законченном процессе: создание и две правки дали три параллельных согласования. Изменения,
  которые делает сам БП (смена стадии, изменение поля), автозапуск не вызывают.

## Что известно не наверняка
- **Смысл значений** взят из наблюдений: например, `MessageType` у уведомления встречался `2` и
  `4`, связка в условиях `"0"`/`"1"` — по наблюдениям «и»/«или».
- **Кнопки утверждения** в корпусе всегда переименованы, поэтому значения по умолчанию
  («Утвердить», «Отклонить») — нейтральные подписи, а не наблюдение.
- **Корпус — шаблоны смарт-процессов одной коробки.** Действий для списков, задач и других
  документов, облачных экспортов и своих действий из `/local/activities/` здесь пока нет.

## Как пополнять
1. Положить новые экспорты в папку корпуса и запустить
   `php tools/bpt/tests/run.php --corpus=<папка>`: тест назовёт типы и свойства, которых нет.
2. Добавить запись в `catalog/activities.php`: форму вложенности, свойства с типами и значениями
   по умолчанию, `returns` по ссылкам из шаблонов.
3. Прогнать тесты: разбор и сборка корпуса должны совпасть с оригиналом.
4. Обновить эту таблицу (`php tools/bpt/bpt.php catalog`) и дату `verified`.

## Облако vs коробка
Корпус — экспорты коробки. В облаке набор действий может отличаться: облачные экспорты мы пока не
разбирали. В коробке, кроме того, бывают свои действия в `/local/activities/` и PHP-код. Устройство
действий описано в [[concept-bizproc-engine|Устройство движка БП]] и
[[entity-cbp-activity|CBPActivity и BaseActivity]]. Действие «Выполнение PHP-кода» в каталоге
помечено запрещённым: сборщик его не генерирует.

## Связанные страницы
- [[concept-bizproc-bpt-format|Формат шаблона .bpt]] — где эти действия лежат в файле
- [[pattern-bizproc-ai-assisted-generation|AI-генерация БП]] — как каталог используется при генерации
- [[antipattern-bizproc-hardcoded-portal-ids|Зашитые ID портала]]
- Формат спецификации — [`tools/bpt/SPEC.md`](../../../tools/bpt/SPEC.md)

[← Бизнес-процессы](_index-bizproc.md)
