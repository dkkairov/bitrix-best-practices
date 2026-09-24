---
title: "HTTP-запрос из коробки: HttpClient"
type: recipe
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / коробка в Docker, main 26.750.0: состав методов Web\\HttpClient (включая sendRequest, sendAsyncRequest, wait, setLogger), реализация Psr\\Http\\Client\\ClientInterface, значения по умолчанию из исходника (socketTimeout 30, streamTimeout 60, redirectMax 5, privateIp true); текст — документация фреймворка (docs.1c-bitrix.ru, «HTTP-клиент»)"
tags: [http, интеграции, rest, httpclient, psr-18, ssrf]
sources: []
related: ["[[concept-d7-logging]]", "[[pattern-rest-webhooks-and-events]]", "[[recipe-composer-third-party-libraries]]", "[[concept-coding-standards]]"]
aliases: []
updated: "2026-09-24"
---

# HTTP-запрос из коробки: HttpClient

**Результат:** обращение к внешнему API без curl-обвязки руками и без сторонних библиотек.

**Когда применять:** любая интеграция из коробки — вызов чужого REST, отправка вебхука, скачивание
файла. Ставить Guzzle ради этого не нужно ([[recipe-composer-third-party-libraries]] — только когда
без пакета не обойтись).

## Простой случай

```php
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;

$http = new HttpClient([
    'socketTimeout' => 5,      // подключение, с
    'streamTimeout' => 10,     // чтение ответа, с
    'redirect' => false,       // не ходить по редиректам
]);
$http->setHeader('Content-Type', 'application/json');

$body = $http->post('https://api.example.com/v1/orders', Json::encode($payload));

if ($http->getStatus() !== 200)
{
    // getError() — массив [код => сообщение]
    throw new \Bitrix\Main\SystemException('HTTP ' . $http->getStatus());
}

$data = Json::decode($body);
```

| Метод | Для чего |
|---|---|
| `get($url)`, `post($url, $data)` | самые частые |
| `query($method, $url, $data)` | произвольный метод: `PUT`, `PATCH`, `DELETE` |
| `download($url, $path)` | сразу в файл, без буфера в памяти |
| `setHeader()`, `setHeaders()`, `setCookies()` | заголовки и cookie |
| `getStatus()`, `getHeaders()`, `getError()`, `getCookies()` | разбор ответа |
| `sendRequest(RequestInterface)` | режим PSR-18: клиент реализует `Psr\Http\Client\ClientInterface` |
| `sendAsyncRequest()` + `wait()` | несколько запросов параллельно |
| `setLogger()` | писать запросы в лог ([[concept-d7-logging]]) |

Файл на другую сторону — `Bitrix\Main\Web\Http\MultipartStream`, тело собирается как `multipart/form-data`.

## Значения по умолчанию (26.750.0, из исходника)

| Параметр | По умолчанию |
|---|---|
| `socketTimeout` | 30 с |
| `streamTimeout` | 60 с (1 с, если `waitResponse = false`) |
| `redirectMax` | 5 |
| `waitResponse` | `true` — читать тело; `false` отключается после заголовков |
| `bodyLengthMax` | 0 — без ограничения |
| `privateIp` | `true` — **запросы на приватные адреса разрешены** |
| версия HTTP | 1.1 |

Прочие опции конструктора: `compress`, `charset`, `useCurl`, `curlLogFile`, `proxyHost`,
`proxyPort`, `proxyUser`, `proxyPassword`, `disableSslVerification`, `debugLevel`, `headers`,
`cookies`.

## Подводные камни

- **Таймауты по умолчанию убивают хит.** 30 + 60 секунд на медленном API — это белая страница у
  пользователя. В веб-запросе ставим 3–10 секунд, долгие вызовы уносим в агент или очередь
  ([[pattern-agents-vs-cron]]).
- **`privateIp` по умолчанию `true`.** Если адрес приходит от пользователя (веб-хук, импорт по
  ссылке), это SSRF: запрос уйдёт на `127.0.0.1` или в локальную сеть. Для таких сценариев —
  `'privateIp' => false`.
- **`disableSslVerification` — не «починить ошибку сертификата».** Выключенная проверка означает,
  что ответ может подменить кто угодно. Правильный путь — обновить CA-бандл.
- **Клиент не бросает исключений.** Ошибку видно только по `getStatus()` и `getError()`; без
  проверки код пойдёт дальше с пустым телом.
- **Ответ целиком в памяти.** Большой файл — `download()`, а не `get()`.
- **Событие `OnHttpClientBuildRequest`** позволяет чужому коду вмешаться в запрос — помним при
  отладке «странных» заголовков.

## Что логировать

`setLogger()` принимает PSR-3-логгер; фабрика ядра знает идентификатор `main.HttpClient` — настроив
его в `.settings.php`, получаем лог всех запросов клиента без правки кода
([[concept-d7-logging]]). В лог не кладём токены и тела с персональными данными.

## Связанные страницы
- [[concept-d7-logging]] — куда писать лог
- [[pattern-rest-webhooks-and-events]] — обратная сторона: нам шлют запрос
- [[concept-coding-standards]] — безопасность и секреты

[← Ядро D7](_index-core-d7.md)
