---
title: "\\Bitrix\\Main\\EventManager"
type: entity
module: core-d7
edition: box
status: verified
provenance: documented
verified: "2026-06-01 / документация модуля main, раздел событий (dev.1c-bitrix.ru)"
tags: [события, d7, eventmanager, класс, подписка]
sources: []
related: ["[[pattern-events-over-core-modification]]", "[[recipe-d7-orm-event-subscription]]", "[[concept-orm-datamanager-events]]", "[[entity-main-event]]"]
aliases: ["bitrix24-event-manager"]
updated: "2026-09-18"
---

# `\Bitrix\Main\EventManager`

**Что это:** точка входа для подписки на серверные события. Синглтон:
`\Bitrix\Main\EventManager::getInstance()`.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | класс (singleton) |
| Модуль | `main` |
| Хранилище регистраций | таблица `b_module_to_module` |
| Edition | box |

## Два способа подписки

### Добавление — живёт до конца хита

```php
$em->addEventHandler(
    $fromModuleId,        // 'crm', 'main', …
    $eventType,           // 'OnAfterCrmDealAdd', …
    $callback,            // callable
    $includeFile = false, // путь к php-файлу вместо callable
    $sort = 100           // меньше — раньше
): int;                   // номер регистрации, нужен для removeEventHandler
```

`addEventHandlerCompatible(...)` — то же для событий старого ядра, где аргументы приходят
несколькими параметрами, в том числе по ссылке.

### Регистрация — живёт в БД

```php
$em->registerEventHandler(
    $fromModuleId, $eventType,
    $toModuleId, $toClass = '', $toMethod = '',
    $sort = 100, $toPath = '', $toMethodArg = []
);
```

Делается один раз при установке модуля. Зеркало — `unRegisterEventHandler(...)`.
Снять добавленный на хит обработчик — `removeEventHandler($fromModuleId, $eventType, $key)`.

## Что выбрать

| Ситуация | Способ |
|---|---|
| Проектная правка под одного клиента | `addEventHandler` в `local/php_interface/init.php` |
| Распространяемый модуль, подписка на обычное событие | `registerEventHandler` при установке |
| **Событие D7 ORM из модуля** | **только** `addEventHandler` в `include.php` + инжект `init.php` |

Последняя строка — не стилистика: `MESSAGE_ID` в `b_module_to_module` это `VARCHAR(50)`, имена
ORM-событий длиннее, запись усекается и обработчик молча не вызывается
([[recipe-d7-orm-event-subscription]]).

## Что получает обработчик

- `addEventHandler` — **ровно один** аргумент: [[entity-main-event|`\Bitrix\Main\Event`]].
- `addEventHandlerCompatible` / `RegisterModuleDependences` — отдельные аргументы по порядку.

Способ регистрации определяет сигнатуру: перепутать легко, ошибка проявится как «в обработчик
пришло не то».

## Подводные камни

- **Callback лучше статическим методом** (`[\Vendor\Module\Handler::class, 'onAfterAdd']`), а не
  замыканием: замыкание нельзя снять и неудобно отлаживать.
- **`$sort` влияет на порядок** относительно чужих обработчиков — если ваша логика должна идти
  после штатной, это единственный рычаг.
- Для диагностики подписок старого ядра есть `\GetModuleEvents($module, $event)`; отдельного
  публичного способа перечислить D7-подписки в документации не описано.

## Связанное
- [[pattern-events-over-core-modification]] — когда вообще идти в события
- [[concept-orm-datamanager-events]] — формат имён событий ORM

[← Ядро D7](_index-core-d7.md)
