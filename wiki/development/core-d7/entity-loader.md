---
title: "\\Bitrix\\Main\\Loader"
type: entity
module: core-d7
edition: box
status: draft
provenance: mixed
verified: ""
tags: [загрузка-модулей, автозагрузка, d7, класс]
sources: []
related: ["[[recipe-module-structure-and-install]]", "[[concept-code-namespaces-and-autoloading]]", "[[entity-module-manager]]", "[[recipe-d7-orm-event-subscription]]"]
aliases: []
updated: "2026-09-18"
---

# `\Bitrix\Main\Loader`

> **Черновик.** Страница собрана по употреблению класса в уже проверенных страницах этой вики.
> Постраничной сверки с первоисточником не было: `apidocs.bitrix24.ru` покрывает REST и облако,
> а не PHP-ядро коробки. Перед тем как ставить `status: verified`, сверьте по курсу
> «Bitrix Framework» или по исходникам модуля `main`.

**Что это:** подключение модулей и автозагрузка их классов. Самый часто вызываемый класс ядра в
наших страницах.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | класс со статическими методами |
| Модуль | `main` |
| Edition | box |

## Что используем

```php
use Bitrix\Main\Loader;

if (Loader::includeModule('crm')) {   // false, если модуля нет или он не установлен
    // работаем с CRM
}

Loader::requireModule('crm');          // бросает исключение вместо false
```

- `includeModule($moduleId): bool` — подключить, вернуть результат. Подходит, когда модуль
  опционален и есть запасной путь.
- `requireModule($moduleId): void` — подключить или упасть. Подходит там, где без модуля
  продолжать бессмысленно (например, внутри ленивой фабрики CRM).

Подключение модуля выполняет его `include.php` — именно поэтому там регистрируют обработчики
событий и сервисы ([[recipe-d7-orm-event-subscription]]).

## Подводные камни

- **Результат `includeModule` надо проверять.** Незакрытый `if` — классическая причина «белого
  экрана» на портале, где модуль не установлен.
- **`Loader::registerAutoLoadClasses()` для модулей в `/local/` не работает** — резолвит пути в
  `/bitrix/modules/`. Автозагрузка своего модуля делается через `spl_autoload_register` с
  `__DIR__`: [[recipe-module-structure-and-install]].
- **В `DoInstall` вызывать `Loader::includeModule($this->MODULE_ID)` до обращения к своим
  классам** — иначе автозагрузчик из `include.php` ещё не подключён.
- Подключение модуля не бесплатно: в горячем пути подключайте лениво, внутри замыкания сервиса,
  а не на каждом хите.

## Связанное
- [[entity-module-manager]] — регистрация модуля, а не его загрузка
- [[concept-code-namespaces-and-autoloading]] — как устроена автозагрузка

[← Ядро D7](_index-core-d7.md)
