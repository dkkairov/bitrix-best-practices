---
title: "Сторонние Composer-пакеты (dompdf, PhpWord) в коробке"
type: recipe
module: core-d7
edition: box
status: verified
provenance: empirical
verified: "2026-06-21 / Bitrix Framework (коробка), практика команды"
tags: [composer, vendor, автозагрузка, библиотеки, local, dompdf, phpword]
related: ["[[concept-code-namespaces-and-autoloading]]", "[[antipattern-box-core-modification]]", "[[recipe-module-structure-and-install]]", "[[concept-bitrix-framework-vs-bitrix24]]"]
aliases: ["composer-third-party-libraries"]
updated: "2026-06-21"
---

# Сторонние Composer-пакеты (dompdf, PhpWord) в коробке

**Когда применять:** нужно подключить PHP-библиотеку из Composer — генерация PDF (`dompdf/dompdf`),
документы Word (`phpoffice/phpword`), Excel, QR и т.п. — в коробке Bitrix24/БУС.
**Только коробка:** в облаке файлов и Composer нет — там сторонние библиотеки невозможны
(см. [[concept-bitrix-framework-vs-bitrix24|Framework vs Bitrix24]]).

## Куда класть: одно общее `vendor/`, а не в каждый модуль

**По умолчанию — одно общее `vendor/` на проект:**
- ✅ одна версия каждой библиотеки, один автозагрузчик, предсказуемое поведение;
- ❌ «по `vendor/` в каждом модуле» → дублирование и риск конфликта версий: если два модуля тянут
  разные версии одного пакета, PSR-4 класс загрузится один раз — второй модуль получит чужую версию.

**Исключение:** автономный модуль, который распространяется отдельно (Маркетплейс, другой клиент) —
тогда `vendor/` кладут внутрь него ради самодостаточности
(см. [[recipe-module-versioning-and-private-distribution|версии и дистрибуция]]).

## Вариант 1 (по умолчанию): общий `local/vendor/`
```bash
cd local
composer require dompdf/dompdf phpoffice/phpword
```
Появятся `local/composer.json`, `local/composer.lock`, `local/vendor/autoload.php`.
Автозагрузчик подключаем **один раз** в `local/php_interface/init.php`:
```php
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';
```
Использование где угодно:
```php
use Dompdf\Dompdf;

$dompdf = new Dompdf();
$dompdf->loadHtml('<h1>Счёт</h1>');
$dompdf->render();
$pdf = $dompdf->output();
```

## Вариант 2 (зрело): базовый модуль `vendor.core`
Когда зависимостей много и нужно версионировать их как часть кода: заведи базовый модуль
`local/modules/vendor.core/` с `composer.json` и `vendor/`, подключи автозагрузчик в его `include.php`:
```php
require_once __DIR__ . '/vendor/autoload.php';
```
Остальные модули объявляют зависимость и вызывают `Loader::includeModule('vendor.core')` —
автозагрузчик поднимется один раз. Структура модуля —
[[recipe-module-structure-and-install|Структура модуля и установка]].

## Чего избегать
- ❌ **Не класть в `/bitrix/vendor/`** и не править `/bitrix/composer.json` — это
  [[antipattern-box-core-modification|правка ядра]]; свои зависимости затрутся при обновлении.
- ⚠️ **Не дублируй то, что уже есть в ядре.** Проверь `/bitrix/vendor/composer/installed.json` — часть
  библиотек ядро уже тянет; своя копия другой версии = конфликт.
- ⚠️ **Composer-autoload ≠ Bitrix-autoload.** PSR-4 пакеты грузятся composer-автозагрузчиком (его
  подключаем явно); классы модулей — Bitrix-автозагрузкой
  ([[concept-code-namespaces-and-autoloading|стандарт D7]]). Это два параллельных механизма.

## Деплой
- Коммить `composer.json` + `composer.lock`; `vendor/` — в `.gitignore`; на сервере `composer install`.
- Если на проде нет Composer — коммить `vendor/` целиком (зафиксированную версию).

## Связанное
- [[concept-code-namespaces-and-autoloading|Организация кода и автозагрузка]]
- [[concept-bitrix-framework-vs-bitrix24|Framework vs Bitrix24]] — почему это только коробка
- [[recipe-module-structure-and-install|Структура модуля и установка]]

[← Ядро D7](_index-core-d7.md)
