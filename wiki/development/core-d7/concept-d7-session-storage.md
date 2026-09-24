---
title: "Сессия D7, LocalSession и временное хранилище"
type: concept
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / коробка в Docker, main 26.750.0: getSession() отдаёт Session\\Session (SessionInterface + ArrayAccess) с методами set/get/has/remove/clear/regenerateId/getId/isStarted; getLocalSession() — Data\\LocalStorage\\SessionLocalStorage; интерфейсы и классы хранилища лежат в Main\\Data\\Storage (PersistentStorageInterface, StorageInterface, ConnectionBasedPersistentStorage, DeferredStorageDecorator); текст — документация фреймворка (docs.1c-bitrix.ru, «Сессии», «Временное хранение»)"
tags: [сессия, хранилище, блокировки, psr-16, ajax]
sources: []
related: ["[[concept-split-session-modes]]", "[[concept-service-locator]]", "[[concept-box-caching]]", "[[recipe-engine-controller-action]]"]
aliases: []
updated: "2026-09-24"
---

# Сессия D7, LocalSession и временное хранилище

**TL;DR:** к сессии обращаемся через объект ядра, а не через `$_SESSION`. Всё, что не обязано жить в
сессии, кладём либо в `LocalSession` (кэш на время сессии, без блокировок), либо во временное
хранилище с гарантированным сроком (`main` 25.1100.0).

## Сессия

```php
$session = \Bitrix\Main\Application::getInstance()->getSession();

$session->set('vendor.step', 3);
$session->get('vendor.step', 0);
$session['vendor.step'];          // ArrayAccess тоже работает
$session->has('vendor.step');
$session->remove('vendor.step');
$session->regenerateId();          // после входа/смены прав
```

На стенде это `Bitrix\Main\Session\Session`, реализует `Session\SessionInterface` и `ArrayAccess`;
есть `clear()`, `getId()`, `isStarted()`.

Настройки — секция `session` в `.settings.php` ([[entity-settings-php]]): `mode`
(`default` / `separated`), `handlers` (`file`, `redis`, `memcache`, `database`), `lifetime`,
`regenerateIdAfterLogin`. Разделённый режим — [[concept-split-session-modes]].

## LocalSession: то, что не должно блокировать

```php
$storage = \Bitrix\Main\Application::getInstance()->getLocalSession('vendor.import');
$storage->set('lastId', $id);
```

Возвращается `Data\LocalStorage\SessionLocalStorage` — кэш, привязанный к `session_id()`. Он **не
берёт блокировку сессии**, поэтому параллельные AJAX-запросы не выстраиваются в очередь. Это
правильное место для промежуточного состояния мастера, фильтров, черновиков —
всего, что можно потерять без последствий.

Неблокирующий и виртуальный режимы самой сессии включаются константами
`BX_SECURITY_SESSION_READONLY` (изменения не сохраняются) и `BX_SECURITY_SESSION_VIRTUAL` (сессия
живёт только в памяти); на стенде обе не определены.

## Временное хранилище (`main` 25.1100.0)

Когда данные должны пережить сессию и хит, но не должны становиться таблицей:

```php
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Data\Storage\PersistentStorageInterface;

$storage = ServiceLocator::getInstance()->get(PersistentStorageInterface::class);

$storage->set('vendor.report.42', $data, 3600);   // TTL: секунды, DateInterval или null
$storage->get('vendor.report.42', null);
$storage->delete('vendor.report.42');
```

- Интерфейсы и реализации — в `Bitrix\Main\Data\Storage`: `StorageInterface` (расширяет PSR-16
  SimpleCache), `PersistentStorageInterface`, `ConnectionBasedPersistentStorage` (хранение в БД),
  `DeferredStorageDecorator` (отложенная запись — для потока записей, где потеря части данных при
  сбое хита допустима).
- Есть `setMultiple()`, `getMultiple()`, `deleteMultiple()`, у декоратора — `save()` и `reset()`.
- TTL дольше **604800 секунд (семь суток)** документация не рекомендует.

## Что выбрать

| Данные | Куда |
|---|---|
| авторизация, состояние пользователя | сессия |
| промежуточное состояние формы, мастера, фильтра | `LocalSession` |
| данные с гарантированным сроком, переживающие сессию | временное хранилище |
| результат тяжёлой выборки | кэш ([[concept-box-caching]]) |
| то, что нужно навсегда | своя таблица ORM |

## Ловушки

- **`$_SESSION` напрямую** обходит режимы и блокировки ядра — в новом коде не используем.
- **Сессия — не кэш.** Всё, что туда положено, читается и пишется на каждом хите и удерживает
  блокировку.
- **Долгое действие контроллера** держит сессию: снимаем фильтром `CloseSession`
  ([[recipe-engine-controller-action]]).
- **Версия.** Временного хранилища нет до 25.1100.0 — на коробке заказчика проверяем.

## Связанные страницы
- [[concept-split-session-modes]] — горячая и холодная части сессии
- [[entity-settings-php]] — секция `session`
- [[concept-service-locator]] — откуда берётся хранилище

[← Ядро D7](_index-core-d7.md)
