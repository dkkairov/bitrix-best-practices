---
title: "\\Bitrix\\Bizproc\\FieldType — типы значений БП"
type: entity
module: bizproc
edition: box
status: verified
provenance: documented
verified: "2026-06-01 / документация модуля bizproc (dev.1c-bitrix.ru)"
tags: [bizproc, типы, поля, класс, диалог-настроек]
sources: []
related: ["[[entity-cbp-activity]]", "[[concept-bizproc-engine]]", "[[recipe-bizproc-custom-task-activity]]"]
aliases: ["bitrix24-bizproc-fieldtype"]
updated: "2026-09-18"
---

# `\Bitrix\Bizproc\FieldType`

**Что это:** перечисление типов значений бизнес-процесса. Используется в `.description.php`
(ключ `RETURN`), в `getPropertiesDialogMap()` и в полях формы настроек действия.

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
зависит от документа, а не известен заранее (используется в `ADDITIONAL_RESULT`).

## Подводные камни

- **`ExternalExtract` меняет формат значения.** Без него в свойстве окажется `user_1`, а не `1` —
  и последующее сравнение с ID сотрудника молча не сработает.
- Тип в `RETURN` описания действия и тип в `getPropertiesDialogMap()` должны совпадать: расхождение
  проявится только в дизайнере, при попытке подставить результат в следующий шаг.
- Не путать с `\Bitrix\Main\UI\Filter\DateType` — это другое семейство констант, для подтипов дат
  в фильтре ([[entity-filter-field-adapter]]).

## Связанное
- [[entity-cbp-activity]] — где типы объявляются

[← Бизнес-процессы](_index-bizproc.md)
