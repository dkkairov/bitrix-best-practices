---
title: "\\Bitrix\\Bizproc\\FieldType — типы значений БП"
type: entity
module: bizproc
edition: box
status: verified
provenance: documented
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Модуль Бизнес-процессы — Действия (RETURN, ADDITIONAL_RESULT), Свои действия (поля диалога)"
tags: [bizproc, типы, поля, класс, диалог-настроек]
sources: ["[[source-devbook-bizproc]]"]
related: ["[[entity-cbp-activity]]", "[[concept-bizproc-engine]]", "[[recipe-bizproc-custom-task-activity]]", "[[entity-bizproc-activity-description]]"]
aliases: ["bitrix24-bizproc-fieldtype"]
updated: "2026-09-21"
---

# `\Bitrix\Bizproc\FieldType`

**Что это:** перечисление типов значений бизнес-процесса. Используется в `.description.php`
(ключ `RETURN`), в `getPropertiesDialogMap()` и в полях формы настроек действия
([Свои действия → поля диалога](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Svoi_dejstvia.html#pola-dialoga);
атрибуция и пара «тип результата» исправлены при сверке с книгой 2026-09-21).

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | класс-перечисление |
| Модуль | `bizproc` |

## Базовые типы

| Константа | Значение | Что это |
|---|---|---|
| `BOOL` | `bool` | булево |
| `INT` | `int` | целое |
| `DOUBLE` | `double` | вещественное |
| `STRING` | `string` | однострочная строка |
| `TEXT` | `text` | многострочный текст |
| `DATE` | `date` | дата |
| `DATETIME` | `datetime` | дата и время |
| `TIME` | `time` | время |
| `FILE` | `file` | файл |
| `SELECT` | `select` | список с настраиваемыми пунктами |
| `INTERNALSELECT` | `internalselect` | внутренний системный список |
| `USER` | `user` | сотрудник или несколько |

Базовые типы одинаковы для всех документов. Кроме них бывают **пользовательские** типы — они
определяются документом, над которым запущен процесс, и меняются вместе с ним.

## Расширенные настройки

**`SELECT`:**

```php
'Settings' => [
    'ShowEmptyValue' => true,
    'Groups' => [
        ['name' => 'Группа 1', 'items' => ['k1' => 'Значение 1']],
        ['name' => 'Группа 2', 'items' => ['k2' => 'Значение 2']],
    ],
]
```

**`USER`:** `ExternalExtract` (отдавать сразу ID `1` вместо префиксного `user_1`),
`allowEmailUsers`, `groups`.

## Динамическая типизация результата

`FieldType::normalizeProperty($documentField): array` берёт описание поля документа и возвращает
структуру, пригодную для регистрации свойства активити. Нужно, когда состав возвращаемых значений
зависит от документа, а не известен заранее (используется в `ADDITIONAL_RESULT`). Схема по книге в
три шага: в `.description.php` — `ADDITIONAL_RESULT` с кодом свойства-карты; в
`GetPropertiesDialogValues()` — построить карту «код → описание типа»; в `Execute()` — объявить типы
через `SetPropertiesTypes($map)` и заполнить значения
([ADDITIONAL_RESULT](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Dejstvia.html#additional-result),
[[entity-bizproc-activity-description]]).

## Подводные камни

- **`ExternalExtract` меняет формат значения.** Без него в свойстве окажется `user_1`, а не `1` —
  и последующее сравнение с ID сотрудника молча не сработает.
- **Пара для `RETURN` — `SetPropertiesTypes()`, а не `getPropertiesDialogMap()`.** Карта формы
  описывает **входные** поля; тип результата, объявленный в `RETURN`, регистрируется в классе
  действия через `SetPropertiesTypes()` (книга, пример `helloworldactivity`). Результат в карту формы
  не кладите — он станет полем ввода (вывод команды). Раньше здесь было сказано, что тип в `RETURN`
  должен совпадать с картой формы, — исправлено при сверке 2026-09-21.
- У `SELECT` без `Groups` пункты берутся из `Options`; своя отрисовка поля —
  `renderFieldControl(..., FieldType::RENDER_MODE_DESIGNER)`.
- Не путать с `\Bitrix\Main\UI\Filter\DateType` — это другое семейство констант, для подтипов дат
  в фильтре ([[entity-filter-field-adapter]]).

## Связанное
- [[entity-cbp-activity]] — где типы объявляются

[← Бизнес-процессы](_index-bizproc.md)
