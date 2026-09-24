---
title: "Очереди сообщений ядра (альфа): Messenger"
type: concept
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / коробка в Docker, main 26.750.0: классы Messenger\\Entity\\AbstractMessage, Messenger\\Receiver\\AbstractReceiver, ProcessingParam\\DelayParam есть; таблица b_main_messenger_message создана; секция messenger в конфигурации задана с брокером типа db; исключения лежат в Messenger\\Internals\\Exception\\Receiver; CLI-команды требуют composer — `php bitrix/bitrix.php list` отвечает «Symfony Console is not installed»; текст — документация фреймворка (docs.1c-bitrix.ru, «Очереди сообщений»)"
tags: [очереди, messenger, фоновые-задачи, alpha, cli]
sources: []
related: ["[[pattern-agents-vs-cron]]", "[[pattern-stepper-long-operations]]", "[[recipe-cli-script-bootstrap]]", "[[entity-main-application]]"]
aliases: []
updated: "2026-09-24"
---

# Очереди сообщений ядра (альфа): Messenger

**TL;DR:** с `main` 25.100.300 в ядре появились очереди сообщений — отправить сообщение и
обработать его отдельно, с задержкой и повторами. Функционал вендор называет **альфа-версией**, и
на нашем стенде это подтверждается: классы исключений лежат в `Internals`, а CLI-обработчик не
запускается без composer.

**Когда смотреть в эту сторону:** отложенная обработка (письма, интеграции, пересчёты), где важны
повторы при сбое. Пока — на своих проектах и с оглядкой, в продакшене у заказчика лучше подождать
стабильной версии.

## Как выглядит

```php
use Bitrix\Main\Messenger\Entity\AbstractMessage;
use Bitrix\Main\Messenger\Receiver\AbstractReceiver;
use Bitrix\Main\Messenger\Entity\MessageInterface;
use Bitrix\Main\Messenger\Entity\ProcessingParam\DelayParam;

final class OrderPaid extends AbstractMessage { /* свои поля */ }

// отправка
(new OrderPaid($orderId))->send('orders');
(new OrderPaid($orderId))->send('orders', [new DelayParam(3600)]);   // через час

// обработка
final class OrderPaidReceiver extends AbstractReceiver
{
    public function process(MessageInterface $message): void { /* ... */ }
}
```

## Транспорт и запуск

- **Транспорт только один — `db`.** На стенде секция `messenger` задана по умолчанию: брокер
  `default`, тип `db`, таблица через `Messenger\Internals\Storage\Db\Model\MessengerMessageTable`;
  таблица `b_main_messenger_message` в базе есть.
- **Режим `web`** — сообщения разбираются фоновыми задачами хита
  ([[entity-main-application|Application::addBackgroundJob]]).
- **Режим `cli`** — `php bitrix/bitrix.php messenger:consume <очередь>` с ключами `-t` (лимит
  времени) и `--sleep`. **На стандартной коробке это не работает**: консольные команды ядра
  требуют composer и `symfony/console`, иначе ответ — «Symfony Console is not installed». То же
  ограничение мы уже ловили на `orm:annotate`.

## Ошибки и повторы

| Исключение (в `Messenger\Internals\Exception\Receiver`) | Смысл |
|---|---|
| `UnprocessableMessageException` | сообщение не для этого обработчика |
| `UnrecoverableMessageException` | повторять бессмысленно |
| `RecoverableMessageException` | временная ошибка, повторить |

Стратегия повторов настраивается: `max_retries` (3), `delay` (1 с), `multiplier` (2), `max_delay`.

## Чем это отличается от соседей

| Механизм | Для чего |
|---|---|
| **агенты / cron** | периодическая работа по расписанию ([[pattern-agents-vs-cron]]) |
| **`Stepper`** | одна большая операция, разбитая на шаги ([[pattern-stepper-long-operations]]) |
| **фоновые задачи хита** | «доделать после ответа пользователю», в пределах одного запроса |
| **очереди** | поток независимых заданий с повторами и задержкой |

## Ловушки

- **Альфа.** Публичный API может измениться; исключения вообще объявлены в `Internals` — по
  соглашению ядра это «не для внешнего использования».
- **Версия.** До 25.100.300 механизма нет вовсе; у заказчика на 22.x его не будет.
- **`db` как транспорт** означает нагрузку на базу и отсутствие внешнего брокера: очередь на
  миллионы сообщений так не строят.
- **Без composer нет CLI**, а значит нет и контролируемого воркера — остаётся режим `web`,
  зависящий от посещаемости портала.

## Связанные страницы
- [[pattern-agents-vs-cron]] — что использовать сегодня
- [[pattern-stepper-long-operations]] — массовая операция шагами
- [[entity-main-application]] — фоновые задачи хита

[← Ядро D7](_index-core-d7.md)
