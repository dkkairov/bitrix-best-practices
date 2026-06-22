---
title: "Расширение через события"
type: pattern
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-06-20 / Bitrix Framework (курс 43)"
tags: [события, eventmanager, расширение, local, разработка]
sources: ["[[source-bxfw-course43-modules]]"]
related: ["[[antipattern-box-core-modification]]", "[[recipe-module-structure-and-install]]"]
aliases: []
updated: "2026-06-20"
---

# Расширение через события

## Проблема и контекст
Нужно изменить или дополнить поведение платформы/модуля (после создания сделки, перед сохранением
заказа и т.п.). Соблазн — поправить ядро; это антипаттерн [[antipattern-box-core-modification|Правка ядра]].

## Решение
Подписаться на **событие** и выполнить свою логику в обработчике. Ядро не трогаем, обновления не ломаем.

## Где регистрировать обработчик
- **Проектная правка (под одного клиента)** → `local/php_interface/init.php`:
  ```php
  use Bitrix\Main\EventManager;
  EventManager::getInstance()->addEventHandler(
      'crm', 'OnAfterCrmDealAdd', [\Vendor\Local\Deal::class, 'onAfterAdd']
  );
  ```
- **Распространяемая функциональность** → в модуле, при установке
  ([[recipe-module-structure-and-install|InstallEvents]]):
  ```php
  EventManager::getInstance()->registerEventHandler(
      'crm', 'OnAfterCrmDealAdd', 'vendor.module',
      \Vendor\Module\Handler\Deal::class, 'onAfterAdd'
  );
  ```
  `register…` сохраняет подписку в БД (переживает перезагрузки), `add…` — на текущий хит.

## Обработчик (D7)
```php
namespace Vendor\Module\Handler;

use Bitrix\Main\Event;

class Deal
{
    public static function onAfterAdd(Event $event): void
    {
        $id = $event->getParameter('id');
        // своя логика; для отмены/изменения — вернуть Bitrix\Main\EventResult
    }
}
```

## Когда применять
- Любое вмешательство в стандартное поведение CRM/заказов/пользователей и т.д.

## Чего избегать
- Тяжёлой логики синхронно в обработчике (тормозит хит) — выноси в очередь/агент.
- Зацикливания (обработчик `OnAfter…Update`, который снова обновляет ту же сущность).

## Связанное
- [[antipattern-box-core-modification]], [[recipe-module-structure-and-install]], [[concept-dev-standards|Стандарт разработки]]

[← Ядро D7](_index-core-d7.md)
