---
title: "CBPActivity и Bizproc\\Activity\\BaseActivity"
type: entity
module: bizproc
edition: box
status: verified
provenance: documented
verified: "2026-06-01 / документация модуля bizproc (dev.1c-bitrix.ru, курс 57)"
tags: [bizproc, активити, класс, диалог-настроек, журнал]
sources: []
related: ["[[concept-bizproc-engine]]", "[[recipe-bizproc-custom-task-activity]]", "[[entity-bizproc-field-type]]", "[[entity-cbp-activity-condition]]", "[[entity-main-result]]"]
aliases: ["bitrix24-cbp-activity"]
updated: "2026-09-18"
---

# `CBPActivity` и `BaseActivity`

**Что это:** базовые классы любого действия бизнес-процесса. `CBPActivity` — корневой (старый
C-API), `\Bitrix\Bizproc\Activity\BaseActivity` — его D7-наследник с удобствами.

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

**Возврат `Execute()`:** `Closed` — завершено; `Executing` — ещё работает (событийные действия);
`Faulting` — критическая ошибка, процесс прекращается.

## Доступ к окружению

`parseValue($value, $convertToType = null)` — разбор выражений; `getVariable` / `setVariable`;
`getConstant`; `getDocumentId()` / `getDocumentType()`; `getWorkflowTemplateId()`;
`getRootActivity()`; сервисы — `$this->workflow->GetService('DocumentService' | 'TrackingService')`.

## Журнал процесса

```php
\CBPActivity::WriteToTrackingService($message = '', $modifiedBy = 0, $trackingType = -1): void
```

Отображаемые типы `CBPTrackingType`: `Error`, `Report`, `Custom`, `FaultActivity`. Остальные
константы в журнале не видны, но разделять их семантически полезно.

## `BaseActivity` — что добавляет D7-наследник

| Возможность | Смысл |
|---|---|
| `internalExecute(): ErrorCollection` вместо `Execute()` | статус выполнения выставляется автоматически |
| `protected static $requiredModules = ['crm']` | автоматический `Loader::includeModule` перед запуском |
| `getFileName(): string` | обязательный статический метод, возвращает `__FILE__` |
| `$this->log()` / `$this->logError()` | сахар над `WriteToTrackingService` |
| `getPropertiesDialogMap()` | декларативное описание формы настроек вместо HTML |
| `checkProperties(): ErrorCollection` | отдельная стадия валидации |
| `$this->preparedProperties[<key>]` | альтернативный способ вернуть результат |

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
- **`$this->Execution` в действии не существует.** Прерывать через `throw new Exception(...)` —
  движок поймает и вызовет `HandleFault`. Вызов `$this->Execution->FaultActivity()` даст
  «Call to a member function on null».
- Диалог настроек грузится AJAX-ом: **inline `<script>` исполняется ненадёжно**, особенно в
  редакторе автоматизации CRM. Сложное редактирование выносить на отдельную страницу — подробно
  в [[recipe-bizproc-custom-task-activity]].

## Связанное
- [[concept-bizproc-engine]] — типы активити и где они лежат
- [[entity-cbp-activity-condition]] — параллельный класс для условий

[← Бизнес-процессы](_index-bizproc.md)
