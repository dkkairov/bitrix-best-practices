---
title: "Operation\\Action или обработчик события: как вмешиваться в сохранение CRM-сущности"
type: pattern
module: crm
edition: box
status: verified
provenance: documented
verified: "2026-06-02 / документация Universal API CRM (apidocs.bitrix24.ru)"
tags: [crm, universal-api, действия, события, решение, фабрика]
sources: ["[[source-devbook-crm]]"]
related: ["[[concept-crm-universal-api]]", "[[pattern-events-over-core-modification]]", "[[recipe-d7-orm-event-subscription]]", "[[recipe-crm-history-all-fields]]", "[[concept-change-invasiveness-hierarchy]]"]
aliases: ["bitrix24-crm-deystviya-vs-sobytiya"]
updated: "2026-09-18"
---

# `Operation\Action` или обработчик события

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
| Где регистрируется | `EventManager::addEventHandler(...)` | `Operation::addAction(...)` в фабрике |
| Состав данных | `$arFields` — **только изменённые** поля | `$item` — **весь** объект |
| Значение «до» | пара `OnBefore` + `OnAfter` и `static`-переменная | `$this->getItemBeforeSave()` или `$item->remindActual('FIELD')` |
| Запросы в БД | дублируются у независимых обработчиков | один `Item`, кэшируется |
| Тестируемость | плохая (глобальное состояние) | хорошая (обычный класс) |
| Покрытие | все изменения объекта автоматически | только конкретная операция (Add / Update / Delete) |
| Привязка к типу | нет: одно событие на все объекты вида | есть: через фабрику конкретного типа |
| Отмена | `false` / исключение | `Result::addError()` с человеческим текстом |
| Контекст запроса | через глобальные переменные | `getContext()->getUserId()`, `getScope()` |

## Ключевая ловушка событий

`$arFields` в `OnAfter…Update` содержит **только изменённые** поля. Правило вида «если `UF_X` пуст
и `UF_Y` < 100 — отправить письмо» на событии срабатывает ложно: при изменении одного лишь
заголовка `UF_X` в массив не попадёт, `empty(null)` вернёт `true`, письмо уйдёт впустую.

```php
// хрупко
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

Вторая ловушка, названная в документации прямо: если в момент `afterUpdate` нужно изменить
**другую** сделку теми же полями, мы дважды войдём в `before` с разными параметрами и перезапишем
общее `static::$objBeforeSave` — уведомление уйдёт не тому. У действия общего статического
состояния нет.

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

Действие добавляется в операцию **подменённой фабрикой** типа — регистрация в `ServiceLocator`
под именем `crm.service.factory.dynamic.<entityTypeId>`. Подменять фабрику одного типа, а не
контейнер целиком: контейнер конфликтует с приложениями маркетплейса. Практический пример —
[[recipe-crm-history-all-fields]].

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

## Связанное
- [[concept-crm-universal-api]] — Container / Factory / Item / Operation
- [[concept-change-invasiveness-hierarchy]] — почему это уровень 2, а не «мягкое» вмешательство

[← CRM](_index-crm.md)
