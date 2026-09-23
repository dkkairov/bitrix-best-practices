---
title: "\\Bitrix\\Main\\Application"
type: entity
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-23 / коробка в Docker, main 26.750.0: состав публичных методов, конкретный класс приложения в консоли, поведение end() и addBackgroundJob() — по коду main/lib/Application.php и прогону"
tags: [ядро, приложение, соединение, кэш, сессия, завершение, d7, класс]
sources: []
related: ["[[entity-loader]]", "[[entity-main-result]]", "[[concept-service-locator]]", "[[recipe-cli-script-bootstrap]]", "[[concept-request-lifecycle]]"]
aliases: []
updated: "2026-09-23"
---

# `\Bitrix\Main\Application`

**Что это:** точка входа в ядро на весь хит — синглтон, через который берут соединение с БД, контекст
запроса, кэши и сессию и которым корректно завершают выполнение. Класс **абстрактный**: работает его
наследник `\Bitrix\Main\HttpApplication`.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | абстрактный класс-синглтон |
| Модуль | `main` |
| Получение | `\Bitrix\Main\Application::getInstance()` |
| Edition | box |
| Конкретный класс | `HttpApplication` — **в том числе в консоли**: отдельного CLI-приложения в ядре нет (стенд, 26.750.0) |

Отсюда следствие для консольных скриптов: буферизация вывода и сессия в консоли работают так же, как
на странице — см. [[recipe-cli-script-bootstrap]].

## Что берут через приложение

```php
use Bitrix\Main\Application;

$connection = Application::getConnection();              // статический метод
$context    = Application::getInstance()->getContext();  // запрос, ответ, сайт, язык
$request    = $context->getRequest();
```

| Метод | Что даёт |
|---|---|
| `getConnection($name = "")` | соединение с БД (**статический**); без имени — соединение по умолчанию |
| `getConnectionPool()` | пул соединений, когда БД несколько |
| `getContext()` | контекст хита: запрос, ответ, сайт, язык ([[concept-request-lifecycle]]) |
| `getCache()`, `getManagedCache()`, `getTaggedCache()` | три вида кэша ядра |
| `getSession()`, `getKernelSession()` | пользовательская и ядровая сессии |
| `getExceptionHandler()` | обработчик исключений — им пишут в журнал ядра |
| `getDocumentRoot()`, `getPersonalRoot()` | корень сайта и `/bitrix` (**статические**) |
| `isUtfMode()` | кодировка портала (**статический**) |
| `getLicense()` | сведения о лицензии |
| `addBackgroundJob($job, $args, $priority)` | работа «после ответа» — см. ниже |
| `end($status = 0)`, `terminate($status = 0)` | завершение хита с кодом возврата |

## Завершение хита

`end()` — корректный способ закончить работу: он дособирает открытые буферы вывода, отдаёт ответ,
сохраняет сессию и вызывает `terminate()`, а тот — `exit()`. Поэтому **код после `end()` не
выполняется**, а `\CMain::FinalActions()` — это фактически `end(0)`.

```php
// консольный скрипт: свой код возврата для мониторинга cron
\Bitrix\Main\Application::getInstance()->end($errors ? 1 : 0);
```

Проверено на стенде: после `FinalActions()` и после `end()` следующая строка скрипта не выполняется,
код возврата равен переданному ([[recipe-cli-script-bootstrap]]).

## Фоновые задания хита

`addBackgroundJob()` откладывает вызов **до момента, когда ответ уже отправлен пользователю** —
удобно для того, что не должно задерживать страницу: отправка уведомления, запись статистики.

```php
Application::getInstance()->addBackgroundJob(
    [\Vendor\Project\Notifier::class, 'send'],
    [$userId, $text],
    Application::JOB_PRIORITY_LOW      // NORMAL = 100, LOW = 50
);
```

- Задания выполняются по приоритету; задание может добавить следующее — очередь разбирается до конца.
- **Исключение внутри задания наверх не всплывает:** ядро пишет его в журнал и идёт дальше
  (`runBackgroundJobs`, код 26.750.0). Значит результат проверяем сами, а не надеемся на падение.
- Это не замена агентам и очередям: задание живёт в том же процессе и умирает вместе с ним.

## Подводные камни

- **`getConnection()` статический, `getContext()` — нет.** Частая путаница при копировании примеров.
- **Прямой `exit` / `die` вместо `end()`** обрывает хит до сохранения сессии и отправки ответа —
  в публичной части так не завершают.
- **В консоли приложение то же самое**, поэтому «веб-поведение» никуда не девается: печатать до
  пролога нельзя, иначе не стартует сессия ([[recipe-cli-script-bootstrap]]).
- Класс абстрактный: `new Application()` не сделать, только `getInstance()`.

## Связанное
- [[recipe-cli-script-bootstrap]] — подключение ядра в консоли и корректное завершение
- [[concept-request-lifecycle]] — из чего состоит хит и где в нём приложение
- [[entity-loader]] — подключение модулей
- [[concept-service-locator]] — где ядро держит сервисы, а где остаются синглтоны вроде этого
- [[entity-main-result]] — общий тип результата операций ядра

[← Ядро D7](_index-core-d7.md)
