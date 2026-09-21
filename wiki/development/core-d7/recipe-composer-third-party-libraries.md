---
title: "Сторонние Composer-пакеты (dompdf, PhpWord) в коробке"
type: recipe
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-21 / Книга разработчика Bitrix24 (структура /local, «Свой код») + практика команды"
tags: [composer, vendor, автозагрузка, библиотеки, local, php_interface, dompdf, phpword]
related: ["[[concept-code-namespaces-and-autoloading]]", "[[antipattern-box-core-modification]]", "[[recipe-module-structure-and-install]]", "[[concept-bitrix-framework-vs-bitrix24]]", "[[entity-local-directory]]"]
aliases: ["composer-third-party-libraries"]
updated: "2026-09-21"
---

# Сторонние Composer-пакеты (dompdf, PhpWord) в коробке

> **Изменено 2026-09-21.** По умолчанию `composer.json` и `vendor/` теперь лежат в
> `/local/php_interface/`, как требует «Книга разработчика Bitrix24» — эталонный источник
> (`CLAUDE.md` §9). Раньше здесь рекомендовался общий `local/vendor/` в корне `/local`;
> решение перейти на вариант книги принял пользователь.

**Когда применять:** нужно подключить PHP-библиотеку из Composer — генерация PDF (`dompdf/dompdf`),
документы Word (`phpoffice/phpword`), Excel, QR и т.п. — в коробке Bitrix24/БУС.
**Только коробка:** в облаке файлов и Composer нет — там сторонние библиотеки невозможны
(см. [[concept-bitrix-framework-vs-bitrix24|Framework vs Bitrix24]]).

## Куда класть: одно общее `vendor/` в `php_interface`

Книга: `vendor` и `composer.json` не должны лежать в корне `/local` — их место внутри
`/local/php_interface/` ([структура папки local](https://bx24devbook.website.yandexcloud.net/Razrabotka/Struktura_papki_local/Osnovnoe.html),
[«Свой код»](https://bx24devbook.website.yandexcloud.net/Razrabotka/Struktura_papki_local/Svoj_kod.html)).
Каталоги `/local` подробно — [[entity-local-directory|Папка /local]].

**Одно общее `vendor/` на проект:**
- ✅ одна версия каждой библиотеки, один автозагрузчик, предсказуемое поведение;
- ❌ «по `vendor/` в каждом модуле» → дублирование и риск конфликта версий: если два модуля тянут
  разные версии одного пакета, PSR-4 класс загрузится один раз — второй модуль получит чужую версию.

**Исключение:** автономный модуль, который распространяется отдельно (Маркетплейс, другой клиент) —
тогда `vendor/` кладут внутрь него ради самодостаточности
(см. [[recipe-module-versioning-and-private-distribution|версии и дистрибуция]]).

## Вариант 1 (по умолчанию, по книге): `/local/php_interface/`
```bash
cd local/php_interface
composer require dompdf/dompdf phpoffice/phpword
```
Появятся `local/php_interface/composer.json`, `composer.lock` и `vendor/autoload.php`.
Автозагрузчик подключаем **один раз** в `local/php_interface/init.php`, если файл есть:
```php
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}
```
Использование где угодно:
```php
use Dompdf\Dompdf;

$dompdf = new Dompdf();
$dompdf->loadHtml('<h1>Счёт</h1>');
$dompdf->render();
$pdf = $dompdf->output();
```

> **Неточность в книге:** в дереве папок `php_interface` каталог назван `/vendors/`, а в коде
> `init.php` той же страницы подключается `/vendor/autoload.php`. Composer по умолчанию создаёт
> `vendor/` — используем его, как в коде книги (сверено 2026-09-21).

## Вариант 2 (практика команды): базовый модуль `vendor.core`
Не из книги — наша практика для [[pattern-module-library-monorepo|библиотеки модулей]], когда
зависимости нужно версионировать вместе с кодом модулей. Заводим базовый модуль
`local/modules/vendor.core/` с `composer.json` и `vendor/` и подключаем автозагрузчик в его
`include.php`:
```php
require_once __DIR__ . '/vendor/autoload.php';
```
Остальные модули объявляют зависимость и вызывают `Loader::includeModule('vendor.core')` —
автозагрузчик поднимется один раз. Структура модуля —
[[recipe-module-structure-and-install|Структура модуля и установка]]. На одном проекте выбираем
**один** вариант: оба сразу — это два автозагрузчика и риск разных версий одного пакета.

## Чего избегать
- ❌ **Не класть `vendor/` и `composer.json` в корень `/local/`** — так говорит книга; это же прежний
  вариант этой страницы, от которого отказались 2026-09-21.
- ❌ **Не класть в `/bitrix/vendor/`** и не править `/bitrix/composer.json` — это
  [[antipattern-box-core-modification|правка ядра]]; свои зависимости затрутся при обновлении.
- ⚠️ **Не дублируй то, что уже есть в ядре.** Проверь `/bitrix/vendor/composer/installed.json` — часть
  библиотек ядро уже тянет; своя копия другой версии = конфликт.
- ⚠️ **Composer-autoload ≠ Bitrix-autoload.** PSR-4 пакеты грузятся composer-автозагрузчиком (его
  подключаем явно); классы модулей — Bitrix-автозагрузкой
  ([[concept-code-namespaces-and-autoloading|стандарт D7]]). Это два параллельных механизма.

## Деплой
- Коммить `composer.json` + `composer.lock`; `vendor/` — в `.gitignore`; на сервере `composer install`
  в `local/php_interface/`.
- Если на проде нет Composer — коммить `vendor/` целиком (зафиксированную версию).
- **Переезд со старого варианта:** перенести `composer.json`, `composer.lock` из `local/` в
  `local/php_interface/`, выполнить там `composer install`, поправить путь в `init.php`, удалить
  `local/vendor/` и старые файлы; проверить, что классы пакетов по-прежнему находятся.

## Связанное
- [[entity-local-directory|Папка /local]] — что где лежит
- [[concept-code-namespaces-and-autoloading|Организация кода и автозагрузка]]
- [[concept-bitrix-framework-vs-bitrix24|Framework vs Bitrix24]] — почему это только коробка
- [[recipe-module-structure-and-install|Структура модуля и установка]]

[← Ядро D7](_index-core-d7.md)
