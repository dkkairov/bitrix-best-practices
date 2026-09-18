---
title: "AJAX-действие со строчным именем контроллера: «Could not find description … in DefaultController»"
type: antipattern
module: core-d7
edition: box
status: verified
provenance: empirical
verified: "2026-09-15 / коробка: main 26.700.0, модуль в /local/modules/, живой запрос из браузера"
tags: [d7, controller, ajax, автозагрузка, регистр, local-modules]
sources: []
related: ["[[recipe-module-structure-and-install]]", "[[concept-code-namespaces-and-autoloading]]"]
aliases: ["bitrix24-ajax-controller-name-case"]
updated: "2026-09-18"
---

# AJAX-действие со строчным именем контроллера

## Как выглядит

Модуль в `/local/modules/vendor.module/` с D7-контроллером (`.settings.php` →
`controllers.defaultNamespace`) и своим `spl_autoload_register`. Вызов из JS:

```js
BX.ajax.runAction('vendor:module.rules.getFields', { data: {} });
// или /bitrix/services/main/ajax.php?action=vendor:module.rules.getFields
```

отвечает:

```
Could not find description of rules.getFields in Bitrix\Main\Engine\DefaultController
```

При этом класс `Vendor\Module\Controller\Rules` существует, `.settings.php` читается, модуль
подключён. Проверка из консольного скрипта проходит успешно — и это сбивает с толку сильнее всего.

## Почему это плохо

`Bitrix\Main\Engine\Resolver::buildControllerClassName()` берёт `defaultNamespace` из
`.settings.php` и **приводит его к нижнему регистру, если имя контроллера в действии начинается
со строчной буквы**:

```php
// do not lower if probably psr4
$firstLetter = mb_substr($controllerName, 0, 1);
if ($firstLetter === mb_strtolower($firstLetter))
{
    $defaultPath = mb_strtolower($defaultPath);
}
```

Для `rules.getFields` роутер ищет класс `vendor\module\controller\rules`. PHP к регистру имён
классов нечувствителен, но **автозагрузчик — чувствителен**:

```php
if (strpos($class, 'Vendor\\Module\\') !== 0) return;   // «vendor\module\…» не проходит
```

Класс не загружается, `ControllerBuilder` падает, роутер подставляет `DefaultController`, который
про ваше действие ничего не знает. Диагностика уходит не туда: ищут ошибку в `.settings.php`,
в правах, в самом действии.

## Почему так делают

Строчное имя выглядит естественно: в документации встречаются действия вида `module.action`, и
разделение «контроллер с большой буквы, метод с маленькой» неочевидно.

## Как исправить

Делать **оба** пункта.

**1. Имя контроллера в действии — с заглавной буквы** (PSR-4-стиль; такой namespace Bitrix не
трогает):

```js
BX.ajax.runAction('vendor:module.Rules.getFields', { data: {} });
```

**2. Автозагрузчик — без учёта регистра префикса**, с запасным путём к файлу (на Linux имена
файлов регистрозависимы) — код в [[recipe-module-structure-and-install]].

Второй пункт страхует от вызова со строчной буквы из чужого кода.

## Как воспроизвести и проверить

**Проверка из консоли врёт.** Если в CLI-скрипте сначала вызвать `class_exists()` в точном
регистре, класс загрузится, и `Resolver` дальше вернёт правильный контроллер. В веб-запросе класс
заранее никто не грузит — там ошибка. Воспроизводить нужно в браузере:

```js
await fetch('/bitrix/services/main/ajax.php?action=vendor:module.Rules.getFields', {
  method: 'POST', credentials: 'same-origin',
  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  body: 'sessid=' + BX.bitrix_sessid()
}).then(r => r.json())
```

| Действие | Ответ |
|---|---|
| `vendor:module.rules.getFields` | `error`: «Could not find description … in …DefaultController» |
| `vendor:module.Rules.getFields` | `success` |

## Профилактика
- В код-ревью модуля: имена действий в JS — `Controller.method` с заглавной.
- Автозагрузчик пишем сразу регистронезависимым — это одна строка.
- `.settings.php` (файл с точкой в начале) легко теряется при заливке папкой с Windows — без него
  `defaultNamespace` нет вообще. См. [[checklist-windows-to-linux-deploy]].
- Переименовывать файл контроллера в нижний регистр — **не** решение: остальные классы модуля
  грузятся по PSR-4 с заглавными.

## Связанное
- [[recipe-module-structure-and-install]] — правильный автозагрузчик
- [[concept-platform-reverse-engineering]] — как искать такое в исходниках ядра

[← Ядро D7](_index-core-d7.md)
