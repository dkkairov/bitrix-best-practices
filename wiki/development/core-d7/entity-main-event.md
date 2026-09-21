---
title: "\\Bitrix\\Main\\Event и EventResult"
type: entity
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Технологии — События (создание своих событий, отладка, ленивые параметры); про moduleId в EventResult — не из книги"
tags: [события, d7, класс, свои-события, отладка]
sources: ["[[source-devbook-core-d7]]"]
related: ["[[entity-event-manager]]", "[[pattern-events-over-core-modification]]", "[[entity-main-result]]"]
aliases: ["bitrix24-event-class"]
updated: "2026-09-21"
---

# `\Bitrix\Main\Event` и `\Bitrix\Main\EventResult`

**Что это:** объект-событие переносит данные подписчикам, объект-результат собирает их ответы.
Это же способ объявить **своё** событие в своём модуле
([создание своих событий](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Sobytia.html#sozdanie-svoih-sobytij)).

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

**Три совета книги для своих событий:**
1. Имя события выбирать осознанно и группировать по смыслу — не событие «на каждый чих».
2. Обрабатывать **все** результаты: если просто перезаписывать переменную в цикле, победит последний
   успешный обработчик.
3. В параметрах передавать объекты: скаляры и массивы уходят по значению, и обработчик не сможет
   их изменить; объект каждый обработчик видит в актуальном состоянии.

## Что возвращает обработчик

`EventResult` с типом (`SUCCESS` и другие) и параметрами. Возврат **не обязателен**: `null` или
отсутствие возврата допустимы.

## Отладка события

| Метод | Когда |
|---|---|
| `turnDebugOn()` | **до** `send()` |
| `addDebugInfo($x)` | внутри обработчика |
| `getDebugInfo()` | после `send()` — массив накопленного |

Полезно, когда подписчиков несколько и непонятно, кто что сделал. Параметры события, обработчик и
результат `EventManager` записывает в отладочную информацию сам — дублировать их не нужно, добавляйте
только свои технические данные ([отладка своих событий](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Sobytia.html#otladka-sobstvennyh-sobytij)).

## Подводные камни

- **Третий аргумент `EventResult` — `moduleId`**, и его часто забывают. Без него инициатор не
  понимает, чей это результат. (Наблюдение команды; в книге конструктор `EventResult` не разбирается —
  сверить по справочнику D7.)
- **Обработчик D7-подписки получает ровно один аргумент** — сам `Event`. Привычная по старому ядру
  сигнатура с несколькими параметрами здесь не работает; для неё есть
  `addEventHandlerCompatible` ([[entity-event-manager]]).
- Книга упоминает «ленивые параметры» для тяжёлых данных, которые нужны не каждому обработчику, но
  раздел в ней — заглушка без API
  ([ленивые параметры](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Sobytia.html#lenivye-parametry)).
  Проверяйте по исходникам, если понадобится.

## Связанное
- [[entity-event-manager]] — где регистрируются подписчики
- [[entity-main-result]] — соседний контейнер результата, с другим назначением

[← Ядро D7](_index-core-d7.md)
