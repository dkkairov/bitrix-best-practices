---
title: "CBPActivity и Bizproc\\Activity\\BaseActivity"
type: entity
module: bizproc
edition: box
status: verified
provenance: mixed
verified: "2026-09-22 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Модуль Бизнес-процессы — Действия, Окружение, PHP код, Свои действия; курс 57 (уроки 3471, 3470, 23034, 13378); код и прогон на стенде (коробка, bizproc 26.1075.0, 2026-09-22): BaseActivity::execute, что ошибка, исключение, Faulting и фатальная ошибка PHP делают с процессом; inline-скрипты диалога — эмпирика команды"
tags: [bizproc, активити, класс, диалог-настроек, журнал]
sources: ["[[source-devbook-bizproc]]", "[[source-course57-developer]]"]
related: ["[[concept-bizproc-engine]]", "[[recipe-bizproc-custom-task-activity]]", "[[entity-bizproc-field-type]]", "[[entity-cbp-activity-condition]]", "[[entity-main-result]]", "[[entity-bizproc-activity-description]]", "[[entity-cbp-task-service]]", "[[recipe-bizproc-custom-activity-baseactivity]]"]
aliases: ["bitrix24-cbp-activity"]
updated: "2026-09-22"
---

# `CBPActivity` и `BaseActivity`

**Что это:** базовые классы любого действия бизнес-процесса. `CBPActivity` — корневой (старый
C-API), `\Bitrix\Bizproc\Activity\BaseActivity` — его D7-наследник с удобствами
([Действия](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Dejstvia.html),
[Свои действия](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Svoi_dejstvia.html)).

> **Сверено с книгой 2026-09-21.** Атрибуция исправлена (материал — из книги; курс 57 она даёт только
> как ссылку). Добавлены: классический диалог настроек, окружение по книге (параметры, геттер
> `getRuntimeProperty`, `DocumentService` без проверки прав), условия возврата результата, код на cron.
> Прерывание через `throw` и ненадёжность inline-скриптов помечены как эмпирика команды; штатный путь по
> книге — статус `Faulting` / `ErrorCollection`. **2026-09-22, стенд:** ни `Faulting`, ни исключение
> процесс не останавливают — раздел «Ошибки и остановка процесса».

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | базовые классы |
| Модуль | `bizproc` |
| Где живёт наследник | `/local/activities/custom/<имя без CBP в нижнем регистре>/` |
| Сопутствующие | `CBPActivityExecutionStatus`, `CBPTrackingType`, `CBPRuntime` |

## `CBPActivity` — минимальный наследник

```php
class CBPMyActivity extends \CBPActivity
{
    public function __construct($name)
    {
        parent::__construct($name);
        $this->arProperties = ['Title' => '', 'MyText' => ''];
    }

    public function Execute()
    {
        // логика
        return \CBPActivityExecutionStatus::Closed;
    }
}
```

Свойства читаются и пишутся магией: `$this->MyText`. Значения по умолчанию — в `$arProperties`.
Параметры процесса — это свойства корневого действия (`GetRootActivity()`); свойство выполненного
раньше действия доступно как `{=ИмяДействия:Свойство}`
([урок 3471](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=3471)). Составное
действие запускает дочерние через `$this->workflow->ExecuteActivity()` и подписывается на их
закрытие (`AddStatusChangeHandler`); пример курса в
[уроке 3470](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=3470) устарел (неверный
интерфейс, `protected OnEvent`) — образец лучше брать из штатного `CBPApproveActivity`.

**Возврат `Execute()`:** `Closed` — завершено; `Executing` — ещё работает (событийные действия). Книга
называет третий вариант — `Faulting` как критическую ошибку, после которой процесс прекращается. На
стенде это не так: ядро принимает от `Execute()` только `Closed`, `Executing` и `Cancelled`, на
остальное пишет в журнал `InvalidExecutionStatus` и идёт дальше (раздел «Ошибки и остановка
процесса»).

**Классический диалог настроек** (без `BaseActivity`) — статические `GetPropertiesDialog(...)`
(10 параметров; форма рисуется через `CBPRuntime::ExecuteResourceFile`) и
`GetPropertiesDialogValues(...)` (8 параметров; действие в шаблоне находят через
`CBPWorkflowTemplateLoader::FindActivityByName`, метод возвращает `bool`). Разметка —
[[entity-bizproc-activity-description|рядом с `.description.php`]], в `properties_dialog.php`.

## Доступ к окружению

([Окружение](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Okruzenie.html))
- `parseValue($value, $convertToType = null)` — разбор выражений (функции, калькулятор, несколько
  выражений в строке); универсальный геттер `getRuntimeProperty($object, $field, $ownerActivity)` →
  `[описание, значение]` с источниками `\Bitrix\Bizproc\Workflow\Template\SourceType::*`.
- Параметры шаблона — `$this->getRootActivity()->ИмяПараметра`; переменные — `getVariable` /
  `setVariable` (множественные значения — массивом, пользователи — `user_N`); константы — только
  `getConstant`.
- Документ — `$this->workflow->getService('DocumentService')->getDocument($this->getDocumentId())`:
  сервис **кэширует, грузит лениво и не проверяет права** — проверку прав делайте сами.
- Прочее: `getDocumentId()` / `getDocumentType()`, `getWorkflowTemplateId()`, `getTemplateUserId()`,
  `getRootActivity()`; глобальные переменные и константы — [[entity-bizproc-globals-manager]].

## Журнал процесса

```php
\CBPActivity::WriteToTrackingService($message = '', $modifiedBy = 0, $trackingType = -1): void
```

`$modifiedBy = 0` — система. Текст сообщения сам проходит подстановку выражений. В журнале видны
четыре типа `CBPTrackingType`: `Error`, `Report`, `Custom`, `FaultActivity` — и выглядят они
одинаково; констант в классе больше, но остальные в журнале не показываются
([журналирование](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/PHP_kod.html#zurnalirovanie)).

Константы в ядре (bizproc 26.1075.0):

| Класс | Значения |
|---|---|
| `CBPTrackingType` | `Unknown` 0, `ExecuteActivity` 1, `CloseActivity` 2, `CancelActivity` 3, `FaultActivity` 4, `Custom` 5, `Report` 6, `AttachedEntity` 7, `Trigger` 8, `Error` 9, `Debug` 10, `DebugAutomation` 11, `DebugDesigner` 12, `DebugLink` 13 |
| `CBPActivityExecutionStatus` | `Initialized` 0, `Executing` 1, `Canceling` 2, `Closed` 3, `Faulting` 4, `Cancelled` 5 |
| `CBPActivityExecutionResult` | `None` 0, `Succeeded` 1, `Canceled` 2, `Faulted` 3, `Uninitialized` 4 |

## `BaseActivity` — что добавляет D7-наследник

| Возможность | Смысл |
|---|---|
| `internalExecute(): ErrorCollection` вместо `Execute()` | статус выполнения выставляется автоматически |
| `protected static $requiredModules = ['crm']` | автоматический `Loader::includeModule` перед запуском |
| `getFileName(): string` | обязательный статический метод, возвращает `__FILE__` |
| `$this->log()` / `$this->logError()` | сахар над `WriteToTrackingService` |
| `getPropertiesDialogMap()` | декларативное описание формы настроек вместо HTML |
| `checkProperties(): ErrorCollection` | проверки параметров **во время выполнения** (с доступом к `$preparedProperties`), не валидация формы при сохранении |
| `$this->setProperty($key, $value)` или `$this->preparedProperties[<key>]` | вернуть результат; ключ объявлен в `RETURN` `.description.php`, тип — через `SetPropertiesTypes()`. `setProperty()` (есть в bizproc 26.1075.0) пишет и в свойства, и в подготовленные значения |
| `validateProperties()` по карте | поле карты с `Required` должно быть заполнено при сохранении и импорте шаблона — «Не заполнено обязательное поле: …» (стенд) |
| форма без `properties_dialog.php` | нет ни его, ни `robot_properties_dialog.php` — ядро само выводит поля карты в дизайнере и в роботах |

Результат можно вернуть и как `$this->Key` — тогда в `arProperties` ключ должен быть `null`.
Результат из `RETURN` дизайнер показывает во «Вставке значения» сразу; `ADDITIONAL_RESULT` — для
свойств-карт с результатами, которые определяются на ходу (противоречие книги снято курсом и кодом
ядра — [[entity-bizproc-activity-description]]).

```php
protected function internalExecute(): \Bitrix\Main\ErrorCollection
{
    $errors = parent::internalExecute();
    if ($hasError) {
        $errors->setError(new \Bitrix\Main\Error('Текст ошибки'));
        return $errors;
    }
    $this->setProperty('Text', 'Результат');   // или $this->preparedProperties['Text'] = …
    $this->log('Готово');
    return $errors;
}
```

## Декларативная форма настроек

```php
public static function getPropertiesDialogMap(?PropertiesDialog $dialog = null): array
{
    return ['Subject' => [
        'Name'      => 'Объект',
        'FieldName' => 'subject',
        'Type'      => \Bitrix\Bizproc\FieldType::STRING,
        'Required'  => true,
        'Default'   => '',
        'Options'   => [],
    ]];
}
```

Ключи поля: `Type` ([[entity-bizproc-field-type]]), `Name`, `Description`, `Required`, `Multiple`,
`Options`, `Settings`, `Default`, `FieldName`.

`Settings` для `SELECT`: `ShowEmptyValue`, `Groups`. Для `USER`: `ExternalExtract` (сразу ID
вместо `user_1`), `allowEmailUsers`, `groups`.

## Ошибки и остановка процесса

Прогон на стенде (коробка, bizproc 26.1075.0, 2026-09-22): процесс из двух шагов — проверяемое
действие и «Запись в отчет» после него:

| Что сделало действие | Шаг (`executionResult`) | Журнал процесса | Процесс |
|---|---|---|---|
| `BaseActivity`: вернуло ошибку в `ErrorCollection` | выполнен (`Succeeded`) | ошибка (`Error`) с текстом | идёт дальше |
| бросило `Exception` | закрыт с ошибкой (`Faulted`) | `FaultActivity` с текстом исключения | идёт дальше |
| `CBPActivity`: вернуло `Faulting` из `Execute()` | закрыт с ошибкой (`Faulted`) | `FaultActivity`: `InvalidExecutionStatus` | идёт дальше |
| упало с фатальной ошибкой PHP (`Error`; на стенде — вызов метода у `null`) | не закрыт | ничего | хит падает, процесс остаётся «Выполняется» |

Почему так (код ядра): движок ловит `Exception` на каждом шаге, вызывает у действия `HandleFault()`,
по умолчанию это `Cancel()` → действие закрывается с результатом `Faulted`, и родительская
последовательность запускает следующий шаг — ошибка вверх не передаётся. `Error` не наследует
`Exception`, поэтому проходит мимо движка в код, который запустил процесс. `TypeError` и ошибка
разбора кода (`ParseError`) — тоже `Error` (правило PHP).

- **Остановить процесс** — в шаблоне: признак ошибки результатом действия, после него условие и
  «Прерывание процесса». Штатное «Прерывание процесса» в коде вызывает `CBPDocument::TerminateWorkflow()`
  для текущего процесса и бросает исключение, чтобы свернуть выполнение (код ядра, не проверялось).
- **Фатальные ошибки ловить самим:** `try/catch (\Throwable)` и запись в журнал — иначе процесс
  зависнет без следа. Отсюда и правило книги для «PHP кода» ([[antipattern-bizproc-php-code-activity]]).
- `Cancel()` и `HandleFault()` у действия с заданием снимают задание при ошибке
  ([[recipe-bizproc-custom-task-activity]]).

> **Изменено 2026-09-22.** Прежняя редакция (по книге и чтению кода) говорила, что `Faulting` из
> `Execute()` или исключение останавливают процесс, — прогон на стенде это опроверг. Вопрос «прерывают
> ли процесс ошибки `ErrorCollection`» закрыт: нет.

## Подводные камни

- **`$requiredModules` при наследовании перекрывается, а не дополняется** — копируйте весь список
  целиком, иначе потеряете модуль родителя.
- Если модуль из `$requiredModules` не подключился, действие закрывается со статусом `Closed`, а
  `internalExecute()` **не вызывается вовсе** — снаружи выглядит как «действие ничего не сделало».
- **Код может выполняться на cron:** текущего пользователя и ID сайта может не быть или они окажутся
  чужими — не полагайтесь на глобальные `$USER` и `SITE_ID`
  ([правила](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/PHP_kod.html#pravila)).
  Курс добавляет: права не повышать через `$USER->Authorize()`, у роботов и триггеров нет
  пользователя-инициатора ([урок 13378](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=13378)).
- **Ни ошибка, ни исключение процесс не останавливают, фатальная ошибка PHP его вешает** — таблица в
  разделе «Ошибки и остановка процесса». **Эмпирика команды:** `$this->Execution` в действии не
  существует — вызов `$this->Execution->FaultActivity()` даст «Call to a member function on null».
- Диалог настроек грузится AJAX-ом: **inline `<script>` исполняется ненадёжно**, особенно в
  редакторе автоматизации CRM (эмпирика команды). Сложное редактирование выносить на отдельную
  страницу — подробно в [[recipe-bizproc-custom-task-activity]].

## Связанное
- [[recipe-bizproc-custom-activity-baseactivity]] — своё действие на `BaseActivity` целиком
- [[concept-bizproc-engine]] — типы активити и где они лежат
- [[entity-cbp-activity-condition]] — параллельный класс для условий

[← Бизнес-процессы](_index-bizproc.md)
