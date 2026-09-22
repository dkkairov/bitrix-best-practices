---
title: "CBPActivityCondition — условия бизнес-процесса"
type: entity
module: bizproc
edition: box
status: verified
provenance: mixed
verified: "2026-09-22 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Модуль Бизнес-процессы — Свои условия; курс 57 (уроки 3789, 3792); код стенда (bizproc 26.1075.0): getJoiner, ConditionGroup"
tags: [bizproc, условие, класс, цикл, ветвление]
sources: ["[[source-devbook-bizproc]]", "[[source-course57-actions-core]]"]
related: ["[[entity-cbp-activity]]", "[[concept-bizproc-engine]]", "[[entity-bizproc-field-type]]", "[[entity-bizproc-activity-description]]", "[[concept-bizproc-bpt-format]]"]
aliases: ["bitrix24-cbp-activity-condition"]
updated: "2026-09-22"
---

# `CBPActivityCondition`

**Что это:** базовый класс условий для блоков «Условие» и «Цикл»
([Свои условия](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Svoi_uslovia.html)).
Отдельная ветка от [[entity-cbp-activity|`CBPActivity`]]: условие — не полноценное активити, и
окружение процесса ему доступно только через родительское активити.

> **Уточнено 2026-09-21 при сверке с книгой.** Книга прямо говорит только, что у условия нет
> штатной записи в журнал; доступ к окружению она показывает через `$ownerActivity->workflow`.
> Перечень «чего нет» ниже — вывод команды. Уточнена сигнатура диалога.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | базовый класс |
| Модуль | `bizproc` |
| Регистрация | `TYPE = 'condition'` в `.description.php` (достаточно `NAME` + `TYPE`, при необходимости `FILTER`) — [[entity-bizproc-activity-description]] |
| Каталоги и имя класса | те же пять каталогов поиска, что у действий; класс — `CBP` + код каталога (регистр неважен) |
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

## Штатные условия и связка «и»/«или»
- Виды в дизайнере: «Смешанное», «PHP код», «Значение переменной», «Поле документа», «Истина»; «PHP
  код» задаёт только администратор, код возвращает `true` или `false`. Константы и глобальные значения
  доступны только в «Смешанном»
  ([урок 3789](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=3789)). В ядре это
  классы `fieldcondition`, `propertyvariablecondition`, `mixedcondition`, `truecondition`,
  `codecondition` в `/bitrix/activities/bitrix/`.
- Штатные условия собирают строки в `Bizproc\Activity\ConditionGroup`. Связка берётся из
  `getJoiner()`: пусто или `0` — «и», иначе «или». Группы делятся по «или», внутри — «и»: «и» сильнее
  «или». Первая группа изначально истинна, поэтому «или» в первой строке делает условие истинным всегда
  (код ядра, bizproc 26.1075.0).
- Курс предупреждает: проверку «не равно» для нескольких значений строят только через «и» — с «или»
  она всегда истинна. «Содержится в» и «Содержит» чувствительны к регистру.
- Своё условие может унаследовать `getJoiner()` и `ConditionGroup`, чтобы вести себя как штатные
  (вывод команды).

## Чего у условий нет

- **Нет штатной записи в журнал** (`WriteToTrackingService`) — книга даёт полифил ниже.
- Окружение процесса (сервисы движка, переменные, документ) — через `$ownerActivity`:
  `$ownerActivity->workflow`, методы родительского активити. Что у самого условия нет
  `$this->workflow`, `parseValue`, `getVariable` и т. п. — вывод команды.

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

- `GetPropertiesDialog(...)` — 10 параметров, как и у действия, но набор другой: есть
  `$defaultValue`, нет `$activityName`, вместо `$form` — `$popupWindow`.
- `ValidateProperties($values = null, CBPWorkflowTemplateUser $user = null): array` — массив
  ошибок. **Не забыть `array_merge` с `parent::ValidateProperties()`** — иначе потеряются
  проверки базового класса.
- `GetPropertiesDialogValues(...)` — 7 параметров; возвращает обработанные значения (либо `null`
  при ошибке), а не записывает их в шаблон, как у действия.

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
