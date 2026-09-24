---
title: "ServiceLocator: регистрация и подмена сервисов без правки ядра"
type: concept
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-22 / коробка в Docker, main 26.750.0: форма constructor (только замыкание) проверена прогоном; 2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Технологии — Локатор служб, Свой код — kernel.php, CRM — Подмена фабрики; 2026-09-24 — сверка с документацией фреймворка и стендом: PSR-11, constructorParams, ключ-интерфейс, registerByGlobalSettings/registerByModuleSettings"
tags: [d7, di, servicelocator, сервисы, kernel-php, подмена]
sources: ["[[source-devbook-core-d7]]", "[[source-devbook-dev-rules]]"]
related: ["[[concept-crm-universal-api]]", "[[recipe-crm-history-all-fields]]", "[[concept-bitrix-naming-conventions]]", "[[concept-change-invasiveness-hierarchy]]", "[[pattern-local-solution-structure]]", "[[recipe-smart-process-factory-customization]]"]
aliases: ["bitrix24-service-locator"]
updated: "2026-09-24"
---

# ServiceLocator

**TL;DR:** `\Bitrix\Main\DI\ServiceLocator` — простой DI-контейнер ядра: сервисы регистрируются по
строковому имени и достаются откуда угодно. На нём же стоит подмена сервисов CRM. Источник —
[Локатор служб](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Lokator_sluzb.html)
«Книги разработчика».

> **Сверено с книгой 2026-09-21.** Атрибуция исправлена (материал — из книги, а не из apidocs, где
> описан REST). Добавлены правила проектирования сервиса из книги. Уточнено: книга сама себе
> противоречит в регистре имени сервиса — записан выбор команды; подмену контейнера CRM книга
> допускает, а конфликт возможен с **модулями** Маркетплейса.

## API

| Метод | Поведение |
|---|---|
| `::getInstance()` | синглтон |
| `has($code): bool` | есть ли сервис |
| `get($code): mixed` | вернуть сервис; нет — исключение, реализующее `\Psr\Container\NotFoundExceptionInterface` (PSR-11) |
| `addInstance($code, $service)` | зарегистрировать **уже созданный** экземпляр |
| `addInstanceLazy($code, $configuration)` | зарегистрировать **по описанию**; создание отложено до первого `get` |

```php
\Bitrix\Main\DI\ServiceLocator::getInstance()->addInstanceLazy('vendor.exchange.service', [
    'className' => \Vendor\Exchange\Service::class,
]);
// либо своя фабрика — только замыканием:
// 'constructor' => static fn () => \Vendor\Exchange\ServiceBuilder::buildInstance()
```

> **`constructor` принимает `Closure`, а не массив-колбэк.** Локатор хранит значение как имя класса
> и создаёт объект через `new $class(...)`; замыкание он вызывает, а массив `['Класс', 'метод']`
> ломается на первом `get()`: `Error: Class name must be a valid object or a string`
> (`main` 26.750.0, стенд 2026-09-22). Само ядро регистрирует сервисы замыканиями.

## Сверка с документацией фреймворка (2026-09-24)

Документация [docs.1c-bitrix.ru](https://docs.1c-bitrix.ru/pages/framework/service-locator.html)
добавляет к сказанному книгой:

- локатор доступен **с `main` 20.5.400** и реализует PSR-11 (`Psr\Container\ContainerInterface` —
  подтверждено на стенде);
- у описания сервиса есть третий вариант — **`constructorParams`**: класс плюс аргументы
  конструктора. Проверено прогоном (26.750.0):

  ```php
  $locator->addInstanceLazy('vendor.date', [
      'className' => \Bitrix\Main\Type\DateTime::class,
      'constructorParams' => ['2026-01-01 00:00:00', 'Y-m-d H:i:s'],
  ]);
  ```

- **ключом может быть имя интерфейса**, не только строка с точками: так ядро отдаёт временное
  хранилище (`Data\Storage\PersistentStorageInterface`, [[concept-d7-session-storage]]);
- автоматическое разрешение зависимостей (autowire) включено по умолчанию;
- кроме известных методов, на стенде есть `registerByGlobalSettings()` и
  `registerByModuleSettings()` — ими ядро само подхватывает секцию `services` из
  `.settings.php` площадки и модулей ([[entity-settings-php]]).

## Соглашение именования

- Имя — минимум из двух частей: вендор и имя сервиса; вложенность больше четырёх уровней читается
  тяжело (книга).
- **Регистр — книга противоречит сама себе:** в рекомендациях локатора советует camelCase, а шаблон
  `kernel.php` требует «вендор обязателен, только нижний регистр»
  ([kernel.php](https://bx24devbook.website.yandexcloud.net/Razrabotka/Struktura_papki_local/Svoj_kod.html#kernel-php)).
  **Выбор команды:** нижний регистр через точку, как у сервисов ядра (`main.validation.service`,
  `crm.service.container`): `vendor.exchange.service`. Анти-примеры: `VENDOR_SOME_SERVICE`,
  `COOL_SERVICE` (нет вендора), `Ve.NdOr.service` (смешанный регистр). См.
  [[concept-bitrix-naming-conventions]].

## Три места регистрации

| Способ | Доступен сразу | Нужен `Loader::includeModule` | Без правки файлов продукта |
|---|:---:|:---:|:---:|
| `/bitrix/.settings.php`, раздел `services` | да | нет | **нет** |
| `.settings.php` в корне своего модуля | нет | да | да |
| Через API (`addInstanceLazy` в `/local/php_interface/kernel.php`, подключённом из `init.php`) | да | нет | да |

Первый способ книга настоятельно не рекомендует: файл не хранится в системе контроля версий
([через .settings.php](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Lokator_sluzb.html#cerez-settings-php)).
Для проектной разработки основной — третий ([[pattern-local-solution-structure]]); второй — когда
сервис принадлежит модулю и нужен только после его подключения (практика команды).

## Правила проектирования сервиса (книга)

([рекомендации](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Lokator_sluzb.html#rekomendacii))
- Сервис не хранит состояние; исключение — кэш. Если кэш есть — методы его сброса и флаг «работать
  без кэша».
- Вызывающий код проверяет, что сервис есть (`has()`), — особенно если его регистрирует модуль.
- Код регистрации не бросает исключений: сервис либо готов, либо отсутствует.
- Сервис не требует донастройки в месте вызова.
- В `kernel.php` — полные имена классов без `use`, а логику создания — в класс-построитель
  ([через API](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Lokator_sluzb.html#cerez-api)).

## Зачем это на внедрении

`ServiceLocator` — механизм, которым CRM отдаёт свои фабрики: `Container::getInstance()` — это
`ServiceLocator::getInstance()->get('crm.service.container')`, а `Container::getFactory()` сначала
спрашивает локатор. Поэтому регистрация своей фабрики под именем
`crm.service.factory.dynamic.<entityTypeId>` (только для смарт-процессов) перехватывает создание
фабрики конкретного типа. Книга описывает это как рабочее «окно» до первого вызова фабрики, а не как
официальный контракт: регистрировать нужно раньше первого `getFactory()` в хите
([Подмена фабрики](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Kastomizacia/Podmena_fabriki.html#podmena-fabriki-bez-podmeny-kontejnera)).
Практика — [[recipe-smart-process-factory-customization]], [[recipe-crm-history-all-fields]].

**Практика команды:** подменяем **один** сервис фабрики, а не контейнер CRM целиком. Книга допускает
и подмену контейнера (для одной фабрики это избыточно), но предупреждает: некоторые **модули**
Маркетплейса сами подменяют контейнер — если `get_class()` контейнера показывает чужой класс,
разбираться с разработчиком модуля.

## Антипаттерн и лечение

Толстое замыкание прямо в `kernel.php`:

```php
$locator->addInstanceLazy('vendor.currency.manager', [
    'constructor' => function () { /* много строк логики */ }
]);
```

Лечение — отдельный класс-построитель:

```php
$locator->addInstanceLazy('vendor.currency.manager', [
    'constructor' => static fn () => \Vendor\Currency\ManagerBuilder::buildInstance()
]);
```

## Подводные камни
- Шаблон `kernel.php` в книге оборачивает регистрацию в
  `class_exists('\Bitrix\Main\DI\ServiceLocator')` — на случай версии платформы без локатора; с
  какой версии класс появился, книга не говорит.
- Ленивая регистрация означает, что ошибка в конфигурации вылезет **при первом `get`**, а не при
  старте — то есть в произвольном месте портала. Для сервисов в горячем пути нужен
  [[pattern-module-self-disabling-guard|сторож]] (практика команды).

## Связанные страницы
- [[concept-crm-universal-api]] — главный потребитель локатора в CRM
- [[pattern-local-solution-structure]] — где в проекте лежит `kernel.php`

[← Ядро D7](_index-core-d7.md)
