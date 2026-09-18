---
title: "\\Bitrix\\Main\\Event и EventResult"
type: entity
module: core-d7
edition: box
status: verified
provenance: documented
verified: "2026-06-01 / документация модуля main, раздел событий (dev.1c-bitrix.ru)"
tags: [события, d7, класс, свои-события, отладка]
sources: []
related: ["[[entity-event-manager]]", "[[pattern-events-over-core-modification]]", "[[entity-main-result]]"]
aliases: ["bitrix24-event-class"]
updated: "2026-09-18"
---

# `\Bitrix\Main\Event` и `\Bitrix\Main\EventResult`

**Что это:** объект-событие переносит данные подписчикам, объект-результат собирает их ответы.
Это же способ объявить **своё** событие в своём модуле.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | классы |
| Модуль | `main` |
| Конструктор события | `new Event($module, $eventType, $parameters)` |
| Конструктор результата | `new EventResult($resultType, $parameters, $moduleId)` |

## Отправить своё событие

```php
$event = new \Bitrix\Main\Event('vendor.module', 'OnSomethingHappened', [$entityId]);
$event->send();

foreach ($event->getResults() ?? [] as $result) {
    if ($result->getResultType() === \Bitrix\Main\EventResult::SUCCESS) {
        $data = $result->getParameters();
    }
}
```

Своё событие — правильный способ дать другим расширять **ваш** модуль, не трогая его код. Ровно
так это делает ядро: например, фабрики фильтров собираются через событие, а обработчики возвращают
`EventResult::SUCCESS` с массивом `callbacks`.

## Что возвращает обработчик

`EventResult` с типом (`SUCCESS` и другие) и параметрами. Возврат **не обязателен**: `null` или
отсутствие возврата допустимы.

## Отладка события

| Метод | Когда |
|---|---|
| `turnDebugOn()` | **до** `send()` |
| `addDebugInfo($x)` | внутри обработчика |
| `getDebugInfo()` | после `send()` — массив накопленного |

Полезно, когда подписчиков несколько и непонятно, кто что сделал.

## Подводные камни

- **Третий аргумент `EventResult` — `moduleId`**, и его часто забывают. Без него инициатор не
  понимает, чей это результат.
- **Обработчик D7-подписки получает ровно один аргумент** — сам `Event`. Привычная по старому ядру
  сигнатура с несколькими параметрами здесь не работает; для неё есть
  `addEventHandlerCompatible` ([[entity-event-manager]]).
- Документация упоминает «ленивые параметры» для тяжёлых данных, которые нужны не каждому
  обработчику, но конкретного API не раскрывает — проверяйте по исходникам, если понадобится.

## Связанное
- [[entity-event-manager]] — где регистрируются подписчики
- [[entity-main-result]] — соседний контейнер результата, с другим назначением

[← Ядро D7](_index-core-d7.md)
