---
title: "Маршрутизация: свои адреса вместо urlrewrite"
type: concept
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / коробка в Docker, main 26.750.0: классы Routing\\RoutingConfigurator, Routing\\Router, Routing\\Controllers\\PublicPageController, Application::getRouter(), фильтры ActionFilter\\{Authentication,HttpMethod,Csrf}; секция routing в конфигурации не задана, каталога /local/routes нет — маршруты на стенде не объявлены; текст — документация фреймворка (docs.1c-bitrix.ru, «Роутинг»)"
tags: [роутинг, маршруты, url, контроллеры, urlrewrite]
sources: []
related: ["[[recipe-engine-controller-action]]", "[[entity-urlrewrite]]", "[[concept-request-lifecycle]]", "[[pattern-local-solution-structure]]"]
aliases: []
updated: "2026-09-24"
---

# Маршрутизация: свои адреса вместо urlrewrite

**TL;DR:** с `main` 21.400.0 в ядре есть нормальный роутер: адреса описываются в файле маршрутов,
параметры вынимаются из пути, у маршрута есть имя, по которому строится URL. Это замена связки
«`urlrewrite.php` + физический файл с кучей `include`» ([[entity-urlrewrite]]).

**Когда применять:** свой раздел с человекочитаемыми адресами, API-эндпоинты, страницы
модуля. Разовую страницу проще оставить файлом.

## Подключение

```php
// /bitrix/.settings.php (или /local/.settings_extra.php с 24.100.0)
'routing' => [
    'value' => ['config' => ['web.php']],
    'readonly' => true,
],
```

Файл маршрутов кладём в `/local/routes/web.php` — ядро смотрит туда раньше, чем в `/bitrix/routes/`
([[pattern-local-solution-structure]]). На чистом стенде секции `routing` нет и каталога
`/local/routes` тоже — маршрутизация подключается осознанно.

## Объявление

```php
use Bitrix\Main\Routing\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->get('/health', static fn() => 'ok');

    $routes->get('/blog/post/{code}', [BlogController::class, 'view'])
        ->where('code', '[\w\d\-]+')
        ->name('blog.post.view');

    $routes->prefix('blog')->name('blog.')->group(function (RoutingConfigurator $routes) {
        $routes->get('', [BlogController::class, 'index'])->name('index');
    });
};
```

| Что | Как |
|---|---|
| методы | `get()`, `post()`, `any()` |
| обработчик | замыкание, `[Контроллер::class, 'действие']`, класс действия с `RoutableAction`, `new PublicPageController('/path/file.php')` |
| параметр пути | `{code}`, ограничение — `->where('code', 'регулярка')`, значение по умолчанию — `->default('lang', 'en')` |
| группировка | `->prefix('blog')->name('blog.')->group(...)`; слеши и точки ядро добавляет само |
| фильтры | те же, что у контроллеров: `Authentication`, `HttpMethod`, `Csrf` |

**Внимание при исследовании кода:** `method_exists(RoutingConfigurator::class, 'get')` вернёт
`false` — методы обслуживает `__call`. Класс на стенде есть, API рабочий, просто проверять его надо
не так ([[concept-platform-reverse-engineering]]).

## Ссылка по имени маршрута

```php
$url = \Bitrix\Main\Application::getInstance()->getRouter()->route('blog.post.view', [
    'code' => 'article-slug',
    'utm_source' => 'ads',   // лишние параметры уйдут в query
]);
```

Именованные маршруты — главный практический выигрыш: адрес меняется в одном месте, шаблоны и письма
не переписываются.

## Рендеринг из контроллера

С `main` 25.700.0 контроллер умеет отдавать страницу, а не только JSON
([[recipe-engine-controller-action]]):

- `renderView()` — подключает представление модуля через `$APPLICATION->IncludeFile()`;
- `renderComponent()` — сразу компонент;
- `renderExtension()` — страницу с JS-расширением.

## Переход со старого

`PublicPageController('/path/file.php')` позволяет завести маршрут на существующий файл — адреса
уже новые, код пока старый. Дальше файл превращается в контроллер с `renderView()`.

## Ловушки

- **Версия.** Роутинг — с 21.400.0, рендеринг из контроллера — с 25.700.0. На старой коробке
  клиента этого может не быть; смотрим версию модуля перед проектированием.
- **Два механизма адресов одновременно.** `urlrewrite.php` продолжает работать; держать один и тот
  же раздел в обоих местах — гарантированная путаница.
- **Файл маршрутов — это код, который выполняется на каждом хите.** Никаких запросов к базе и
  тяжёлых вычислений при объявлении маршрутов.
- **`readonly => true`** в секции означает, что ядро не перезапишет её из админки — так и оставляем.

## Связанные страницы
- [[recipe-engine-controller-action]] — контроллеры, к которым ведут маршруты
- [[entity-urlrewrite]] — старый механизм
- [[concept-request-lifecycle]] — куда роутинг встраивается на хите

[← Ядро D7](_index-core-d7.md)
