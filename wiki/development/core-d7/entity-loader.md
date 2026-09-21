---
title: "\\Bitrix\\Main\\Loader"
type: entity
module: core-d7
edition: box
status: verified
provenance: documented
verified: "2026-09-18 / dev.1c-bitrix.ru, справочник D7 \Bitrix\Main\Loader"
tags: [загрузка-модулей, автозагрузка, d7, класс]
sources: []
related: ["[[recipe-module-structure-and-install]]", "[[concept-code-namespaces-and-autoloading]]", "[[entity-module-manager]]", "[[recipe-d7-orm-event-subscription]]"]
aliases: []
updated: "2026-09-21"
---

# `\Bitrix\Main\Loader`

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

| Метод | Назначение |
|---|---|
| `includeModule($moduleName)` | подключить модуль по имени; результат **обязательно проверять** |
| `includeSharewareModule($moduleName)` | подключить партнёрский модуль (с 14.0.2) |
| `registerAutoLoadClasses()` | зарегистрировать классы для автозагрузки |
| `registerNamespace()` | зарегистрировать пространство имён |
| `getDocumentRoot()` | корень документов (с 14.0.0) |
| `getLocal()` / `getPersonal()` | поиск файла в `/local` или `/bitrix` |
| `autoLoad()` | загрузка зарегистрированных для автозагрузки |

`requireModule($moduleId)` — «подключить или бросить исключение» — в справочнике D7 на момент
сверки **не перечислен**, хотя широко используется в современном коде ядра (в том числе в
ленивых фабриках CRM). «Книга разработчика» тоже подключает модули через
`Loader::requireModule()` в примерах (например, глава «Модуль Задачи») — но отдельной главы про
`Loader` в книге нет. Если пишете под старую версию, проверьте наличие метода.

> **Источник уточнён 2026-09-21.** Ссылка на конспект книги убрана из `sources`: страница сверена
> по справочнику D7 на dev.1c-bitrix.ru (см. `verified`), книга класс `Loader` не разбирает.

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
