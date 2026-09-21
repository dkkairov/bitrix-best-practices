---
title: "Operation\\Action или обработчик события: как вмешиваться в сохранение CRM-сущности"
type: pattern
module: crm
edition: box
status: verified
provenance: mixed
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Модуль CRM — Кастомизация (как работает, добавление действий, подмена фабрики), события сделки; getScope — эмпирика, crm 26.800"
tags: [crm, universal-api, действия, события, решение, фабрика]
sources: ["[[source-devbook-crm]]"]
related: ["[[concept-crm-universal-api]]", "[[pattern-events-over-core-modification]]", "[[recipe-d7-orm-event-subscription]]", "[[recipe-crm-history-all-fields]]", "[[concept-change-invasiveness-hierarchy]]", "[[entity-crm-legacy-events]]", "[[recipe-smart-process-factory-customization]]"]
aliases: ["bitrix24-crm-deystviya-vs-sobytiya"]
updated: "2026-09-21"
---

# `Operation\Action` или обработчик события

> **Сверено с «Книгой разработчика» 2026-09-21.** Атрибуция исправлена (материал — из
> [Кастомизация → Как работает](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Kastomizacia/Kak_rabotaet.html),
> а не из apidocs). Уточнены: подписка на события CRM — `addEventHandlerCompatible`; механизм отмены
> в событиях; обход событий через `ENABLE_SYSTEM_EVENTS`; различие контекстов; «приложения» →
> «модули» Маркетплейса.

## Проблема и контекст

Задачу «вмешаться в процесс сохранения CRM-сущности» в коробке можно решить двумя путями:
классическим обработчиком события (`OnAfterCrmDealUpdate` и аналоги) или действием операции
Universal API (`Operation\Action`). Пути **не эквивалентны**, и выбор «по привычке» регулярно даёт
плавающие дефекты.

## Решение

Для сущностей, работающих через Universal API, по умолчанию выбирать **`Operation\Action`**.
Обработчик события оставлять для legacy и сквозных сценариев.

## Сравнение

| Аспект | Обработчик события | `Operation\Action` |
|---|---|---|
| Где регистрируется | `EventManager::addEventHandlerCompatible(...)` — события CRM из старого ядра ([[entity-crm-legacy-events]]) | `Operation::addAction(...)` в фабрике |
| Состав данных | `$arFields` — только изменённые или «затронутые» поля (при смене названия — `TITLE`, `~DATE_MODIFY`, `MODIFY_BY_ID`) | `$item` — **весь** объект |
| Значение «до» | пара `OnBefore` + `OnAfter` и `static`-переменная | после сохранения — `$this->getItemBeforeSave()`; до сохранения — `$item->remindActual('FIELD')` / `isChanged*()` |
| Запросы в БД | дублируются у независимых обработчиков | один `Item` на операцию |
| Тестируемость | плохая (глобальное состояние) | хорошая (обычный класс) |
| Покрытие | вызовы `CCrm*::Add/Update/Delete` (в том числе из интерфейса); `Update` лида и сделки с `ENABLE_SYSTEM_EVENTS => false` события не вызывает | только конкретная операция (Add / Update / Delete) |
| Привязка к типу | нет: одно событие на все объекты вида | есть: через фабрику конкретного типа |
| Отмена | только в `OnBefore…`: `return false` + текст в `$arFields['RESULT_MESSAGE']` (Add/Update) или `$APPLICATION->ThrowException()` (Delete); возврат `OnAfter…` игнорируется | `Result::addError()` с человеческим текстом |
| Контекст запроса | через глобальные переменные | `$this->getContext()` — пользователь операции (контейнерный — пользователь хита); `getScope()` — эмпирика |

## Ключевая ловушка событий

`$arFields` в `OnAfter…Update` содержит только изменённые или затронутые поля. Правило вида «если
`UF_X` пуст и `UF_Y` < 100 — отправить письмо» на событии срабатывает ложно: при изменении одного
лишь заголовка `UF_X` в массив не попадёт, `empty(null)` вернёт `true`, письмо уйдёт впустую. Книга
показывает наивный вариант и громоздкую «заплатку» с проверкой ключа — ниже она, и она всё равно не
знает значения «до».

```php
// хрупко: наличие ключа проверено, но значения «до» нет
$em->addEventHandlerCompatible('crm', 'OnAfterCrmDealUpdate', function (&$ar) {
    if (array_key_exists('UF_X', $ar) && empty($ar['UF_X'])) { /* ... */ }
});
```

```php
// надёжно
class NotifyResponsible extends \Bitrix\Crm\Service\Operation\Action
{
    public function process(\Bitrix\Crm\Item $item): \Bitrix\Main\Result
    {
        $result = new \Bitrix\Main\Result();
        if ($this->getItemBeforeSave()->isChanged('UF_X')
            && empty($item->get('UF_X'))
            && $item->get('UF_Y') < 100) {
            // отправка
        }
        return $result;
    }
}
// + $operation->addAction(Operation::ACTION_AFTER_SAVE, new NotifyResponsible());
```

Вторая ловушка, названная в книге прямо (и названная «очень редкой»): если внутри `afterUpdate`
изменить **другую** сделку, вложенный вызов снова войдёт в `before` и перезапишет общее
`static::$objBeforeSave` — уведомление уйдёт не тому. У действия общего статического состояния нет.
Третий недостаток событий по книге — независимые обработчики повторяют одни и те же запросы.

## Когда применять `Operation\Action`
- Сущность работает через Universal API (смарт-процессы — всегда).
- Логика привязана к одному типу сущности.
- Нужен весь объект или значение «до».
- Нужны тесты или предсказуемая нагрузка (без дублирующих запросов).
- Нужно **запретить** сохранение с внятным сообщением пользователю.

## Когда оставить обработчик события
- Сущность работает через старый API (UA выключен).
- Одна логика на много типов сущностей (например, журналирование любого удаления).
- Это не CRM: в других модулях системы действий нет — там
  [[recipe-d7-orm-event-subscription|подписка на событие D7 ORM]].

## Как реализовать

Действие добавляется в операцию **подменённой фабрикой** типа. Для смарт-процесса — регистрация в
`ServiceLocator` под именем `crm.service.factory.dynamic.<entityTypeId>` лениво и **до первого
`getFactory()`** в хите (книга: [Подмена фабрики](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Kastomizacia/Podmena_fabriki.html)).
Практика команды — подменять фабрику одного типа, а не контейнер: книга допускает и подмену
контейнера, но некоторые **модули** Маркетплейса делают то же. Рецепт —
[[recipe-smart-process-factory-customization]], рабочий пример — [[recipe-crm-history-all-fields]].

Порядок шагов `Operation::launch()`: действия «перед сохранением» → запись элемента → запись
истории → действия «после сохранения». Это важно, если нужно что-то запомнить до истории.

## Последствия
- **Плюсы:** предсказуемость, тестируемость, внятные ошибки пользователю, отсутствие
  «фантомных» срабатываний.
- **Минусы:** нужна подмена фабрики (лишний слой и риск при мажорных обновлениях — страхуемся
  [[pattern-module-self-disabling-guard|сторожем]]); действие видит только свою операцию.

## Открытые вопросы
- Где разместить **общее** действие для нескольких типов сущностей.
- Есть ли события CRM без аналога в системе действий.
- Срабатывают ли старые события (`OnAfterCrmDealUpdate`) при сохранении через операции, когда UA
  для сделки включён: книга обещает, что старый код продолжит работать, — проверять на стенде.

## Связанное
- [[concept-crm-universal-api]] — Container / Factory / Item / Operation
- [[concept-change-invasiveness-hierarchy]] — почему это уровень 2, а не «мягкое» вмешательство

[← CRM](_index-crm.md)
