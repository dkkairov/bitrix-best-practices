---
title: "Консольный PHP от root: Option::set пишет, Option::get отдаёт старое"
type: antipattern
module: server-admin
edition: box
status: verified
provenance: empirical
verified: "2026-09-15 / коробка на «Веб-окружении», владелец сайта bitrix, main 26.700"
tags: [cli, кэш, managed_cache, option, деплой, права, linux]
sources: []
related: ["[[recipe-safe-module-deploy]]", "[[checklist-windows-to-linux-deploy]]", "[[pattern-agents-vs-cron]]"]
aliases: ["bitrix24-cli-root-cache-owner"]
updated: "2026-09-18"
---

# Консольный PHP от root портит кэш портала

## Как выглядит

Модуль хранит настройку в `Option`. После сохранения нового значения:

- в базе `b_option.VALUE` — **новое** значение;
- `Option::get()` отдаёт **старое** — и в вебе, и в свежем консольном процессе, при любом `siteId`;
- страница, которая сама пишет опцию и тут же читает, видит старое.

Выглядит как баг модуля («сохранил — не применилось»), а не как проблема сервера. Отсюда часы,
потраченные не туда.

## Почему это плохо

Скрипт заливки запускал `php -f .../selfcheck.php` (с `prolog_before.php`) **от root**. Ядро при
чтении настроек создаёт файлы управляемого кэша (`bitrix/managed_cache/…/b_option…`) — с владельцем
root. Веб-сервер работает от владельца сайта и **не может их удалить** при `Option::set()`: запись
в базу проходит, сброс кэша — нет. Дальше все читают устаревший файл.

Страдает не только ваш модуль: **любые** настройки портала, записанные из веба после такого
запуска, читаются старыми до ручного сброса кэша.

Отдельно коварно: самопроверка, запущенная от root, сама себе ставит «ok» — она читает тот же
устаревший кэш, из которого только что создала файл.

## Почему так делают

По SSH заходят под root, потому что так проще, и запускают скрипт «одной командой». Ошибки при
этом нет: PHP отрабатывает, скрипт печатает успех.

## Как исправить

**Не допускать.** Любой консольный PHP с подключённым ядром — от владельца сайта:

```bash
OWNER=$(stat -c %U /home/bitrix/www)
runuser -u "$OWNER" -- env BX_DOCUMENT_ROOT=/home/bitrix/www php -f script.php
# или: sudo -u bitrix php -f script.php
```

Временные файлы для такого запуска — `chmod 644`, иначе пользователь сайта их не прочитает.
`php -l` (линт, без ядра) от root запускать можно.

В конце скрипта заливки — страховка:

```bash
for dir in bitrix/managed_cache bitrix/cache bitrix/stack_cache; do
  find "$DOC_ROOT/$dir" ! -user "$OWNER" -exec chown "$OWNER:$GROUP" {} +
done
```

### Как обнаружить

Сравнить то, что отдаёт API, с тем, что лежит в базе:

```php
$raw = \Bitrix\Main\Application::getConnection()->query(
    "SELECT VALUE FROM b_option WHERE MODULE_ID = 'vendor.module' AND NAME = 'x'"
)->fetch();
$api = \Bitrix\Main\Config\Option::get('vendor.module', 'x', '');
// $raw['VALUE'] !== $api  →  кэш настроек не обновляется
```

Полезно встроить такую проверку прямо в админ-страницу модуля и показывать расхождение
администратору.

### Как вылечить

```bash
find /home/bitrix/www/bitrix/managed_cache -path "*b_option*" -type f -delete
chown -R bitrix:bitrix /home/bitrix/www/bitrix/managed_cache /home/bitrix/www/bitrix/cache
```

## Профилактика
- Первое, что проверять при «опция не меняется», — владельцев файлов в `bitrix/managed_cache`.
- Скрипты установки/обновления сами вызывают PHP через `runuser -u <владелец>` и в конце
  возвращают владельца файлам кэша — [[recipe-safe-module-deploy]].
- В cron то же правило: задача запускается от владельца сайта, не от root —
  [[pattern-agents-vs-cron]].
- Помнить про `Option::get(…, $siteId)`: сначала ищется значение сайта, потом общее. Если значения
  расходятся, проверять `SELECT … FROM b_option WHERE MODULE_ID = … ORDER BY SITE_ID`.

## Связанное
- [[recipe-safe-module-deploy]] — скрипты заливки с правильным владельцем
- [[checklist-windows-to-linux-deploy]] — остальные грабли заливки

[← Администрирование сервера](_index-server-admin.md)
