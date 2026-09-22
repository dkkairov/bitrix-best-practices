---
title: "CBPActivity и Bizproc\\Activity\\BaseActivity"
type: entity
module: bizproc
edition: box
status: verified
provenance: mixed
verified: "2026-09-22 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Модуль Бизнес-процессы — Действия, Окружение, PHP код, Свои действия; курс 57 (уроки 3471, 3470, 23034, 13378); код стенда (bizproc 26.1075.0): BaseActivity::execute; inline-скрипты диалога — эмпирика команды"
tags: [bizproc, активити, класс, диалог-настроек, журнал]
sources: ["[[source-devbook-bizproc]]", "[[source-course57-developer]]"]
related: ["[[concept-bizproc-engine]]", "[[recipe-bizproc-custom-task-activity]]", "[[entity-bizproc-field-type]]", "[[entity-cbp-activity-condition]]", "[[entity-main-result]]", "[[entity-bizproc-activity-description]]", "[[entity-cbp-task-service]]"]
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
> книге — статус `Faulting` / `ErrorCollection`.

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

**Возврат `Execute()`:** `Closed` — завершено; `Executing` — ещё работает (событийные действия);
`Faulting` — критическая ошибка, процесс прекращается.

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

## `BaseActivity` — что добавляет D7-наследник

| Возможность | Смысл |
|---|---|
| `internalExecute(): ErrorCollection` вместо `Execute()` | статус выполнения выставляется автоматически |
| `protected static $requiredModules = ['crm']` | автоматический `Loader::includeModule` перед запуском |
| `getFileName(): string` | обязательный статический метод, возвращает `__FILE__` |
| `$this->log()` / `$this->logError()` | сахар над `WriteToTrackingService` |
| `getPropertiesDialogMap()` | декларативное описание формы настроек вместо HTML |
| `checkProperties(): ErrorCollection` | проверки параметров **во время выполнения** (с доступом к `$preparedProperties`), не валидация формы при сохранении |
| `$this->preparedProperties[<key>]` | способ вернуть результат; ключ должен быть объявлен в `RETURN` `.description.php`, тип — через `SetPropertiesTypes()` |

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
    $this->preparedProperties['Text'] = 'Результат';
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
- **Ошибка в `ErrorCollection` процесс не останавливает.** По коду ядра (`BaseActivity::execute`,
  bizproc 26.1075.0) ошибки из `checkProperties()` и `internalExecute()` только пишутся в журнал
  (`logError`), действие закрывается, процесс идёт дальше. Остановить процесс — статусом `Faulting` из
  `Execute()` у `CBPActivity` или исключением: движок поймает его и вызовет `HandleFault`.
  **Эмпирика команды:** `$this->Execution` в действии не существует — вызов
  `$this->Execution->FaultActivity()` даст «Call to a member function on null».

> **Изменено 2026-09-22.** Раньше вопрос «прерывают ли ошибки `ErrorCollection` процесс» был открыт —
> ответ взят из кода ядра.
- Диалог настроек грузится AJAX-ом: **inline `<script>` исполняется ненадёжно**, особенно в
  редакторе автоматизации CRM (эмпирика команды). Сложное редактирование выносить на отдельную
  страницу — подробно в [[recipe-bizproc-custom-task-activity]].

## Связанное
- [[concept-bizproc-engine]] — типы активити и где они лежат
- [[entity-cbp-activity-condition]] — параллельный класс для условий

[← Бизнес-процессы](_index-bizproc.md)
