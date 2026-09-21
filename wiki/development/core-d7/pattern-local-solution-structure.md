---
title: "Решение в /local/php_interface: структура клиентского проекта"
type: pattern
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Структура папки local — Основное, Свой код, Миграции; сочетание с модульным стандартом — решение команды 2026-09-21"
tags: [local, php_interface, init-php, структура, решение, модуль, env, автозагрузка]
sources: ["[[source-devbook-dev-rules]]"]
related: ["[[pattern-module-based-development-standard]]", "[[entity-php-interface]]", "[[entity-local-directory]]", "[[pattern-events-over-core-modification]]", "[[concept-service-locator]]", "[[recipe-migrations-as-code]]", "[[recipe-composer-third-party-libraries]]"]
aliases: []
updated: "2026-09-21"
---

# Решение в `/local/php_interface`: структура клиентского проекта

**TL;DR:** код, написанный под одного клиента и не предназначенный для переноса, не оформляем
модулем и не сваливаем в `init.php`, а раскладываем «решением» по стандартной структуре
`/local/php_interface` из «Книги разработчика». Модуль — только для переиспользуемого кода.

## Проблема и контекст

Книга исходит из того, что Bitrix24 — монолитное приложение под конкретного клиента: модульный подход,
который продвигает 1С-Битрикс, рассчитан на «Управление сайтом», а в Bitrix24 проект по сути сам —
один большой модуль ([Свой код](https://bx24devbook.website.yandexcloud.net/Razrabotka/Struktura_papki_local/Svoj_kod.html)).
Отсюда две крайности, которых надо избежать:

- **модуль ради модуля** — установка, версии и дистрибуция для кода, который никуда не переедет;
- **всё в `init.php`** — файл на каждый хит разрастается, его боятся трогать, командная работа
  превращается в конфликты.

## Решение

Структура по книге (практика компании «ИТ-Интегратор Фьюжн», книга подаёт её как проверенную
отправную точку, а не догму):

```text
local/php_interface/
├── init.php        короткий: автозагрузчик classes/ и подключение файлов ниже
├── kernel.php      окружение (.env) и регистрация сервисов ServiceLocator
├── events.php      только подписки на события; код обработчиков — в классах
├── legacy.php      константы и устаревшие функции (в идеале пустой)
├── classes/        классы проекта по пространствам имён
├── console/        консольные скрипты и приложения
├── install/        инсталлеры: setup.php — точка входа, steps/ — шаги
├── composer.json   зависимости Composer
└── vendor/         пакеты Composer
```

> В дереве книги каталог пакетов назван `vendors/`, а в коде подключается `vendor/autoload.php`.
> В вики принят `vendor/` — [[recipe-composer-third-party-libraries]].

### `init.php` — только две вещи

Автозагрузчик для `classes/` и подключение остальных файлов (каждый — если существует). Логики здесь
нет ([Свой код → init.php](https://bx24devbook.website.yandexcloud.net/Razrabotka/Struktura_papki_local/Svoj_kod.html#init-php)).

```php
// local/php_interface/init.php
spl_autoload_register(static function (string $class): void {
    $base = __DIR__ . '/classes/';
    $path = str_replace('\\', '/', ltrim($class, '\\'));
    // 1) путь точно по namespace; 2) каждый сегмент с заглавной, остальное строчными
    $candidates = [
        $base . $path . '.php',
        $base . implode('/', array_map(
            static fn (string $part): string => ucfirst(strtolower($part)),
            explode('/', $path)
        )) . '.php',
    ];
    foreach ($candidates as $file) {
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

foreach (['kernel.php', 'events.php', 'vendor/autoload.php', 'legacy.php'] as $file) {
    if (is_file(__DIR__ . '/' . $file)) {
        require_once __DIR__ . '/' . $file;
    }
}
```

В файле класса должен быть объявлен правильный `namespace` — иначе класс не найдётся.

### `events.php` — подписки отдельно от кода

Файл содержит только перечень подписок; обработчик — статический метод класса из `classes/`. Для
событий старого ядра — `addEventHandlerCompatible`, для нового — `addEventHandler`: книга советует
разделять их явно, чтобы не путаться, когда обработчик меняет аргументы
([events.php](https://bx24devbook.website.yandexcloud.net/Razrabotka/Struktura_papki_local/Svoj_kod.html#events-php)).
Подробно — [[pattern-events-over-core-modification]].

### `kernel.php` — окружение и сервисы

**`.env` лежит выше `DOCUMENT_ROOT`** — чтобы его нельзя было открыть из браузера — и загружается в
окружение приложения ([kernel.php](https://bx24devbook.website.yandexcloud.net/Razrabotka/Struktura_papki_local/Svoj_kod.html#kernel-php)):

```php
// local/php_interface/kernel.php
$envFile = dirname($_SERVER['DOCUMENT_ROOT']) . '/.env';
if (is_file($envFile)) {
    $environment = \Bitrix\Main\Context::getCurrent()->getEnvironment();
    $values = $environment->getValues();
    foreach (parse_ini_file($envFile, true, INI_SCANNER_TYPED) as $key => $value) {
        $values[$key] = $value;
    }
    $environment->set($values);
}

if (class_exists(\Bitrix\Main\DI\ServiceLocator::class)) {
    \Bitrix\Main\DI\ServiceLocator::getInstance()->addInstanceLazy('vendor.project.exchange', [
        'className' => \Vendor\Project\Exchange\Client::class,
    ]);
}
```

Где держать переменные окружения — сравнение книги:

| | `.env` выше `DOCUMENT_ROOT` | Опции (`b_option`, админка) |
|---|---|---|
| Правка пользователем | нет | да |
| Перенос через VCS | да | плохо: при обновлении неясно, какое значение верное |
| Попадает в штатный бэкап | **нет** — доставку и восстановление файла продумать самим | да |

Сервисы — [[concept-service-locator]]. Секреты — только в `.env`, не в репозитории (§4.4 схемы).

### `legacy.php`, `install/`, `console/`

- `legacy.php` — место для констант (с осмысленным префиксом, по-английски) и устаревших функций
  вроде `custom_mail`; при рефакторинге содержимое переезжает в классы.
- `install/` — **инсталлеры**: скрипты, которые приводят портал к состоянию, нужному коду, не трогая
  созданное пользователями. Сравнение с миграциями — [[recipe-migrations-as-code]].
- `console/` — консольные скрипты; как в них выполняется ядро — [[concept-request-lifecycle]].

## Решение или модуль: как выбрать (подход команды)

Вопрос «модулем или нет?» задаётся первым (`CLAUDE.md` §9). Критерий книги — **переиспользуемость**
([решение vs модуль](https://bx24devbook.website.yandexcloud.net/Razrabotka/Struktura_papki_local/Svoj_kod.html#resenie-vs-modul)).
Признаки «решения, оформленного под модуль»:

1. для переноса на другую установку мало скопировать папку модуля;
2. для обновления модуля нужны ручные действия (заменить файлы, выполнить код, создать инфоблоки).

| Ситуация | Куда |
|----------|------|
| Код под одного клиента, переносить не будем | **решение** по этой странице |
| Функциональность библиотеки агентства, ставится на много коробок | **модуль** — [[pattern-module-based-development-standard]], [[pattern-module-library-monorepo]] |
| Клиентское решение использует модули агентства | решение подключает их через `Loader::includeModule()` (практика команды) |

> **Практика команды.** Модульный стандарт агентства (библиотека модулей, приватная дистрибуция) —
> осознанный выбор для переиспользуемого кода и согласуется с критерием книги. Для клиентского кода,
> который модулем не является, по умолчанию действует эта структура (решение 2026-09-21).

## Когда применять
- Любая доработка коробки под конкретного клиента, которая не станет модулем.
- Наведение порядка в проекте, где логика живёт в `init.php`.

## Чего избегать
- Логики в `init.php` и кода обработчиков в замыканиях прямо в подписке.
- `.env` внутри `DOCUMENT_ROOT` и в git; забытого `.env` при восстановлении из бэкапа.
- Модуля, который нельзя перенести копированием папки и обновить без ручных шагов.

## Последствия
- **Плюсы:** одно предсказуемое место для клиентского кода; маленькие файлы с одной ответственностью;
  код обработчиков переиспользуется и тестируется.
- **Минусы:** нет жизненного цикла модуля (`install`/`uninstall`) — изменения конфигурации портала
  переносятся инсталлерами или миграциями.

## Связанное
- [[entity-php-interface]] — системные файлы каталога (`dbconn.php`, `init.php` и соседи)
- [[entity-local-directory]] — какие каталоги допустимы в `/local`
- [[pattern-module-based-development-standard]] — когда всё-таки модуль

[← Ядро D7](_index-core-d7.md)
