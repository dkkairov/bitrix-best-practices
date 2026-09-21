---
title: "Расширение через события"
type: pattern
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Технологии / События, Свой код; курс 43; оговорка про D7-ORM — 2026-09-15, коробка main 26.700"
tags: [события, eventmanager, расширение, local, разработка, compatible]
sources: ["[[source-bxfw-course43-modules]]", "[[source-devbook-core-d7]]", "[[source-devbook-dev-rules]]"]
related: ["[[antipattern-box-core-modification]]", "[[recipe-module-structure-and-install]]", "[[recipe-d7-orm-event-subscription]]", "[[entity-event-manager]]", "[[concept-orm-datamanager-events]]", "[[pattern-crm-action-vs-event]]"]
aliases: []
updated: "2026-09-21"
---

# Расширение через события

> **Изменено 2026-09-21 при сверке с «Книгой разработчика».** Прежний пример подписывал
> `OnAfterCrmDealAdd` через `addEventHandler` / `registerEventHandler` и ждал в обработчике объект
> `Event`. Для событий **старого ядра** (все события `CCrm*`) это неверно: книга подписывается на них
> через `…Compatible`, и обработчик получает массив полей по ссылке, а не `Event`
> ([События](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Sobytia.html#kak-podpisat-sa-na-sobytia),
> [события сделки](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Sdelka/Cobytia.html#onaftercrmdealadd)).
> Пример исправлен, добавлена таблица поколений событий.

## Проблема и контекст
Нужно изменить или дополнить поведение платформы/модуля (после создания сделки, перед сохранением
заказа и т.п.). Соблазн — поправить ядро; это антипаттерн [[antipattern-box-core-modification|Правка ядра]].

## Решение
Подписаться на **событие** и выполнить свою логику в обработчике. Ядро не трогаем, обновления не ломаем.

## Сначала — поколение события

Метод подписки выбирают по тому, **какое событие** ловим, — от этого зависит, что придёт в обработчик.

| Поколение | Примеры | Добавить на хит | Зарегистрировать в модуле | Что получает обработчик |
|-----------|---------|-----------------|---------------------------|-------------------------|
| Старое ядро | `OnBeforeCrmDealAdd`, `OnAfterCrmDealUpdate`, события `CCrm*`, `iblock`, `main` старого API | `addEventHandlerCompatible` | `registerEventHandlerCompatible` | аргументы по порядку, часто массив полей **по ссылке** (`array &$fields`) |
| Новое ядро (D7) | события ORM `DataManager`, события модулей на `\Bitrix\Main\Event` | `addEventHandler` | `registerEventHandler` | ровно один объект `\Bitrix\Main\Event`; ответ — `EventResult` или ничего |

Сигнатуры пар методов совпадают, отличается только имя. Подробно — [[entity-event-manager]].

## Где регистрировать обработчик
- **Проектная правка (под одного клиента)** — подписки отдельным файлом
  `local/php_interface/events.php` (подключается из `init.php`), код обработчиков — в классах, а не в
  замыканиях рядом с подпиской ([Свой код → events.php](https://bx24devbook.website.yandexcloud.net/Razrabotka/Struktura_papki_local/Svoj_kod.html#events-php)):
  ```php
  // local/php_interface/events.php
  $eventManager = \Bitrix\Main\EventManager::getInstance();

  // событие старого ядра → Compatible
  $eventManager->addEventHandlerCompatible(
      'crm', 'OnAfterCrmDealAdd', [\Vendor\Local\Crm\DealCatcher::class, 'onAfterAdd']
  );
  ```
- **Распространяемая функциональность** → в модуле, при установке
  ([[recipe-module-structure-and-install|InstallEvents]]):
  ```php
  \Bitrix\Main\EventManager::getInstance()->registerEventHandlerCompatible(
      'crm', 'OnAfterCrmDealAdd', 'vendor.module',
      \Vendor\Module\Handler\Deal::class, 'onAfterAdd'
  );
  ```
  `register…` сохраняет подписку в БД (`b_module_to_module`, один раз при установке), `add…` живёт
  только до конца текущего хита.

> **Исключение — события D7 ORM.** `register…` пишет имя события в `b_module_to_module.MESSAGE_ID`,
> а это `VARCHAR(50)`. Имена ORM-событий длиннее (`\Bitrix\BizProc\Workflow\Task\TaskUser::OnAfterUpdate`
> — 53 символа): запись усекается, обработчик **молча не вызывается**, ошибки нет. Для таких событий
> единственный рабочий путь — `addEventHandler` в `include.php` модуля плюс инжект загрузки модуля
> в `init.php`: [[recipe-d7-orm-event-subscription]]. Проверено вживую на коробке (main 26.700).
> Книга события ORM не разбирает и для модулей советует регистрацию без оговорок — это исключение
> установлено нами эмпирически.

## Обработчик

**Старое ядро** — аргументы приходят по порядку; набор полей не гарантирован, наличие ключа
проверяем всегда (книга, [события сделки](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Sdelka/Cobytia.html)):
```php
namespace Vendor\Module\Handler;

class Deal
{
    /** crm::OnAfterCrmDealAdd — возвращаемое значение не обрабатывается */
    public static function onAfterAdd(array &$fields): void
    {
        $dealId = (int)($fields['ID'] ?? 0);
        if ($dealId <= 0) {
            return;
        }
        // своя логика
    }
}
```
Отменить операцию можно только в `OnBefore…`: вернуть `false` и положить текст в
`$fields['RESULT_MESSAGE']` (для `Add`/`Update`). У `OnAfter…` возврат игнорируется.

**Новое ядро** — один объект события:
```php
public static function onAfterUpdate(\Bitrix\Main\Event $event): void
{
    $fields = $event->getParameter('fields') ?? [];
    // …
}
```
Параметры событий ORM — [[concept-orm-datamanager-events]].

## Когда применять
- Любое вмешательство в стандартное поведение CRM/заказов/пользователей и т.д.
- Для сущностей CRM на Universal API сначала рассмотри `Operation\Action` —
  [[pattern-crm-action-vs-event]].

## Чего избегать
- Тяжёлой логики синхронно в обработчике (тормозит хит) — выноси в очередь/агент.
- **Зацикливания**: обработчик `OnAfter…Update`, который снова обновляет ту же сущность. Средства
  из книги ([частные ситуации](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Sobytia.html#zaciklirovanie-obrabotcikov)):
  дописать поля в `$arFields` в `OnBefore…` вместо повторного `Update`; идемпотентный код (проверить,
  что действие ещё не выполнено); статический lock-флаг в классе-обработчике.
- **«Поле пришло» ≠ «поле изменилось».** В событии — поля, переданные в запросе на изменение, а не
  вся сущность. Чтобы поймать именно смену значения, сравни с исходным в `OnBefore…`, поставь флаг и
  проверь его в `OnAfter…` ([нет проверки аргументов](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Sobytia.html#net-proverki-nalicia-argumentov)).
- Кода обработчика в `init.php` или в замыкании прямо в подписке — файл разрастается, код не
  переиспользуется и плохо читается.

## Связанное
- [[entity-event-manager]] — методы подписки и отписки
- [[antipattern-box-core-modification]], [[recipe-module-structure-and-install]], [[concept-dev-standards|Стандарт разработки]]

[← Ядро D7](_index-core-d7.md)
