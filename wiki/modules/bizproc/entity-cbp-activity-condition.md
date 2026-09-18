---
title: "CBPActivityCondition — условия бизнес-процесса"
type: entity
module: bizproc
edition: box
status: verified
provenance: documented
verified: "2026-06-01 / документация модуля bizproc, раздел своих условий (dev.1c-bitrix.ru)"
tags: [bizproc, условие, класс, цикл, ветвление]
sources: ["[[source-devbook-bizproc]]"]
related: ["[[entity-cbp-activity]]", "[[concept-bizproc-engine]]", "[[entity-bizproc-field-type]]"]
aliases: ["bitrix24-cbp-activity-condition"]
updated: "2026-09-18"
---

# `CBPActivityCondition`

**Что это:** базовый класс условий для блоков «Условие» и «Цикл». Отдельная ветка от
[[entity-cbp-activity|`CBPActivity`]] — и это главный источник путаницы: у условий **нет** доступа
к окружению процесса.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | базовый класс |
| Модуль | `bizproc` |
| Регистрация | `TYPE = 'condition'` в `.description.php` |
| Главный метод | `Evaluate(\CBPActivity $ownerActivity): bool` |

```php
class CBPDiceCondition extends \CBPActivityCondition
{
    public $number = 6;

    public function __construct($activityData)      // массив настроек, а НЕ $name
    {
        $this->number = $activityData['Number'];
    }

    public function Evaluate(\CBPActivity $ownerActivity): bool
    {
        return random_int(1, 6) === (int)$this->number;
    }
}
```

`$ownerActivity` — родительское активити («Условие» или «Цикл»). `true` — условие выполнено.

## Чего у условий нет

- **Нет `$this->workflow`** — прямого доступа к сервисам движка.
- **Нет `WriteToTrackingService`** — писать в журнал напрямую нельзя.
- **Нет `parseValue`, `getVariable`, `setVariable`, `getConstant`, `getDocumentId`** — всё только
  через `$ownerActivity`.

Полифил записи в журнал (заодно самая полная известная сигнатура `TrackingService::Write`):

```php
protected function writeToTrackingService(
    \CBPActivity $ownerActivity, $message = '', $modifiedBy = 0, $trackingType = -1
) {
    $trackingService = $ownerActivity->workflow->GetService('TrackingService');
    if ($trackingType < 0) { $trackingType = \CBPTrackingType::Custom; }

    $trackingService->Write(
        $ownerActivity->GetWorkflowInstanceId(),
        $trackingType,
        $ownerActivity->getName(),
        $ownerActivity->executionStatus,
        $ownerActivity->executionResult,
        ($ownerActivity->IsPropertyExists('Title') ? $ownerActivity->Title : ''),
        $message,
        $modifiedBy
    );
}
```

## Статические методы диалога

- `GetPropertiesDialog(...)` — 10 параметров; в отличие от активити, здесь есть `$defaultValue`.
- `ValidateProperties($values = null, CBPWorkflowTemplateUser $user = null): array` — массив
  ошибок. **Не забыть `array_merge` с `parent::ValidateProperties()`** — иначе потеряются
  проверки базового класса.
- `GetPropertiesDialogValues(...): array|null` — обработанные значения либо `null` при ошибке.

`CBPWorkflowTemplateUser::CurrentUser` — маркер «текущий пользователь»:
`new CBPWorkflowTemplateUser(CBPWorkflowTemplateUser::CurrentUser)`.

## Подводные камни

- **Конструктор принимает массив настроек, а не имя** — в отличие от `CBPActivity`. Скопированный
  из действия конструктор молча сломает условие.
- **`properties_dialog.php` условия открывается внутри существующей таблицы** — содержимое должно
  быть `<tr><td>…</td></tr>`. Обратите внимание: у формы **задания** правило противоположное
  ([[recipe-bizproc-custom-task-activity]]), их легко перепутать.
- Имена HTML-полей должны совпадать с ключами массива настроек (`name='Number'` ↔
  `$activityData['Number']`).

## Связанное
- [[entity-cbp-activity]] — параллельный класс для действий
- [[concept-bizproc-engine]] — где условия стоят в движке

[← Бизнес-процессы](_index-bizproc.md)
