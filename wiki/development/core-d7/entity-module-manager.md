---
title: "\\Bitrix\\Main\\ModuleManager"
type: entity
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-23 / коробка в Docker, main 26.750.0: состав методов, механика registerModule/unRegisterModule, кэш списка модулей и поведение getVersion — по коду main/lib/ModuleManager.php и прогону"
tags: [модули, установка, агенты, права, d7, класс]
sources: []
related: ["[[recipe-module-structure-and-install]]", "[[entity-loader]]", "[[recipe-module-versioning-and-private-distribution]]", "[[entity-main-application]]"]
aliases: []
updated: "2026-09-23"
---

# `\Bitrix\Main\ModuleManager`

**Что это:** регистрация и снятие регистрации модуля, проверка установленности, версия модуля.
D7-замена старому `CModule::RegisterModule()`.

> **Сверено по исходникам 2026-09-23.** Прежде страница была черновиком: справочник D7 на
> `dev.1c-bitrix.ru` не отдавал страницу класса, а поиск выводил на функции старого ядра. Факты ниже
> сняты с кода `main/lib/ModuleManager.php` и прогона на стенде — тем же способом, что и
> [[entity-main-application]].

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | класс со статическими методами |
| Модуль | `main` |
| Хранилище | таблица `b_module` (`\Bitrix\Main\ModuleTable`) |
| Edition | box |

## Методы

```php
use Bitrix\Main\ModuleManager;

ModuleManager::registerModule($moduleId);      // в DoInstall
ModuleManager::unRegisterModule($moduleId);    // в DoUninstall
ModuleManager::isModuleInstalled($moduleId);   // проверка
ModuleManager::getVersion('crm');              // '26.800.0'
```

| Метод | Что делает |
|---|---|
| `registerModule($name)` | зарегистрировать модуль |
| `unRegisterModule($name)` | снять регистрацию |
| `isModuleInstalled($name)` | зарегистрирован ли модуль |
| `getInstalledModules()` | список установленных (на стенде — 92) |
| `getVersion($name)` | версия модуля или `false` |
| `getModificationDateTime($name)` | время изменения файлов модуля |
| `getModulesFromDisk($withLocal, $withPartners, $withKernel)` | что лежит на диске, а не в базе |
| `isValidModule($name)` | имя из допустимых символов (`a-z`, `A-Z`, `0-9`, `_`, `.`) |
| `add($name)`, `delete($name)` | низкоуровневая запись в `b_module` без событий |

## Что происходит при регистрации и снятии

Важно для установщика: ядро делает больше, чем строку в таблице.

| `registerModule()` | `unRegisterModule()` |
|---|---|
| `add()`: строка в `b_module` (повторная регистрация не падает — `DuplicateEntryException` гасится) | `CAgent::RemoveModuleAgents()` — снимает агенты модуля |
| включает агенты модуля (`b_agent`: `ACTIVE = 'Y'`) | `CMain::DelGroupRight()` — снимает групповые права модуля |
| чистит кэш модулей и загруженных обработчиков | `delete()`: удаляет строку из `b_module`, деактивирует агенты |
| событие `main` → **`OnAfterRegisterModule`** | событие `main` → **`OnAfterUnRegisterModule`** |

Оба события получают имя модуля — на них можно повесить свою донастройку портала при установке
другого модуля ([[entity-event-manager]]).

## Регистрация ≠ загрузка

Это две разные вещи, и путаница между ними — частый источник «модуль установлен, но не работает»:

| Что | Чем | Когда |
|---|---|---|
| **Регистрация** | `ModuleManager::registerModule()` | один раз, при установке |
| **Загрузка** | [[entity-loader\|`Loader::includeModule()`]] | на каждом хите, где модуль нужен |

Зарегистрированный модуль **не грузится сам** — его `include.php` выполнится только при
`includeModule`. Чтобы модуль работал на каждом хите, установщик вписывает вызов в `init.php`:
[[recipe-d7-orm-event-subscription]].

## Версия модуля

`getVersion()` читает версию **с диска**, а не из базы: для `main` — константа `SM_VERSION`, для
остальных — `$arModuleVersion['VERSION']` из `install/version.php` модуля. Возвращает `false`, если
модуль не зарегистрирован или имя невалидно (стенд: `main` → `26.750.0`, `crm` → `26.800.0`,
несуществующий → `false`).

Следствие: версия в вики и в `verified` берётся именно отсюда — она показывает, какие файлы лежат на
портале ([[recipe-module-versioning-and-private-distribution]]).

## Подводные камни

- **`CModule::RegisterModule()` — старый API.** Работает, но в новом коде используем `ModuleManager`.
- **`isModuleInstalled()` отвечает «зарегистрирован ли», а не «загружен ли сейчас»** — для второго
  нужен результат `Loader::includeModule()`.
- **Список модулей кэшируется**: `getInstalledModules()` читает `b_module` с кэшем на сутки плюс
  статический кэш на хит. Свои правки таблицы в обход `add()`/`delete()` портал увидит не сразу —
  кэш чистят только штатные методы.
- **Агенты и групповые права ядро снимает само** (`unRegisterModule`), а вот события, опции,
  таблицы модуля и строку подключения в `init.php` убирает уже ваш `DoUninstall`
  ([[recipe-module-structure-and-install]]).
- `add()` и `delete()` работают тихо — **без событий** `OnAfter(Un)RegisterModule`. Если на них
  кто-то подписан, вызывайте `registerModule()` / `unRegisterModule()`.

## Связанное
- [[entity-loader]] — загрузка модуля
- [[recipe-module-structure-and-install]] — где эти вызовы стоят в установщике
- [[entity-main-application]] — соединение с БД, которым ходит сам `ModuleManager`
- [[recipe-module-versioning-and-private-distribution]] — откуда берётся `install/version.php`

[← Ядро D7](_index-core-d7.md)
