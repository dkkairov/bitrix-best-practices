---
title: "Своё действие БП с заданием: CBPTaskService, форма и диалог настроек"
type: recipe
module: bizproc
edition: box
status: verified
provenance: mixed
verified: "2026-06-08 / коробка: классический дизайнер БП + новый UI заданий в карточке смарт-процесса; сверено с «Книгой разработчика Bitrix24» 2026-09-21 (Модуль Бизнес-процессы — Действия): базовый класс и RETURN/ADDITIONAL_RESULT расходятся с книгой, см. врезку"
tags: [bizproc, активити, задание, CBPTaskService, дизайнер, форма]
sources: ["[[source-devbook-bizproc]]"]
related: ["[[pattern-robots-vs-bizproc-decision]]", "[[recipe-module-structure-and-install]]", "[[concept-change-invasiveness-hierarchy]]", "[[entity-robots-triggers]]", "[[entity-cbp-task-service]]", "[[entity-cbp-activity]]", "[[entity-bizproc-activity-description]]", "[[antipattern-bizproc-php-code-activity]]"]
aliases: ["bitrix24-bp-task-activity"]
updated: "2026-09-21"
---

# Своё действие БП с заданием

**Результат:** бизнес-процесс приостанавливается до тех пор, пока человек не заполнит вашу форму;
задание попадает в штатный список заданий БП и в живую ленту, как встроенные «Утверждение» и
«Ознакомление».

> **Сверено с книгой 2026-09-21: три расхождения, рецепт оставлен как практика команды.**
> 1. **Базовый класс.** По книге задание — событийное действие, которое наследует
>    `CBPCompositeActivity` и реализует те же интерфейсы
>    ([Действия → классификация](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Dejstvia.html#klassifikacia-dejstvij)).
>    У нас — `CBPActivity` + `IBPEventActivity` + `IBPActivityExternalEventListener`, и это работает на
>    стенде (2026-06-08). **Решение:** оставить как осознанную практику; при обновлении коробки или
>    странностях выполнения сверить со штатными заданиями дистрибутива и при необходимости перейти на
>    `CBPCompositeActivity` ([[entity-cbp-task-service]], открытый вопрос).
> 2. **`CATEGORY`.** Книга называет только `['ID' => 'other']` и свой раздел через `OWN_ID`/`OWN_NAME`
>    ([CATEGORY](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Dejstvia.html#category)).
>    Таблица встроенных кодов в шаге 5 — наблюдения команды.
> 3. **`RETURN` и `ADDITIONAL_RESULT`.** По книге `ADDITIONAL_RESULT` перечисляет **свойства-карты**
>    результатов, состав которых определяется на ходу, а не дублирует ключи `RETURN`
>    ([ADDITIONAL_RESULT](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Dejstvia.html#additional-result)).
>    Наш тезис «`RETURN` — для классического дизайнера, `ADDITIONAL_RESULT` — для роботов, указывать
>    оба» — эмпирика, книгой не подтверждается; сама книга здесь противоречит себе
>    ([[entity-bizproc-activity-description]]). **Решение:** до проверки на стенде постоянный
>    результат объявлять в `RETURN` + `SetPropertiesTypes()`, `ADDITIONAL_RESULT` — только со
>    свойством-картой; после выкладки проверить «Вставку значения» и в дизайнере, и в роботах.

## Предусловия
- Решено, что нужен полноценный БП, а не робот — [[pattern-robots-vs-bizproc-decision]].
- Действие поставляется модулем — [[recipe-module-structure-and-install]]; файлы активити
  раскладываются в `DoInstall` через `CopyDirFiles` в один из каталогов, где движок ищет действия,
  обычно `/local/activities/custom/` (порядок поиска — [[entity-bizproc-activity-description]]).

## Почему не свой URL

Альтернатива — `OnBeforeProlog` и своя страница-форма. Работает, но не интегрируется в штатный UI
заданий: пользователь должен сам перейти по ссылке, задание не видно в общем списке. Штатный путь —
`CBPTaskService::CreateTask()`: дальше Bitrix сам вызывает на классе активити `ShowTaskForm`,
`PostTaskForm` и `getTaskControls`.

## Шаги

### 1. Класс активити

```php
// практика команды: CBPActivity; книга для заданий называет CBPCompositeActivity (см. врезку вверху)
class CBPMyActivity extends CBPActivity
    implements IBPEventActivity, IBPActivityExternalEventListener
{
    private $taskId = 0;
    private $taskStatus = false;
    private $isInEventActivityMode = false;

    public function Execute()
    {
        if ($this->isInEventActivityMode) return CBPActivityExecutionStatus::Closed;
        $this->Subscribe($this);
        return CBPActivityExecutionStatus::Executing;
    }

    public function Subscribe(IBPActivityExternalEventListener $eventHandler)
    {
        $this->isInEventActivityMode = true;
        $users = CBPHelper::ExtractUsers($this->Responsible, $this->GetDocumentId(), false);
        $taskService = $this->workflow->GetService('TaskService');

        $this->taskId = $taskService->CreateTask([
            'USERS'         => $users,
            'WORKFLOW_ID'   => $this->GetWorkflowInstanceId(),
            'ACTIVITY'      => 'MyActivity',      // имя класса без префикса CBP
            'ACTIVITY_NAME' => $this->name,
            'NAME'          => $this->AssignmentName,
            'PARAMETERS'    => ['KEY' => 'value'], // данные для ShowTaskForm
            'IS_INLINE'     => 'N',
            'DELEGATION_TYPE' => 0,                // запретить делегирование
            'DOCUMENT_NAME' => CBPRuntime::GetRuntime()
                ->GetService('DocumentService')->GetDocumentName($this->GetDocumentId()),
        ]);
        $this->workflow->AddEventHandler($this->name, $eventHandler);
    }

    public function Unsubscribe(IBPActivityExternalEventListener $eventHandler)
    {
        $taskService = $this->workflow->GetService('TaskService');
        if ($this->taskStatus === false) {
            $taskService->DeleteTask($this->taskId);      // БП отменён — задание убрать
        } else {
            $taskService->Update($this->taskId, ['STATUS' => $this->taskStatus]);
        }
        $this->workflow->RemoveEventHandler($this->name, $eventHandler);
        $this->taskId = 0; $this->taskStatus = false;
    }

    public function OnExternalEvent($params = [])
    {
        if ($this->executionStatus == CBPActivityExecutionStatus::Closed) return;
        $taskService = $this->workflow->GetService('TaskService');
        $taskService->MarkCompleted($this->taskId, $params['REAL_USER_ID'], CBPTaskUserStatus::Ok);
        $this->taskStatus = CBPTaskStatus::CompleteOk;
        $this->Unsubscribe($this);
        $this->workflow->CloseActivity($this);
    }

    public function Cancel()
    {
        if ($this->isInEventActivityMode && $this->taskId > 0) $this->Unsubscribe($this);
        return CBPActivityExecutionStatus::Closed;
    }

    public function HandleFault(Exception $ex)
    {
        $status = $this->Cancel();
        return $status == CBPActivityExecutionStatus::Canceling
            ? CBPActivityExecutionStatus::Faulting : $status;
    }
}
```

`Cancel()` и `HandleFault()` обязательны: без них при отмене БП задание останется висеть у людей.

### 2. Три статических метода, которые зовёт Bitrix

```php
public static function ShowTaskForm($arTask, $userId, $userName = '')
{
    // данные брать из $arTask['PARAMETERS'] (заполнены при CreateTask).
    // Перезагрузка остановленного workflow через GetWorkflow()->GetActivityByName()
    // работает, но хрупка: в новом UI может вернуть пустые свойства → пустое тело задания.
    $form = CBPRuntime::GetRuntime()->ExecuteResourceFile(__FILE__, 'tasktemplate.php', [
        'arResult' => ['PARAM' => $arTask['PARAMETERS']['KEY']],
    ]);
    return [$form, '<input type="submit" name="finish" value="Готово">'];
}

public static function getTaskControls($arTask)
{
    return ['BUTTONS' => [[
        'TYPE' => 'submit', 'TARGET_USER_STATUS' => CBPTaskUserStatus::Ok,
        'NAME' => 'finish', 'VALUE' => 'Y', 'TEXT' => 'Готово',
    ]]];
}

public static function PostTaskForm($arTask, $userId, $arRequest, &$arErrors, $userName = '', $realUserId = null)
{
    $arErrors = [];
    CBPRuntime::SendExternalEvent(            // СТАТИЧЕСКИЙ вызов
        $arTask['WORKFLOW_ID'],
        $arTask['ACTIVITY_NAME'],
        ['USER_ID' => (int)$userId, 'REAL_USER_ID' => $realUserId ?? (int)$userId]
    );
    return true;
}
```

### 3. `tasktemplate.php` — форма задания: только `<div>`

Вёрстка зависит от UI, и это главная ловушка:

- **классический попап задания БП** оборачивает форму в `<table>` → ждёт `<tr><td>`;
- **новый UI заданий в карточке смарт-процесса** вставляет форму в `<div>` → одинокие `<tr>`
  **выбрасываются браузером, тело задания пустое** (кнопки при этом есть).

`<div>` рендерится в обоих случаях, `<tr>` в div-контейнере — нет. Значит, `<div>`:

```php
<?php defined('B_PROLOG_INCLUDED') || die; ?>
<div class="my-task-form" style="max-width:760px;">
    <div style="margin-bottom:8px;">Вопрос:</div>
    <input name="my_field" size="50" value="">
</div>
```

Поля ввода submit-ятся независимо от обёртки — Bitrix сам оборачивает вывод `ShowTaskForm` в свой
`<form>`.

### 4. `properties_dialog.php` — диалог настроек действия

Здесь, наоборот, строки таблицы, и обязательно `CBPDocument::ShowParameterField()` — иначе
настройка не сможет принимать переменные БП:

```php
<?php defined('B_PROLOG_INCLUDED') || die; ?>
<tr>
    <td align="right" width="40%"><span class="adm-required-field">Ответственный:</span></td>
    <td width="60%">
        <?= CBPDocument::ShowParameterField('user', 'Responsible', $arCurrentValues['Responsible']) ?>
    </td>
</tr>
```

В `GetPropertiesDialog` значение поля-пользователя приводится к строке через
`CBPHelper::UsersArrayToString`, в `GetPropertiesDialogValues` — обратно через
`CBPHelper::UsersStringToArray`.

### 5. `.description.php` — категория и результаты

```php
$arActivityDescription = [
    'CLASS'             => 'MyActivity',      // без префикса CBP
    'JSCLASS'           => 'BizProcActivity',
    'CATEGORY'          => ['ID' => 'task'],  // см. таблицу ниже
    'RETURN'            => ['ResultField' => ['NAME' => '…', 'TYPE' => 'int']],
    'ADDITIONAL_RESULT' => ['ResultField'],   // эмпирика команды; по книге здесь свойство-карта — см. врезку
];
```

Встроенные коды разделов — наблюдения команды; в книге есть только `other` и свой раздел:

| `CATEGORY['ID']` | Раздел дизайнера |
|---|---|
| `document` | Обработка документа |
| `task` | **Задания** |
| `constructs` | Конструкции |
| `notification` | Уведомления |
| `other` | Прочее |

`OWN_ID` + `OWN_NAME` создают **свой** раздел — не указывать, если нужна встроенная категория.
Неизвестный `ID` → действие падает в «Мои действия» либо не отображается вовсе.

`RETURN` делает результаты выбираемыми в классическом дизайнере, `ADDITIONAL_RESULT` — в
D7-движке и роботах. Указывать оба безопасно и покрывает обе среды. **Расходится с книгой
(2026-09-21):** книга описывает `ADDITIONAL_RESULT` иначе — как список свойств-карт для
динамических результатов; до проверки на стенде следовать решению из врезки вверху.

## Проверка результата
- Действие видно в разделе «Задания» дизайнера БП (после сброса кэша).
- Задание появляется в штатном попапе и в списке заданий.
- Тело задания **непустое** и в классическом попапе, и в карточке смарт-процесса.
- Отмена БП удаляет задание, завершение — закрывает его.

## Подводные камни

| Симптом | Причина | Решение |
|---|---|---|
| Действия нет в дизайнере / упало в «Мои действия» | нестандартный `CATEGORY['ID']` или лишний `OWN_ID` | `['ID' => 'task']`, затем сбросить кэш |
| **Тело задания пустое в новом UI**, кнопки есть | `ShowTaskForm` вернул `<tr>`, контейнер нового UI — `<div>` | `<div>`-вёрстка; данные из `$arTask['PARAMETERS']`, не перезагружать workflow |
| БП не просыпается | `SendExternalEvent` вызван как метод экземпляра | только статически: `CBPRuntime::SendExternalEvent(...)` |
| Задание не появляется в попапе | `ACTIVITY` в `CreateTask` ≠ имени класса без `CBP` | привести в соответствие |
| Задание не удаляется при отмене БП | нет `Cancel()` / `HandleFault()` | добавить оба, оба зовут `Unsubscribe()` |
| Поле-пользователь сохраняется строкой | не использован `CBPHelper` | конвертировать в обе стороны |
| `Call to FaultActivity() on null` | `$this->Execution` в действии не существует | прерывать через `throw new Exception(...)` — движок вызовет `HandleFault` (эмпирика; штатный путь по книге — статус `Faulting`, см. [[entity-cbp-activity]]) |
| **Свой JS в диалоге настроек не отрабатывает** | диалог грузится AJAX-ом, inline `<script>` исполняется ненадёжно (особенно в редакторе автоматизации CRM) | лёгкий случай — progressive enhancement (`<textarea>` + билдер поверх); надёжно — вынести сложное редактирование на отдельную страницу |
| **Нужна загрузка файлов в настройках действия** | диалог сохраняется AJAX-ом и multipart не принимает в принципе | отдельная админ-страница с обычным multipart-POST и `CFile::SaveFile`; действие хранит только ID сущности |
| **Админ-страница из `/local/admin/` даёт 404** | Bitrix не роутит `/local/admin/` по URL | реальную страницу класть в `/local/admin/`, а в `/bitrix/admin/<file>.php` — тонкий загрузчик `require($_SERVER['DOCUMENT_ROOT'].'/local/admin/<file>.php');`; снимать при удалении модуля; само-ссылки строить от `$_SERVER['SCRIPT_NAME']` |

## Альтернативы
- `OnBeforeProlog` + свой URL — не интегрирован в UI заданий.
- `IBPEventActivity` без `CBPTaskService` — внешнее событие без UI: подходит для интеграций по
  REST/вебхуку, не для человека.

## Связанное
- [[pattern-robots-vs-bizproc-decision]] — нужен ли вообще БП
- [[recipe-module-structure-and-install]] — как поставляется действие
- [[entity-cbp-task-service]] — методы и поля сервиса заданий
- [[entity-bizproc-activity-description]] — паспорт действия `.description.php` по книге
- [[antipattern-bizproc-php-code-activity]] — почему своё действие, а не «PHP код»

[← Бизнес-процессы](_index-bizproc.md)
