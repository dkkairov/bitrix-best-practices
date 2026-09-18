---
title: "\\Bitrix\\Main\\ModuleManager"
type: entity
module: core-d7
edition: box
status: draft
provenance: mixed
verified: ""
tags: [модули, установка, d7, класс]
sources: ["[[source-devbook-core-d7]]"]
related: ["[[recipe-module-structure-and-install]]", "[[entity-loader]]", "[[recipe-module-versioning-and-private-distribution]]"]
aliases: []
updated: "2026-09-18"
---

# `\Bitrix\Main\ModuleManager`

> **Черновик.** Состав методов собран по употреблению в проверенных страницах вики. Сверка
> 2026-09-18 не удалась: страница справочника D7 по `ModuleManager` на `dev.1c-bitrix.ru` не отдала
> содержимое, а поиск выводит на функции **старого** ядра (`RegisterModule`, `IsModuleInstalled`).
> Соседние классы того же модуля сверить удалось — см. [[entity-loader]] и
> [[entity-config-option]]. Снять `draft` после сверки по исходникам `main` или по справочнику.

**Что это:** регистрация и снятие регистрации модуля, проверка установленности. D7-замена старому
`CModule::RegisterModule()`.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | класс со статическими методами |
| Модуль | `main` |
| Edition | box |

## Что используем

```php
use Bitrix\Main\ModuleManager;

ModuleManager::registerModule($moduleId);      // в DoInstall
ModuleManager::unRegisterModule($moduleId);    // в DoUninstall
ModuleManager::isModuleInstalled($moduleId);   // проверка
```

## Регистрация ≠ загрузка

Это две разные вещи, и путаница между ними — частый источник «модуль установлен, но не работает»:

| Что | Чем | Когда |
|---|---|---|
| **Регистрация** | `ModuleManager::registerModule()` | один раз, при установке |
| **Загрузка** | [[entity-loader\|`Loader::includeModule()`]] | на каждом хите, где модуль нужен |

Зарегистрированный модуль **не грузится сам** — его `include.php` выполнится только при
`includeModule`. Чтобы модуль работал на каждом хите, установщик вписывает вызов в `init.php`:
[[recipe-d7-orm-event-subscription]].

## Подводные камни

- **`CModule::RegisterModule()` — старый API.** Работает, но в новом коде используем
  `ModuleManager`.
- **`isModuleInstalled()` отвечает на вопрос «зарегистрирован ли», а не «загружен ли сейчас»** —
  для второго нужен результат `Loader::includeModule()`.
- Снятие регистрации в `DoUninstall` — не вся уборка: события, опции, агенты и записи в `init.php`
  снимаются отдельно ([[recipe-module-structure-and-install]]).

## Связанное
- [[entity-loader]] — загрузка модуля
- [[recipe-module-structure-and-install]] — где эти вызовы стоят в установщике

[← Ядро D7](_index-core-d7.md)
