---
title: "Расширения JS и CSS: свой bundle вместо тегов script"
type: concept
module: templates-design
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / коробка в Docker, main 26.750.0: UI\\Extension::load() есть, каталог /bitrix/js существует, /local/js на стенде нет; образец конфига — /bitrix/js/main/loader/config.php (ключи css, js, rel, skip_core); текст — документация фреймворка (docs.1c-bitrix.ru, «Расширения»)"
tags: [js, css, расширения, extension, сборка, ui]
sources: []
related: ["[[concept-ui-subsystem]]", "[[recipe-custom-list-page-filter-grid]]", "[[pattern-local-solution-structure]]", "[[recipe-engine-controller-action]]"]
aliases: []
updated: "2026-09-24"
---

# Расширения JS и CSS: свой bundle вместо тегов script

**TL;DR:** расширение — способ оформить свой JS и CSS так, как это делает ядро: папка с исходниками,
собранный бандл, объявленные зависимости и одна строка подключения. Ядро само подтянет зависимости
и не подключит одно и то же дважды.

**Когда применять:** свой интерфейс сложнее одного обработчика клика — свой раздел, виджет в
карточке, страница со сложным поведением. Три строки скрипта в шаблоне расширением оформлять не
нужно.

## Где лежит

- ядро: `/bitrix/js/<модуль>/<расширение>/`;
- наше: **`/local/js/<модуль>/<расширение>/`** — рядом с остальным кодом проекта
  ([[pattern-local-solution-structure]]). На чистом стенде каталога `/local/js` нет, его заводят
  под первое расширение.

Состав папки: `src/` (исходники ES6), `dist/` (собранный бандл), `bundle.config.js` (настройки
сборщика), `config.php` (описание расширения). По желанию — `lang/`, `test/`, `@types/`.

## `config.php`

```php
return [
    'css' => 'dist/vendor.orders.bundle.css',
    'js'  => 'dist/vendor.orders.bundle.js',
    'rel' => ['main.core', 'ui.buttons'],
    'skip_core' => false,
];
```

Так выглядит и штатный `/bitrix/js/main/loader/config.php` — формат один и тот же. `rel` —
зависимости, которые ядро подключит раньше; `skip_core` отключает автоматическое подключение ядра
JS.

## Подключение

```php
\Bitrix\Main\UI\Extension::load('vendor.orders');
```

Из JS — импортом (`import {Orders} from 'vendor.orders'`) или `Runtime.loadExtension('vendor.orders')`.
Имя пространства (`BX.Vendor.Orders`) задаётся в `bundle.config.js`.

Сборка — через `@bitrix/cli`; он же обновляет версии в зависимостях.

## Почему не `<script src>` в шаблоне

- порядок загрузки и дубли ядро решает само;
- зависимости объявлены явно, а не «работает, пока файл выше по странице»;
- расширение подключается и из контроллера — `renderExtension()`
  ([[recipe-engine-controller-action]]);
- файлы попадают в общий механизм объединения и кэширования статики.

## Ловушки

- **Правим `src/`, а браузер грузит `dist/`.** Забыли пересобрать — «изменения не применились».
  Собранный бандл кладём в git вместе с исходником: на бою `@bitrix/cli` не запускают.
- **Имя расширения глобально** — берём префикс вендора, как в модулях.
- **`main.core` в `rel` нужен почти всегда** — без него нет `BX.*`.
- **Расширения ядра меняются между версиями.** Зависимость на внутреннее расширение чужого модуля —
  та же правка ядра, только сбоку ([[concept-ui-subsystem]]).

## Связанные страницы
- [[concept-ui-subsystem]] — тулбар, фильтр, грид
- [[recipe-custom-list-page-filter-grid]] — где это подключается на практике
- [[recipe-engine-controller-action]] — `renderExtension()`

[← Шаблоны и дизайн](_index-templates-design.md)
