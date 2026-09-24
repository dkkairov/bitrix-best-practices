---
title: "Web\\Cookie — чтение и установка cookie"
type: entity
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / коробка в Docker, main 26.750.0: классы Web\\Cookie и Web\\CryptoCookie есть, у Cookie все методы set* включая setSameSite и setSpread; префикс имени — опция main.cookie_name, на стенде BITRIX_SM; текст — документация фреймворка (docs.1c-bitrix.ru, «Cookie-файлы»)"
tags: [cookie, безопасность, samesite, шифрование, запрос]
sources: []
related: ["[[concept-d7-session-storage]]", "[[entity-settings-php]]", "[[concept-request-lifecycle]]", "[[concept-composite-site]]"]
aliases: []
updated: "2026-09-24"
---

# `Web\Cookie` — чтение и установка cookie

**Что это:** объект cookie ядра. Чтение — из запроса, запись — в ответ; ядро само добавляет префикс
имени и следит за параметрами безопасности.

## Чтение

```php
$request = \Bitrix\Main\Context::getCurrent()->getRequest();
$value = $request->getCookie('vendor_last_tab');
```

## Запись

```php
use Bitrix\Main\Web\Cookie;

$cookie = new Cookie('vendor_last_tab', 'orders', time() + 86400);
$cookie->setHttpOnly(true);
$cookie->setSecure(true);
$cookie->setSameSite(Cookie::SAME_SITE_LAX);
$cookie->setPath('/');

\Bitrix\Main\Context::getCurrent()->getResponse()->addCookie($cookie);
```

Методы: `setName()`, `setValue()`, `setDomain()`, `setPath()`, `setExpires()`, `setHttpOnly()`,
`setSecure()`, `setSameSite()`, `setSpread()` — и парные геттеры.

В AJAX-ответе после `addCookie()` вызывают `flush('')`, иначе заголовок не уйдёт вместе с телом.

## Префикс имени

Ядро добавляет к имени префикс из опции `main.cookie_name` — на стенде это `BITRIX_SM`, то есть
`vendor_last_tab` уедет в браузер как `BITRIX_SM_vendor_last_tab`. Отсюда два следствия:

- в JS и в конфигурации веб-сервера (nginx, композит) имя нужно писать **с префиксом**
  ([[concept-composite-site]]);
- `setSpread()` управляет тем, как cookie распространяется по доменам и сайтам — трогаем осознанно.

## Шифрованные cookie

`Bitrix\Main\Web\CryptoCookie` хранит значение в зашифрованном виде; требуется `crypto_key` в
секции `crypto` ([[entity-settings-php]]). На них же держится «горячая» часть разделённой сессии
([[concept-split-session-modes]]).

## Правила

- **Персональные данные и идентификаторы доступа в открытых cookie не храним** — только флаги
  интерфейса. Нужен секрет — `CryptoCookie` или серверное хранилище
  ([[concept-d7-session-storage]]).
- **`HttpOnly` по умолчанию, `Secure` на HTTPS** — иначе cookie читается чужим JS и уходит по
  открытому каналу.
- **`SameSite`** проверяем, если портал работает во фрейме или принимает POST со стороннего домена:
  слишком строгое значение ломает интеграцию, слишком мягкое открывает CSRF.
- **Cookie — не хранилище состояния.** Четыре килобайта, уезжают в каждом запросе, пользователь
  может их вычистить.

## Связанные страницы
- [[concept-d7-session-storage]] — что хранить на сервере
- [[entity-settings-php]] — секции `cookies` и `crypto`
- [[concept-composite-site]] — cookie и композит

[← Ядро D7](_index-core-d7.md)
