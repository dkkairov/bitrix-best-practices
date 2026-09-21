---
title: "\\Bitrix\\Main\\EventManager"
type: entity
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Технологии — События, Свой код; строка про события D7 ORM — эмпирика, main 26.700"
tags: [события, d7, eventmanager, класс, подписка, compatible]
sources: ["[[source-devbook-core-d7]]"]
related: ["[[pattern-events-over-core-modification]]", "[[recipe-d7-orm-event-subscription]]", "[[concept-orm-datamanager-events]]", "[[entity-main-event]]", "[[entity-crm-legacy-events]]"]
aliases: ["bitrix24-event-manager"]
updated: "2026-09-21"
---

# `\Bitrix\Main\EventManager`

**Что это:** точка входа для подписки на серверные события. Синглтон:
`\Bitrix\Main\EventManager::getInstance()`. Обработчики вызываются последовательно — по сортировке,
при равной — в порядке добавления ([События](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Sobytia.html#kak-eto-rabotaet)).

> **Сверено с книгой 2026-09-21.** Добавлена ось «старое / новое ядро» — от неё, а не от способа
> регистрации, зависит, что придёт в обработчик. Исправлено: прежний пример приводил
> `OnAfterCrmDealAdd` (событие старого ядра) для `addEventHandler`; замыкание можно снять по ключу.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | класс (singleton) |
| Модуль | `main` |
| Хранилище регистраций | таблица `b_module_to_module` |
| Edition | box |

## Сначала — поколение события

| Событие | Добавить на хит | Зарегистрировать в БД | Обработчик получает |
|---|---|---|---|
| **старого ядра** (`OnBeforeCrmDealAdd`, `OnAfterCrmDealUpdate`, …) | `addEventHandlerCompatible` | `registerEventHandlerCompatible` | аргументы по порядку, в том числе по ссылке (`array &$arFields`); что ждёт в ответ — зависит от события |
| **нового ядра** (D7, события ORM, `\Bitrix\Main\Event`) | `addEventHandler` | `registerEventHandler` | ровно один [[entity-main-event|`\Bitrix\Main\Event`]]; ответ — `EventResult` или ничего |

Сигнатуры в каждой паре совпадают, различаются только имена методов. Книга советует и для старых
событий явно брать `…Compatible` — так не спутаешь, когда обработчик меняет входные аргументы.
События CRM-сущностей — [[entity-crm-legacy-events]].

## Добавление — живёт до конца хита

```php
$em = \Bitrix\Main\EventManager::getInstance();
$key = $em->addEventHandler(
    $fromModuleId,        // 'main', 'bizproc', …
    $eventType,           // имя события нового ядра
    $callback,            // callable
    $includeFile = false, // путь к php-файлу вместо callable
    $sort = 100           // меньше — раньше
);                        // int: ключ регистрации, нужен для removeEventHandler
```

Снять добавленный на хит обработчик — `removeEventHandler($fromModuleId, $eventType, $key)` по ключу,
который вернул `add…` — это работает и для замыкания.

## Регистрация — живёт в БД

```php
$em->registerEventHandler(          // или registerEventHandlerCompatible для старого ядра
    $fromModuleId, $eventType,
    $toModuleId, $toClass = '', $toMethod = '',
    $sort = 100, $toPath = '', $toMethodArg = []
);
```

Делается один раз при установке модуля. Зеркало — `unRegisterEventHandler(...)` при удалении.

## Что выбрать

| Ситуация | Способ |
|---|---|
| Проектная правка под одного клиента | `add…` в `local/php_interface/events.php` (подключается из `init.php`) — [[pattern-local-solution-structure]] |
| Распространяемый модуль, подписка на обычное событие | `register…` при установке (`…Compatible` для старого ядра) |
| **Событие D7 ORM из модуля** | **только** `addEventHandler` в `include.php` + инжект `init.php` |

Последняя строка — эмпирика команды (main 26.700), а не книга: книга события ORM не разбирает и
советует модулям регистрацию без оговорок. Причина: `MESSAGE_ID` в `b_module_to_module` — это
`VARCHAR(50)`, имена ORM-событий длиннее, запись усекается и обработчик молча не вызывается
([[recipe-d7-orm-event-subscription]]).

## Подводные камни

- **Метод подписки выбирают по поколению события.** Подписать событие старого ядра через
  `addEventHandler` и ждать `Event` — значит получить в обработчик не то (массив полей вместо объекта).
- **Callback лучше статическим методом** (`[\Vendor\Module\Handler::class, 'onAfterAdd']`), а не
  замыканием: подписки держим отдельно от кода обработчика (`events.php`), замыкание неудобно
  отлаживать и переиспользовать.
- **`$sort` — главный рычаг порядка** относительно чужих обработчиков; при равной сортировке
  обработчики идут в порядке добавления.
- Для диагностики подписок старого ядра есть `\GetModuleEvents($module, $event)`; отдельного
  публичного способа перечислить D7-подписки книга не описывает.

## Связанное
- [[pattern-events-over-core-modification]] — когда вообще идти в события
- [[concept-orm-datamanager-events]] — формат имён событий ORM
- [[entity-crm-legacy-events]] — события лида, контакта, компании, сделки

[← Ядро D7](_index-core-d7.md)
