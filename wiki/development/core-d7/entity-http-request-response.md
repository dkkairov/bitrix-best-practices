---
title: "HttpRequest, HttpResponse и Context"
type: entity
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / коробка в Docker, main 26.750.0: Context::getCurrent()->getRequest() — Main\\HttpRequest (наследник Main\\Request), getResponse() — Main\\HttpResponse, getServer() — Main\\Server; все перечисленные методы есть, кроме isGet(), который документация называет, а в ядре его нет; текст — документация фреймворка (docs.1c-bitrix.ru, «Request и Response»)"
tags: [запрос, ответ, context, http, суперглобальные]
sources: []
related: ["[[concept-request-lifecycle]]", "[[entity-main-application]]", "[[entity-web-cookie]]", "[[recipe-engine-controller-action]]", "[[concept-coding-standards]]"]
aliases: []
updated: "2026-09-24"
---

# `HttpRequest`, `HttpResponse` и `Context`

**Что это:** объектный доступ к запросу и ответу. Вместо `$_GET`, `$_POST`, `$_FILES`, `header()` —
объекты, которые знают про кодировку, админку и AJAX.

```php
$context  = \Bitrix\Main\Context::getCurrent();          // или Application::getInstance()->getContext()
$request  = $context->getRequest();                      // Bitrix\Main\HttpRequest
$response = $context->getResponse();                     // Bitrix\Main\HttpResponse
$server   = $context->getServer();                       // Bitrix\Main\Server
```

`Context` отдаёт ещё `getCulture()`, `getLanguage()`, `getSite()`, `getEnvironment()` — региональные
настройки и текущий сайт ([[concept-multisite]]).

## Запрос

| Группа | Методы |
|---|---|
| значения | `get($name)` (GET или POST), `getQuery()`, `getQueryList()`, `getPost()`, `getPostList()` |
| файлы и cookie | `getFile()`, `getFileList()`, `getCookie()`, `getCookieList()` ([[entity-web-cookie]]) |
| про сам запрос | `getRequestMethod()`, `isPost()`, `isAjaxRequest()`, `isHttps()`, `isAdminSection()`, `getRequestUri()`, `getRemoteAddress()`, `getHeaders()` |

`HttpRequest` наследует `Main\Request`, тот — `Type\ParameterDictionary`, поэтому доступ к
значениям единообразный.

> **Расхождение с документацией (2026-09-24).** Документация называет метод `isGet()`. В ядре
> 26.750.0 его нет — проверка идёт через `getRequestMethod() === 'GET'`. `isPost()`,
> `isAjaxRequest()`, `isHttps()` на месте.

## Ответ

`addHeader($name, $value)`, `setHeaders(Web\HttpHeaders)`, `getHeaders()`, `addCookie(Web\Cookie)`,
`setContent()`, `getContent()`, `setStatus()`, `writeHeaders()`, `flush()`.

Готовые виды ответа для контроллеров — `AjaxJson`, `Json`, `Component`, `Redirect`
([[recipe-engine-controller-action]]).

## Правила

- **Суперглобальные массивы в новом коде не используем.** `$_REQUEST` вообще не знает, откуда
  значение; объект запроса различает источники, а это половина защиты от подделки параметров
  ([[concept-coding-standards]]).
- **Значение из запроса — не доверенные данные.** Приводим тип и проверяем: `(int)$request->get('id')`.
- **`getRemoteAddress()` за прокси** отдаёт адрес прокси; реальный адрес зависит от настроек
  веб-сервера и заголовков — не строим на нём безопасность без проверки инфраструктуры.
- **Заголовки после вывода не уйдут** — `addHeader()` вызываем до того, как что-то напечатано
  ([[concept-request-lifecycle]]).

## Связанные страницы
- [[concept-request-lifecycle]] — где в хите появляются эти объекты
- [[entity-main-application]] — `Application` и контекст
- [[recipe-engine-controller-action]] — ответы контроллера

[← Ядро D7](_index-core-d7.md)
