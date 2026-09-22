---
title: "Своё действие БП с заданием: CBPTaskService, форма и диалог настроек"
type: recipe
module: bizproc
edition: box
status: verified
provenance: mixed
verified: "2026-06-08 / коробка: классический дизайнер БП + новый UI заданий в карточке смарт-процесса; сверено с «Книгой разработчика Bitrix24» 2026-09-21 и с курсом 57 и кодом стенда (bizproc 26.1075.0) 2026-09-22: базовый класс, результаты, тип делегирования и коды разделов — см. врезку"
tags: [bizproc, активити, задание, CBPTaskService, дизайнер, форма]
sources: ["[[source-devbook-bizproc]]", "[[source-course57-actions-core]]", "[[source-course57-developer]]"]
related: ["[[pattern-robots-vs-bizproc-decision]]", "[[recipe-module-structure-and-install]]", "[[concept-change-invasiveness-hierarchy]]", "[[entity-robots-triggers]]", "[[entity-cbp-task-service]]", "[[entity-cbp-activity]]", "[[entity-bizproc-activity-description]]", "[[antipattern-bizproc-php-code-activity]]", "[[concept-bizproc-activity-catalog]]"]
aliases: ["bitrix24-bp-task-activity"]
updated: "2026-09-22"
---

# Своё действие БП с заданием

**Результат:** бизнес-процесс приостанавливается до тех пор, пока человек не заполнит вашу форму;
задание попадает в штатный список заданий БП и в живую ленту, как встроенные «Утверждение» и
«Ознакомление».

> **Сверено с книгой 2026-09-21 и с курсом 57 и ядром 2026-09-22.**
> 1. **Базовый класс — спор снят.** Книга называет базовым классом задания `CBPCompositeActivity`
>    ([Действия → классификация](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Dejstvia.html#klassifikacia-dejstvij)),
>    у нас — `CBPActivity` + `IBPEventActivity` + `IBPActivityExternalEventListener`. В ядре (bizproc
>    26.1075.0) штатны оба: «Ознакомление» (`CBPReviewActivity`, без веток) наследует `CBPActivity`,
>    «Утверждение» и «Запрос информации» (с ветками) — `CBPCompositeActivity`. **Решение:** задание без
>    веток, как в этом рецепте, — на `CBPActivity`; нужны ветки «да/нет» — `CBPCompositeActivity` по
>    образцу `CBPApproveActivity`.
> 2. **`CATEGORY` — сверено с ядром.** Встроенные разделы дизайнера заданы в компоненте
>    `bizproc.workflow.edit`: `document`, `task`, `logic`, `interaction`, `rest`, `other` плюс свои через
>    `OWN_ID`/`OWN_NAME`. Коды `constructs` и `notification` из прежней таблицы команды в ядре не
>    встречаются — таблица в шаге 5 исправлена.
> 3. **`RETURN` и `ADDITIONAL_RESULT` — снято для дизайнера.** Курс: результаты заданий, у которых в
>    описании только `RETURN`, доступны во «Вставке значения → Дополнительные результаты» сразу после
>    добавления действия ([урок 3771](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=3771)).
>    Ядро: список результатов для дизайнера строится из `RETURN`, а `ADDITIONAL_RESULT` читается, только
>    если `RETURN` пуст, и ждёт свойства-карты (`PropertiesDialog::extractChildProperties`). Ключ из
>    `RETURN`, повторённый в `ADDITIONAL_RESULT`, ничего не даёт. **Решение:** постоянный результат —
>    `RETURN` + `SetPropertiesTypes()`; `ADDITIONAL_RESULT` — только со свойством-картой. Видимость в
>    роботах проверить в интерфейсе роботов.
> 4. **Тип делегирования — исправлено.** `DELEGATION_TYPE => 0` раньше был подписан «запретить
>    делегирование». По ядру `0` — «только подчинённым», запрет — `CBPTaskDelegationType::None` (`2`);
>    курс называет эти варианты «Только подчиненным» и «Никому»
>    ([урок 3771](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=3771)).

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
// задание без веток — CBPActivity, как штатное «Ознакомление»; с ветками — CBPCompositeActivity (см. врезку)
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
            'DELEGATION_TYPE' => CBPTaskDelegationType::None,  // 2 — никому; 0 — только подчинённым
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
    'CLASS'    => 'MyActivity',      // без префикса CBP
    'JSCLASS'  => 'BizProcActivity',
    'CATEGORY' => ['ID' => 'task'],  // см. таблицу ниже
    'RETURN'   => ['ResultField' => ['NAME' => '…', 'TYPE' => 'int']],
    // ADDITIONAL_RESULT не нужен: он для свойств-карт с результатами, которые определяются на ходу
];
```

Встроенные разделы дизайнера — по ядру (bizproc 26.1075.0, компонент `bizproc.workflow.edit`):

| `CATEGORY['ID']` | Раздел дизайнера |
|---|---|
| `document` | Обработка документа |
| `task` | **Задания** |
| `logic` | Конструкции |
| `interaction` | Уведомления |
| `rest` | Действия приложений |
| `other` | Прочее |

> **Изменено 2026-09-22.** Раньше в таблице были коды `constructs` и `notification` (наблюдения
> команды) — в ядре их нет, исправлено на `logic` и `interaction`.

`OWN_ID` + `OWN_NAME` создают **свой** раздел — не указывать, если нужна встроенная категория
(штатное «Утверждение» кладёт себя в «Задания» как `['ID' => 'document', 'OWN_ID' => 'task']`).
Неизвестный `ID` без `OWN_ID` — по опыту команды действие не отображается в панели.

Результат из `RETURN` дизайнер показывает во «Вставке значения → Дополнительные результаты» сразу;
`ADDITIONAL_RESULT` он читает, только если `RETURN` пуст (см. врезку, п. 3).

> **Изменено 2026-09-22.** Раньше здесь было «`RETURN` — для классического дизайнера,
> `ADDITIONAL_RESULT` — для роботов, указывать оба». Курс и ядро этого не подтверждают: для дизайнера
> хватает `RETURN`, ключ `RETURN` в `ADDITIONAL_RESULT` не используется. В роботах проверить отдельно.

## Проверка результата
- Действие видно в разделе «Задания» дизайнера БП (после сброса кэша).
- Задание появляется в штатном попапе и в списке заданий.
- Тело задания **непустое** и в классическом попапе, и в карточке смарт-процесса.
- Отмена БП удаляет задание, завершение — закрывает его.

## Подводные камни

| Симптом | Причина | Решение |
|---|---|---|
| Действия нет в дизайнере | нестандартный `CATEGORY['ID']` (например, `constructs`) или лишний `OWN_ID` | код из таблицы шага 5, например `['ID' => 'task']`, затем сбросить кэш |
| Своё действие подменило штатное во всех шаблонах | папка названа как штатная (`logactivity`, `task2activity` — так в примерах курса) | префикс вендора в имени: ядро берёт первую найденную папку, `/local` — раньше `/bitrix` |
| Кнопка «Делегировать» осталась | `DELEGATION_TYPE => 0` — это «только подчинённым» | `CBPTaskDelegationType::None` (`2`) |
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
