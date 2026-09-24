---
title: "Логирование D7: логгеры, уровни, настройка"
type: concept
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / коробка в Docker, main 26.750.0: наличие Diag\\Logger, FileLogger, SysLogger, EventLogger, LogFormatter, JsonLinesFormatter, LogFormatterInterface и методов create/setFormatter/setLevel; текст — документация фреймворка (docs.1c-bitrix.ru, «Логгеры»)"
tags: [логи, diag, psr-3, отладка, settings]
sources: []
related: ["[[recipe-http-client]]", "[[entity-main-result]]", "[[concept-coding-standards]]", "[[recipe-cli-script-bootstrap]]"]
aliases: []
updated: "2026-09-24"
---

# Логирование D7: логгеры, уровни, настройка

**TL;DR:** в ядре есть PSR-3-логирование: четыре готовых логгера, уровни от `emergency` до `debug`,
настройка секцией `loggers` в `.settings.php`. `AddMessage2Log()` никуда не делся, но за ним теперь
стоит тот же механизм — идентификатор `main.Default`.

**Когда нужно:** интеграции, фоновые задачи, всё, что нельзя отладить в браузере. Логи — первое,
что спрашивает поддержка, и последнее, о чём вспоминает разработчик.

## Четыре логгера

| Класс | Куда пишет |
|---|---|
| `Bitrix\Main\Diag\FileLogger` | файл; конструктор `new FileLogger($path, $maxLogSize)` |
| `Bitrix\Main\Diag\SysLogger` | системный журнал (syslog) |
| `Bitrix\Main\Diag\EventLogger` | таблица `b_event_log` — журнал событий в админке |
| `Bitrix\Main\Diag\Logger` | базовый класс, от него наследуют свои |

**Ротация файла**: по умолчанию 1 МБ, при превышении — однократная ротация; `$maxLogSize = 0`
отключает её. «Однократная» значит, что архив один: следующий цикл его перезапишет, вечного архива
не будет — для долгой истории отдаём файл внешнему ротатору.

## Уровни

Восемь уровней PSR-3: `emergency()`, `alert()`, `critical()`, `error()`, `warning()`, `notice()`,
`info()`, `debug()`. Уровень логгера отсекает всё менее важное — `setLevel()` или ключ `level` в
настройках.

## Настройка в `.settings.php`

```php
'loggers' => ['value' => [
    'vendor.import' => [
        'constructor' => fn() => new \Bitrix\Main\Diag\FileLogger(
            $_SERVER['DOCUMENT_ROOT'] . '/local/logs/import.log', 5 * 1024 * 1024
        ),
        'level' => \Psr\Log\LogLevel::INFO,
        'formatter' => 'vendor.formatter',
    ],
]],
```

Получаем логгер фабрикой:

```php
$logger = \Bitrix\Main\Diag\Logger::create('vendor.import', [$this]);
$logger?->info('Импорт начат, записей: {count}', ['count' => $n]);
```

Фабрика вернёт `null`, если такой идентификатор не настроен, — код с `?->` работает и без
конфигурации. Свой класс принимает логгер через `Psr\Log\LoggerAwareInterface` и трейт
`LoggerAwareTrait`.

**Готовые идентификаторы ядра:** `main.HttpClient` (все запросы [[recipe-http-client|HttpClient]]),
`main.GeoIpManager`, `main.Default` (то, что пишет `AddMessage2Log`). Настроив их, получаем лог без
единой правки кода.

## Формат

Форматтер по умолчанию — `Diag\LogFormatter`; свой реализует `Diag\LogFormatterInterface` с методом
`format($message, array $context = []): string`. Доступны плейсхолдеры `{date}`, `{host}`,
`{exception}`, `{trace}`, `{delimiter}` плюс любые ключи контекста. Для машинного разбора есть
`Diag\JsonLinesFormatter` (по документации — с 25.300.0; на стенде 26.750.0 присутствует).

## Правила

- **Логи не в репозиторий и не в публичный каталог.** `/local/logs/` закрываем от веб-сервера:
  файл лога с телами запросов — готовая утечка.
- **Секреты и персданные в лог не пишем** — то же правило, что для вики ([[concept-coding-standards]]).
- **`debug` в продакшене не оставляем** — файл растёт быстрее, чем кажется, а ротация одноразовая.
- **Контекст вместо склейки:** `('Ошибка {id}', ['id' => $id])` — форматтер подставит сам, строка
  остаётся машиночитаемой.
- **Консольным скриптам логи нужнее всего** — вывода в браузер у них нет
  ([[recipe-cli-script-bootstrap]]).

## Связанные страницы
- [[recipe-http-client]] — `main.HttpClient` и `setLogger()`
- [[entity-main-result]] — ошибки операции, которые попадают в лог
- [[concept-coding-standards]] — что нельзя писать в лог

[← Ядро D7](_index-core-d7.md)
