---
title: "CBPTaskService — задания бизнес-процессов"
type: entity
module: bizproc
edition: box
status: verified
provenance: empirical
verified: "2026-07-13 / коробка: классический попап заданий и новый UI в карточке смарт-процесса; сверено с «Книгой разработчика Bitrix24» 2026-09-21 — API сервиса в книге не разобрано"
tags: [bizproc, задание, task, класс, делегирование]
sources: ["[[source-devbook-bizproc]]"]
related: ["[[recipe-bizproc-custom-task-activity]]", "[[entity-cbp-activity]]", "[[concept-bizproc-engine]]"]
aliases: ["bitrix24-cbptaskservice"]
updated: "2026-09-21"
---

# `CBPTaskService`

**Что это:** сервис движка БП для заданий — интерактивных шагов, которые ждут ответа человека.
Задание, созданное через него, попадает в штатный список заданий и в живую ленту.

> **Сверено с книгой 2026-09-21.** Книга описывает задания только в классификации действий: это
> событийное действие с дополнительными методами, которое **наследует `CBPCompositeActivity`** и
> реализует те же интерфейсы, что событийные
> ([Действия → классификация](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Dejstvia.html#klassifikacia-dejstvij)).
> Сам `CBPTaskService` книга не разбирает, поэтому всё ниже — эмпирика команды. Базовый класс в нашем
> рецепте — `CBPActivity`; расхождение и решение — в [[recipe-bizproc-custom-task-activity]].

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | сервис движка |
| Модуль | `bizproc` |
| Получение | `$this->workflow->GetService('TaskService')` внутри активити |
| Требует | активити с `IBPEventActivity` + `IBPActivityExternalEventListener` (так и по книге) |
| Базовый класс задания | по книге — `CBPCompositeActivity`; у команды — `CBPActivity` (проверено на стенде) |

## Методы, которые нужны на практике

| Метод | Когда вызывается |
|---|---|
| `CreateTask(array $fields): int` | в `Subscribe()` — создать задание |
| `MarkCompleted($taskId, $userId, CBPTaskUserStatus::Ok)` | в `OnExternalEvent()` — после ответа человека |
| `Update($taskId, ['STATUS' => …])` | в `Unsubscribe()`, если задание завершилось штатно |
| `DeleteTask($taskId)` | в `Unsubscribe()`, если процесс отменён |
| `GetList(...)` | получить данные задания, в том числе `DOCUMENT_ID` по `TASK_ID` |

Разделение в `Unsubscribe()` делается по внутреннему флагу: статус не выставлен — процесс
отменяют, задание надо **удалить**; выставлен — обновить.

## Важные поля `CreateTask`

| Поле | Смысл |
|---|---|
| `USERS` | список исполнителей (через `CBPHelper::ExtractUsers`) |
| `WORKFLOW_ID`, `ACTIVITY`, `ACTIVITY_NAME` | привязка к процессу и активити |
| `NAME`, `DESCRIPTION` | что человек видит в списке заданий |
| **`PARAMETERS`** | произвольные данные для формы задания |
| `DELEGATION_TYPE` | `0` — убрать кнопку «Делегировать» |
| `IS_INLINE` | режим отображения |
| `DOCUMENT_NAME` | название документа в списке |

**`PARAMETERS` — ключевой приём.** `ShowTaskForm()` и `PostTaskForm()` читают данные из
`$arTask['PARAMETERS']` и не обращаются к остановленному процессу. Альтернатива — перезагружать
workflow и доставать свойства активити — работает, но хрупка: в новом UI смарт-процессов может
вернуть пустые свойства, и человек увидит пустое задание.

## Подводные камни

- **`ACTIVITY` должен совпадать с именем класса без префикса `CBP`.** Не совпало — задание не
  появится в попапе, без каких-либо ошибок.
- **Пробуждать процесс только статически:** `CBPRuntime::SendExternalEvent(...)`. Вызов как метода
  экземпляра не разбудит процесс.
- **Новый UI кладёт форму задания в `<div>`, а не в `<table>`.** Вёрстка `tasktemplate.php` с
  одинокими `<tr>` даст пустое тело задания при видимых кнопках.
- **Без `Cancel()` и `HandleFault()` в активити** задание останется висеть у людей после отмены
  процесса.
- По умолчанию делегирование разрешено глобальной настройкой модуля — если оно ломает вашу
  логику, `DELEGATION_TYPE => 0`.

## Открытые вопросы
- Чем именно отличается отображение при `IS_INLINE` `'Y'` и `'N'`.
- От какого класса наследуются штатные задания дистрибутива («Утверждение», «Запрос доп.
  информации») — сверить по коду `/bitrix/modules/bizproc/activities/` на своей версии, чтобы решить
  спор «`CBPActivity` или `CBPCompositeActivity`».

## Связанное
- [[recipe-bizproc-custom-task-activity]] — полный рецепт задания-активити
- [[entity-cbp-activity]] — класс, из которого вызывается сервис

[← Бизнес-процессы](_index-bizproc.md)
