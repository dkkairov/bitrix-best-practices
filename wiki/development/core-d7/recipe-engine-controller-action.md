---
title: "Контроллер и действие: Engine\\Controller"
type: recipe
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / коробка в Docker, main 26.750.0: у Engine\\Controller есть configureActions, processBeforeAction, processAfterAction, addError, getAutoWiredParameters, renderView, renderComponent, renderExtension; классы ответов AjaxJson, Json, Component, File, BFile, ResizedImage, Redirect и фильтры-атрибуты Authentication, HttpMethod, Csrf, Scope, ContentType, CloseSession на месте; текст — документация фреймворка (docs.1c-bitrix.ru, «Контроллеры»)"
tags: [контроллер, ajax, действия, фильтры, атрибуты, engine]
sources: []
related: ["[[concept-routing]]", "[[antipattern-ajax-controller-lowercase-name]]", "[[concept-validation-d7]]", "[[entity-main-result]]", "[[concept-service-locator]]"]
aliases: []
updated: "2026-09-24"
---

# Контроллер и действие: `Engine\Controller`

**Результат:** серверное действие, которое вызывается из JS одной строкой и возвращает
предсказуемый JSON с ошибками в понятном формате.

**Когда применять:** любой AJAX в админке, своей странице или компоненте. Свой `ajax.php` с
`require prolog` — вчерашний день: он мимо авторизации, CSRF и обработки ошибок.

## Скелет

```php
namespace Vendor\Project\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Error;

class Rules extends Controller
{
    public function configureActions(): array
    {
        return [
            'getFields' => [
                '-prefilters' => [ActionFilter\Csrf::class],   // снять фильтр по умолчанию
            ],
        ];
    }

    public function getFieldsAction(int $entityTypeId): array
    {
        if ($entityTypeId <= 0)
        {
            $this->addError(new Error('Не передан тип сущности', 'EMPTY_TYPE'));
            return [];
        }

        return ['fields' => [...]];
    }
}
```

Вызов из JS:

```js
BX.ajax.runAction('vendor:project.rules.getFields', { data: { entityTypeId: 1 } })
    .then(r => console.log(r.data), r => console.warn(r.errors));
```

- Имя действия — метод с суффиксом `Action`.
- Результат-массив ядро завернёт в `AjaxJson` со `status` и `errors`; ошибки из `addError()` попадут
  в `errors` ([[entity-main-result]]).

## Фильтры: конфигом или атрибутами

Фильтры по умолчанию — авторизация, метод HTTP, CSRF. Настроить их можно двумя способами:

```php
#[ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST])]
#[ActionFilter\Authentication]
public function saveAction(): array { ... }
```

> **Нельзя настраивать одно действие и через `configureActions()`, и атрибутами** — контроллер
> ответит `Invalid configuration of actions`. Выбираем один способ на действие.

Доступные фильтры на стенде: `Authentication`, `HttpMethod`, `Csrf`, `Scope`, `ContentType`,
`CloseSession`. Последний важен для долгих действий: он снимает блокировку сессии, и параллельные
запросы перестают ждать.

## Жизненный цикл действия

`processBeforeAction($action)` → событие `onBeforeAction` (может отменить выполнение) → метод
действия → событие `onAfterAction` → `processAfterAction($action, $result)`. Общие проверки для всех
действий контроллера кладём в `processBeforeAction()`, а не копируем в каждое действие.

## Что вернуть

| Класс ответа | Когда |
|---|---|
| массив | обычный JSON-ответ (обернётся в `AjaxJson`) |
| `Engine\Response\AjaxJson`, `Json` | нужен контроль над форматом |
| `Engine\Response\Component` | отрендерить компонент и отдать HTML |
| `Engine\Response\File`, `BFile`, `ResizedImage` | отдача файла и превью |
| `Engine\Response\Redirect` | редирект |
| `renderView()`, `renderComponent()`, `renderExtension()` | целая страница; с `main` 25.700.0 ([[concept-routing]]) |

## Параметры действия

Ядро подставляет аргументы по имени из запроса, приводя к типу подсказки. Из коробки
автоподставляются `Engine\CurrentUser`, `Engine\JsonPayload`, `UI\PageNavigation`. Свои классы
добавляются через `getAutoWiredParameters()` (`ExactParameter`, `ValidationParameter`) или
достаются из [[concept-service-locator|ServiceLocator]].

Валидация входа — атрибутами: `ValidationParameter` плюс правила `#[NotEmpty]`, `#[Length]`
([[concept-validation-d7]]).

## Ловушки

- **Регистр имени контроллера.** Действие `rules.getFields` со строчной буквой ломает разрешение
  класса — разбор в [[antipattern-ajax-controller-lowercase-name]].
- **`-prefilters` снимает фильтр, а не добавляет.** Убрали `Csrf` — убедитесь, что действие
  действительно безопасно вызывать без токена.
- **Действие не должно быть длинным.** Долгая работа — в очередь или `Stepper`
  ([[pattern-stepper-long-operations]]), иначе висит сессия и таймаут веб-сервера.
- **Ошибку возвращаем через `addError()`**, а не исключением: клиент получит `errors`, а не
  страницу с трассировкой.

## Связанные страницы
- [[concept-routing]] — маршруты к контроллерам
- [[concept-validation-d7]] — правила валидации
- [[antipattern-ajax-controller-lowercase-name]] — частая ошибка с именем

[← Ядро D7](_index-core-d7.md)
