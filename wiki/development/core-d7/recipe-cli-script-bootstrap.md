---
title: "Консольный и cron-скрипт: подключение ядра и завершение"
type: recipe
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-22 / коробка в Docker, main 26.750.0: прогон скрипта в консоли от владельца сайта — сессия и $_SERVER, события пролога, лимиты, поведение FinalActions и Application::end; текст рецепта — «Книга разработчика Bitrix24» (снимок 2026-09-21)"
tags: [cli, cron, консоль, prolog-before, finalactions, фоновые-задачи]
sources: ["[[source-devbook-core-d7]]"]
related: ["[[pattern-agents-vs-cron]]", "[[concept-request-lifecycle]]", "[[antipattern-cli-php-as-root]]", "[[pattern-local-solution-structure]]", "[[entity-php-interface]]", "[[entity-admin-php-console]]"]
aliases: []
updated: "2026-09-22"
---

# Консольный и cron-скрипт: подключение ядра и завершение

**Результат:** PHP-скрипт, который из консоли или по `cron` поднимает ядро без веб-запроса, работает
сколько нужно и корректно завершается — с закрытием БД и фоновыми заданиями. Основа —
[«Выполнение в фоновом режиме»](https://bx24devbook.website.yandexcloud.net/Obsie_svedenia/Stranica.html#vypolnenie-v-fonovom-rezime).

**Когда применять:** долгие и тяжёлые операции — книга относит к ним всё, что идёт от 5 секунд или
требует много памяти, и отправляет их в cron, а не в агенты
([Агенты → выбор](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Agenty.html#vybor-mezdu-agentami-i-cron),
[[pattern-agents-vs-cron]]); разовые служебные скрипты — импорт, пересчёт, чистка.

> **Проверено на стенде** (коробка в Docker, 2026-09-22): шаблон ниже запущен от пользователя
> сайта. Подтвердилось: события `OnPageStart`, `OnBeforeProlog`, `OnProlog` в консоли срабатывают;
> пользователь — гость. Уточнено: сессия стартует и в консоли, `$_SERVER` не пуст, после
> `FinalActions()` код не выполняется и код возврата 0.

## Чем консоль отличается от страницы

По книге результат в браузере или в «php-консоли» разработчика
([[entity-admin-php-console|командная PHP-строка]]) может не совпасть с консольным:

- **нет авторизованного пользователя** — всё выполняется от гостя (`$USER` есть, `IsAuthorized()`
  ложь, `GetID()` = `0`);
- **cookie нет, а сессия есть.** Вопреки ожиданию, ядро стартует сессию и в консоли (на стенде —
  активна, 9 ключей), но идентификатор у каждого запуска свой: состояние между запусками так не
  сохранить. Отсюда правило ниже — не печатать ничего до пролога;
- **`$_SERVER` не пуст, но веб-ключей в нём нет.** В консоли это переменные окружения и ключи CLI
  (`SCRIPT_NAME`, `PHP_SELF`, `argv`); `HTTP_HOST`, `REQUEST_URI`, `SERVER_NAME` не заполняются.
  `DOCUMENT_ROOT` задаём сами — абсолютный путь к каталогу, где лежит `bitrix`.

При этом **служебная часть пролога работает как на хите.** В
[таблице порядка выполнения](https://bx24devbook.website.yandexcloud.net/Obsie_svedenia/Stranica.html#poradok-vypolnenia)
`prolog_before.php` — это шаги 1–14: `dbconn.php`, соединение с БД, `init.php`, события
`OnPageStart`, `OnBeforeProlog`, `OnProlog`. Шаблона (визуальной части) нет. Выводы команды из
таблицы:

- обработчики событий из `init.php` сработают и на изменениях, которые делает скрипт, — помним об
  этом при массовых операциях;
- обработчики `OnPageStart`, `OnBeforeProlog` и `OnProlog` тоже выполнятся — они должны переживать
  пустой `$_SERVER` и гостя;
- нужен конкретный сайт (его `SITE_DIR`, язык, форматы дат) — определяем `SITE_ID` до пролога: по
  шагу 4 тогда сайт не вычисляется по папке и домену, которого в консоли нет.

## Где лежит скрипт

В структуре решения по книге для консольных скриптов и приложений отведён
`/local/php_interface/console/`
([Свой код](https://bx24devbook.website.yandexcloud.net/Razrabotka/Struktura_papki_local/Svoj_kod.html#tipovaa-struktura-proekta),
[[pattern-local-solution-structure]]). Каталог `/local/tools/` книга оставляет для технических
устаревших скриптов
([Структура папки local](https://bx24devbook.website.yandexcloud.net/Razrabotka/Struktura_papki_local/Osnovnoe.html#podderzivaemye-direktorii)).

## Шаги

1. **Корень сайта** — `$_SERVER['DOCUMENT_ROOT']`, абсолютный путь. Плюс устаревший глобал
   `$DOCUMENT_ROOT` — на случай старых модулей и компонентов.
2. **Среда — до пролога.** Константы и их назначение по книге:

   | Константа | Зачем |
   |---|---|
   | `NO_KEEP_STATISTIC`, `STOP_STATISTICS`, `NO_AGENT_STATISTIC` | выключить сбор и обработку статистики модуля «Веб-аналитика» |
   | `NOT_CHECK_PERMISSIONS` | выключить проверку прав |
   | `BX_NO_ACCELERATOR_RESET` | не сбрасывать кэш акселератора |
   | `DisableEventsCheck`, `NO_AGENT_CHECK` | не проверять агенты; книга даёт обе одним пунктом, роль каждой отдельно не раскрыта — сверить с офф. документацией |

3. **Служебная часть пролога** — `/bitrix/modules/main/include/prolog_before.php`. Не
   `/bitrix/header.php`: тот подключает ещё и визуальную часть шаблона
   ([шапка страницы](https://bx24devbook.website.yandexcloud.net/Obsie_svedenia/Stranica.html#sapka-header)).
4. **Лимиты — после пролога:** `set_time_limit(0)` и `ignore_user_abort(true)`. Раньше нельзя —
   `dbconn.php` может их переопределить (на стенде в консоли `max_execution_time` и так 0).
5. **Полезный код.**
6. **Завершение — обязательно:** `\CMain::FinalActions()` или подключение служебной части эпилога
   (`/bitrix/modules/main/include/epilog_after.php`). Там закрывается соединение с БД,
   обрабатываются фоновые задания, уходят push-уведомления; без этого, по книге, консольный скрипт
   работает некорректно. **Обе формы завершают процесс:** код после них не выполняется, а код
   возврата — 0 (стенд). Нужен свой код для мониторинга — вместо них
   `\Bitrix\Main\Application::getInstance()->end(<код>)`: те же завершающие действия и выход с этим
   кодом (`CMain::FinalActions()` — это `end(0)`).

```php
<?php
// local/php_interface/console/orders-recalc.php
// Запуск — от владельца сайта: sudo -u bitrix php -f local/php_interface/console/orders-recalc.php

// 1. Корень: console → php_interface → local → корень сайта (вариант команды, см. ниже)
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3);
$DOCUMENT_ROOT = $_SERVER['DOCUMENT_ROOT'];

// 2. Среда — до пролога
define('NO_KEEP_STATISTIC', true);
define('STOP_STATISTICS', true);
define('NO_AGENT_STATISTIC', 'Y');
define('NOT_CHECK_PERMISSIONS', true);
define('BX_NO_ACCELERATOR_RESET', true);
define('DisableEventsCheck', true);
define('NO_AGENT_CHECK', true);
// define('SITE_ID', '<ID сайта>'); // если нужен контекст конкретного сайта

// 3. Служебный пролог: dbconn.php, БД, init.php, события — без шаблона
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

// 4. Лимиты — только после пролога
@set_time_limit(0);
@ignore_user_abort(true);

// 5. Работа: автозагрузчик решения из init.php уже подключён
\Vendor\Project\Orders\Recalculator::run();

// 6. Завершение: закрыть БД, выполнить фоновые задания. Код после этой строки не выполнится
\CMain::FinalActions();
// Нужен свой код возврата для cron-мониторинга — вместо строки выше:
// \Bitrix\Main\Application::getInstance()->end($errors ? 1 : 0);
```

Корень через `dirname(__DIR__, 3)` — вариант команды: один файл работает на стендах с разными
путями. Книга задаёт абсолютный путь явно (у неё — `/home/bitrix/www`). Если `/local` подключён
симлинком, `__DIR__` вернёт реальный путь — тогда корень задаём явно.

## Запуск

- **Только от владельца сайта, не от root.** От root ядро создаёт файлы кэша, которые веб-сервер
  потом не может сбросить, и портал читает устаревшие настройки. Это эмпирика вики, не книга —
  [[antipattern-cli-php-as-root]].
- В cron — то же правило:

```bash
# системный crontab: поле пользователя — владелец сайта
*/10 * * * * bitrix /usr/bin/php -f /home/bitrix/www/local/php_interface/console/orders-recalc.php >> <путь-к-логу> 2>&1
```

## Проверка результата

- Скрипт отрабатывает от владельца сайта без ошибок, изменения видны в веб-интерфейсе.
- Диагностику печатаем **после** пролога: любой вывод до него ломает старт сессии —
  `RuntimeException: Could not start session because headers have already been sent` (стенд).
- В `bitrix/managed_cache` и `bitrix/cache` нет файлов чужого владельца — команда проверки в
  [[antipattern-cli-php-as-root]].
- На портале с одним сайтом `SITE_ID` определяется сам (стенд: `s1`, `LANGUAGE_ID` `ru`);
  задавать до пролога обязательно там, где сайтов несколько.

## Чего избегать

- ❌ Запуска от root — см. «Запуск».
- ❌ Вывода (`echo`, `print_r`) до пролога — скрипт упадёт на старте сессии.
- ❌ `set_time_limit` / `ignore_user_abort` до пролога.
- ❌ Скрипта без `FinalActions()`, эпилога или `Application::end()`.
- ❌ Опоры на `$_SERVER` (кроме заданного вами `DOCUMENT_ROOT`), сессию и «текущего пользователя».
  Если метод модуля сам проверяет права текущего пользователя, в консоли это гость — видит ли он
  нужные данные, **проверить на стенде**.
- ❌ Тяжёлой операции в агенте вместо cron-скрипта — [[pattern-agents-vs-cron]].

## Связанное
- [[pattern-agents-vs-cron]] — когда cron, а когда агент
- [[concept-request-lifecycle]] — шаги служебного пролога и эпилога
- [[antipattern-cli-php-as-root]] — владелец процесса и порча кэша
- [[pattern-local-solution-structure]] — каталог `console/` в структуре решения
- [[entity-php-interface]] — `dbconn.php` и `init.php`

[← Ядро D7](_index-core-d7.md)
