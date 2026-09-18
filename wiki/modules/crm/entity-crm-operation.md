---
title: "\\Bitrix\\Crm\\Service\\Operation и Operation\\Action"
type: entity
module: crm
edition: box
status: verified
provenance: documented
verified: "2026-06-02 / документация Universal API CRM (apidocs.bitrix24.ru)"
tags: [crm, universal-api, операция, действие, класс, d7, расширение]
sources: []
related: ["[[concept-crm-universal-api]]", "[[entity-crm-factory]]", "[[entity-crm-item]]", "[[pattern-crm-action-vs-event]]", "[[recipe-crm-history-all-fields]]", "[[entity-main-result]]"]
aliases: ["bitrix24-crm-operation"]
updated: "2026-09-18"
---

# `\Bitrix\Crm\Service\Operation` и `Operation\Action`

**Что это:** операция — конфигурируемый объект, описывающий весь процесс изменения элемента
(порядка 15 шагов). `Action` — точка легального вмешательства в этот процесс.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | классы операций + абстрактный `Action` |
| Модуль | `crm` |
| Операции | `Operation\Add`, `Update`, `Delete`, `Copy`, `Conversion` |
| Откуда берутся | `$factory->getUpdateOperation($item)` и аналоги |
| Результат | `\Bitrix\Main\Result` ([[entity-main-result]]) |

```php
$result = $factory->getUpdateOperation($item)->launch();
$result->isSuccess();
$result->getErrorMessages();
```

## Пятнадцать шагов `launch()`

1. Проверка тарифных ограничений
2. Проверка прав доступа к элементу
3. Проверка запущенных бизнес-процессов (для удаления)
4. Обработка дополнительной логики полей **перед** операцией
5. Проверка обязательных полей
6. Если элемент изменён → **действия до сохранения**
7. Если не изменён → выход
8. Обработка дополнительной логики полей **после** операции
9. Обновление прав
10. Обновление поискового индекса
11. **Сохранение в историю и таймлайн**
12. **Действия после сохранения**
13. Push-сообщение (если есть стадии)
14. Запуск бизнес-процессов
15. Запуск автоматизации

Порядок важен практически: всё, что нужно запомнить **до** записи истории, кладут в действие
`ACTION_BEFORE_SAVE` — так устроено определение источника изменения в
[[recipe-crm-history-all-fields]].

## Конфигурация: десять пар выключателей

| Что отключаем | Методы |
|---|---|
| Проверку прав | `enableCheckAccess` / `disableCheckAccess` / `isCheckAccessEnabled` |
| Проверку запущенных БП | `enableCheckWorkflows` / `disableCheckWorkflows` / … |
| Логику полей | `enableFieldProcession` / `disableFieldProcession` |
| Проверку обязательных полей | `enableCheckFields` / `disableCheckFields` |
| Проверку обязательных UF | `enableCheckRequiredUserFields` / `disable…` |
| Действия до сохранения | `enableBeforeSaveActions` / `disable…` |
| Историю и таймлайн | `enableSaveToHistory` / `disableSaveToHistory` |
| Действия после сохранения | `enableAfterSaveActions` / `disable…` |
| Бизнес-процессы | `enableBizProc` / `disableBizProc` |
| Автоматизацию | `enableAutomation` / `disableAutomation` |

Плюс `disableAllChecks()` — массовый выключатель. Все возвращают `$this`:

```php
$op->disableCheckWorkflows()->disableBizProc()->disableAutomation();
```

`disableCheckAccess()` нужен техническим обработчикам: операция идёт от имени текущего
пользователя, у которого может не быть прав на карточку.

## `Operation\Action` — контракт

```php
abstract class Action
{
    abstract public function process(\Bitrix\Crm\Item $item): \Bitrix\Main\Result;
}
```

Внутри доступны: сам `$item`, снимок `$this->getItemBeforeSave()`, контекст
`$this->getContext()` (`getUserId()`, `getScope()`). Возврат `Result` с `addError()` **прерывает
операцию** — именно так выводится человеку понятная ошибка вместо «не сохранилось».

Две константы стадий: `Operation::ACTION_BEFORE_SAVE` (перед шагом 6) и
`Operation::ACTION_AFTER_SAVE` (перед шагом 12).

```php
public function getUpdateOperation(Item $item, Context $context = null): Operation\Update
{
    $op = parent::getUpdateOperation($item, $context);
    return $op->addAction(Operation::ACTION_BEFORE_SAVE, new MyAction(), 900001);
}
```

Третий аргумент `addAction` — сортировка: ей регулируют порядок относительно штатных действий.

## Подводные камни

- **Действие после сохранения может сохранить элемент повторно** (`$item->save()`) — удобно, но
  легко получить рекурсию: проверяйте, что изменение действительно нужно.
- **`Action::__construct()` без аргументов** — `new MyAction()` законен; зависимости передавайте
  через сеттеры или статику, а не через конструктор.
- **Контекст брать у действия, а не у контейнера**: у контейнера при REST-запросе остаётся
  «ручная» область.
- **Область `automation` не отличает робота от бизнес-процесса**: оба идут через
  `Integration\BizProc\Document\Item`, который сам её и ставит.

## Связанное
- [[pattern-crm-action-vs-event]] — когда действие, когда событие
- [[recipe-crm-history-all-fields]] — рабочий пример действия

[← CRM](_index-crm.md)
