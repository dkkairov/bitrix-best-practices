---
title: "Расширение через события"
type: pattern
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-06-20 / курс 43; оговорка про D7-ORM — 2026-09-15, коробка main 26.700"
tags: [события, eventmanager, расширение, local, разработка]
sources: ["[[source-bxfw-course43-modules]]"]
related: ["[[antipattern-box-core-modification]]", "[[recipe-module-structure-and-install]]", "[[recipe-d7-orm-event-subscription]]", "[[entity-event-manager]]", "[[concept-orm-datamanager-events]]"]
aliases: []
updated: "2026-09-18"
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

> **Исключение — события D7 ORM.** `register…` пишет имя события в `b_module_to_module.MESSAGE_ID`,
> а это `VARCHAR(50)`. Имена ORM-событий длиннее (`\Bitrix\BizProc\Workflow\Task\TaskUser::OnAfterUpdate`
> — 53 символа): запись усекается, обработчик **молча не вызывается**, ошибки нет. Для таких событий
> единственный рабочий путь — `addEventHandler` в `include.php` модуля плюс инжект загрузки модуля
> в `init.php`: [[recipe-d7-orm-event-subscription]]. Проверено вживую на коробке (main 26.700).

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
