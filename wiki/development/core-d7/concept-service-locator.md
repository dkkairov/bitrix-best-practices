---
title: "ServiceLocator: регистрация и подмена сервисов без правки ядра"
type: concept
module: core-d7
edition: box
status: verified
provenance: documented
verified: "2026-06-01 / документация модуля main (dev.1c-bitrix.ru, apidocs.bitrix24.ru)"
tags: [d7, di, servicelocator, сервисы, kernel-php, подмена]
sources: []
related: ["[[concept-crm-universal-api]]", "[[recipe-crm-history-all-fields]]", "[[concept-bitrix-naming-conventions]]", "[[concept-change-invasiveness-hierarchy]]"]
aliases: ["bitrix24-service-locator"]
updated: "2026-09-18"
---

# ServiceLocator

**TL;DR:** `\Bitrix\Main\DI\ServiceLocator` — простой DI-контейнер ядра: сервисы регистрируются по
строковому имени и достаются откуда угодно. На нём же стоит штатная подмена сервисов CRM.

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
// либо своя фабрика:
// 'constructor' => [\Vendor\Exchange\ServiceBuilder::class, 'buildInstance']
```

## Соглашение именования

Код сервиса **обязательно** содержит вендора и **обязательно** в нижнем регистре:
`vendor.exchange.service`. Анти-примеры: `VENDOR_SOME_SERVICE`, `COOL_SERVICE` (нет вендора),
`Ve.NdOr.service` (смешанный регистр). См. [[concept-bitrix-naming-conventions]].

## Три места регистрации

| Способ | Доступен сразу | Нужен `Loader::includeModule` | Без правки файлов продукта |
|---|:---:|:---:|:---:|
| `/bitrix/.settings.php`, раздел `services` | да | нет | **нет** |
| `.settings.php` своего модуля | нет | да | да |
| Через API (`addInstanceLazy` в `/local/php_interface/kernel.php`, подключённом из `init.php`) | да | нет | да |

Первый способ не использовать: файл лежит в ядре, вне репозитория
([[concept-change-invasiveness-hierarchy]]). Для проектной разработки основной — третий,
для поставляемой функциональности — второй.

## Зачем это на внедрении

`ServiceLocator` — механизм, которым CRM отдаёт свои фабрики: `Container::getFactory()` сначала
спрашивает локатор. Поэтому регистрация своей фабрики под именем
`crm.service.factory.dynamic.<entityTypeId>` перехватывает создание фабрики конкретного типа — это
штатная точка расширения, а не трюк. Практика — [[recipe-crm-history-all-fields]].

Подменять **один** сервис, а не контейнер CRM целиком: подмена контейнера конфликтует с
приложениями маркетплейса, которые делают то же самое.

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
    'constructor' => [\Vendor\Currency\ManagerBuilder::class, 'buildInstance']
]);
```

## Подводные камни
- Класс появился относительно недавно: в коде, который должен работать на старых версиях, встречается
  `class_exists('\Bitrix\Main\DI\ServiceLocator')` перед использованием.
- Ленивая регистрация означает, что ошибка в конфигурации вылезет **при первом `get`**, а не при
  старте — то есть в произвольном месте портала. Для сервисов в горячем пути нужен
  [[pattern-module-self-disabling-guard|сторож]].

## Связанные страницы
- [[concept-crm-universal-api]] — главный потребитель локатора в CRM

[← Ядро D7](_index-core-d7.md)
