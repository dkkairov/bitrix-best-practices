---
title: "Консольные команды ядра: bitrix.php и свои команды"
type: recipe
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / коробка в Docker, main 26.750.0: /bitrix/bitrix.php на месте, но `php bitrix/bitrix.php list` отвечает «Symfony Console is not installed. Please install and configure composer in your project»; composer.json и vendor в корне отсутствуют, Symfony\\Component\\Console\\Command\\Command не загружен, секция console в конфигурации не задана; текст — документация фреймворка (docs.1c-bitrix.ru, «Консольные команды»)"
tags: [cli, консоль, composer, symfony-console, генераторы, модули]
sources: []
related: ["[[recipe-cli-script-bootstrap]]", "[[recipe-composer-third-party-libraries]]", "[[concept-messenger-queues]]", "[[recipe-module-structure-and-install]]"]
aliases: []
updated: "2026-09-24"
---

# Консольные команды ядра: `bitrix.php` и свои команды

**Результат:** понятно, что умеет штатная консоль ядра, почему она обычно молчит и как добавить
свою команду.

**Когда нужно:** генерация заготовок кода, обслуживание (обновления, индексация переводов),
обработчик очереди ([[concept-messenger-queues]]), свои операции для админов проекта.

## Сначала — про composer

```bash
cd /path/to/document_root/bitrix
php bitrix.php list
```

На **чистой коробке это не работает**. Стенд (26.750.0) отвечает:

```
Symfony Console is not installed.
Please install and configure composer in your project.
```

Консоль ядра построена на Symfony Console, а её ставит composer. На стенде нет ни `composer.json`,
ни каталога `vendor`, класс `Symfony\Component\Console\Command\Command` не загружается. Поэтому:

- **все команды ниже доступны только после настройки composer** в проекте
  ([[recipe-composer-third-party-libraries]]);
- пока его нет, разовые операции делаем обычным скриптом с подключением ядра
  ([[recipe-cli-script-bootstrap]]);
- путь к файлу — **`/bitrix/bitrix.php`**, а не `bitrix.php` в корне сайта, как пишет документация.

## Что есть из коробки

| Группа | Команды |
|---|---|
| генерация (с `main` 25.900.0) | `make:component`, `make:controller`, `make:tablet`, `make:agent`, `make:entity`, `make:event`, `make:eventhandler`, `make:message`, `make:messagehandler`, `make:module`, `make:request`, `make:service` |
| ORM | `orm:annotate` — аннотации полей сущностей для IDE |
| очереди | `messenger:consume` |
| локализация | `translate:index` |
| обновления | `update:modules`, `update:versions`, `update:languages` |

`php bitrix.php help <команда>` — справка. В cron добавляем `--no-interaction`, иначе команда может
ждать ответа.

## Своя команда

1. Класс — наследник `Symfony\Component\Console\Command\Command`.
2. Место — `lib/Cli/Command/` внутри своего модуля ([[recipe-module-structure-and-install]]).
3. Регистрация — в `.settings.php` **модуля**:

```php
'console' => ['value' => ['commands' => [
    \Vendor\Module\Cli\Command\Feature\RebuildCommand::class,
]]],
```

Имя команды собирается из пространства имён: `…\Cli\Command\Feature\RebuildCommand` → `feature:rebuild`.

## Ловушки

- **Команда — не место для бизнес-логики.** Она разбирает аргументы и зовёт сервис; тот же сервис
  должен работать из агента и контроллера.
- **От какого пользователя запускаем.** Консольный PHP от `root` портит права на кэше
  ([[antipattern-cli-php-as-root]]).
- **Долгая команда по cron** должна уметь запускаться повторно и не пересекаться сама с собой —
  блокировка по файлу или `Connection::lock()`.
- **`orm:annotate` и `make:*` меняют файлы проекта** — держим их под git и смотрим diff.

## Связанные страницы
- [[recipe-cli-script-bootstrap]] — скрипт без composer
- [[recipe-composer-third-party-libraries]] — как в коробке живёт composer
- [[concept-messenger-queues]] — команда `messenger:consume`

[← Ядро D7](_index-core-d7.md)
