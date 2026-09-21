---
title: "\\Bitrix\\Crm\\Service\\Operation и Operation\\Action"
type: entity
module: crm
edition: box
status: verified
provenance: mixed
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Модуль CRM — Универсальное API / Операции, Кастомизация; блок «Эмпирика» — crm 26.800"
tags: [crm, universal-api, операция, действие, класс, d7, расширение]
sources: ["[[source-devbook-crm]]"]
related: ["[[concept-crm-universal-api]]", "[[entity-crm-factory]]", "[[entity-crm-item]]", "[[pattern-crm-action-vs-event]]", "[[recipe-crm-history-all-fields]]", "[[entity-main-result]]", "[[recipe-smart-process-factory-customization]]"]
aliases: ["bitrix24-crm-operation"]
updated: "2026-09-21"
---

# `\Bitrix\Crm\Service\Operation` и `Operation\Action`

**Что это:** операция — конфигурируемый объект, описывающий весь процесс изменения элемента
(15 шагов). `Action` — точка легального вмешательства в этот процесс. Источник —
[Операции](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Operacii.html)
«Книги разработчика».

> **Исправлено 2026-09-21 при сверке с книгой.** `disableAllChecks()` был описан как «массовый
> выключатель» — на деле он выключает **только четыре проверки**; БП, роботы, история и действия
> продолжают работать. Для массовых правок это критично. Эмпирические утверждения (третий аргумент
> `addAction`, `getScope()`, область `automation`) вынесены в отдельный блок; атрибуция исправлена.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | классы операций + абстрактный `Action` |
| Модуль | `crm` |
| Операции | `Operation\Add`, `Update`, `Delete`, `Copy`, `Conversion` |
| Откуда берутся | **только** из фабрики: `$factory->getUpdateOperation($item)` и аналоги |
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
6. Если элемент изменён → **действия до сохранения** (`ACTION_BEFORE_SAVE`)
7. Если не изменён → выход (без истории, БП и автоматизации)
8. Обработка дополнительной логики полей **после** операции
9. Обновление прав
10. Обновление поискового индекса
11. **Сохранение в историю и таймлайн**
12. **Действия после сохранения** (`ACTION_AFTER_SAVE`)
13. Push-сообщение (если есть стадии)
14. Запуск бизнес-процессов
15. Запуск автоматизации

Отдельные шаги могут пропускаться. Порядок важен практически: всё, что нужно запомнить **до**
записи истории, кладут в действие `ACTION_BEFORE_SAVE` — так устроено определение источника
изменения в [[recipe-crm-history-all-fields]].

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

**`disableAllChecks()` выключает только четыре проверки:** запущенные БП (`CheckWorkflows`), права
(`CheckAccess`), обязательные поля (`CheckFields`) и обязательные UF (`CheckRequiredUserFields`)
([конфигурация операций](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Operacii.html#konfiguracia-operacij)).
Бизнес-процессы, роботы, история и действия **продолжают работать**. Для импорта и массовых правок
выключайте их явно, иначе на каждый элемент запустятся БП и роботы:

```php
$op->disableAllChecks()->disableBizProc()->disableAutomation();
```

Все методы возвращают `$this`. `disableCheckAccess()` нужен техническим обработчикам и CLI:
операция идёт от имени пользователя из контекста, у которого может не быть прав на карточку.

## `Operation\Action` — контракт

```php
abstract class Action
{
    abstract public function process(\Bitrix\Crm\Item $item): \Bitrix\Main\Result;
}
```

Действие может **изменить** элемент или **прервать** операцию: возврат `Result` с `addError()`
останавливает сохранение — так выводится человеку понятная ошибка вместо «не сохранилось». В
действии после сохранения книга допускает повторный `$item->save()`
([действия в операции](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Operacii.html#dejstvia-v-operacii)).

Внутри доступны: сам `$item`, снимок `$this->getItemBeforeSave()` (книга связывает его с действием
после сохранения), контекст `$this->getContext()` — пользователь, переданный в операцию (контекст
контейнера — пользователь хита).

Регистрация — в подменённой фабрике ([[recipe-smart-process-factory-customization]]); в книге
`addAction` всегда с двумя аргументами:

```php
public function getUpdateOperation(Item $item, Context $context = null): Operation\Update
{
    $op = parent::getUpdateOperation($item, $context);
    return $op->addAction(Operation::ACTION_BEFORE_SAVE, new MyAction());
}
```

## Эмпирика команды (crm 26.800, в книге нет)

- **Третий аргумент `addAction` — сортировка** (например, `900001`): им регулируют порядок
  относительно штатных действий. Сверить сигнатуру в `crm/lib/service/operation.php` на своей версии:
  если параметра нет, PHP молча проигнорирует лишний аргумент.
- **Контекст брать у действия, а не у контейнера**: при REST-запросе у контейнера остаётся «ручная»
  область (`getScope()`).
- **Область `automation` не отличает робота от бизнес-процесса**: оба идут через
  `Integration\BizProc\Document\Item`, который сам её и ставит.
- `new MyAction()` без аргументов конструктора работает; зависимости передаём через конструктор
  своего действия или сеттеры, **без общего статического состояния** (им болеют события —
  [[pattern-crm-action-vs-event]]).

## Подводные камни

- **`disableAllChecks()` не выключает БП и роботов** — см. выше.
- **Повторный `$item->save()` в действии после сохранения** книга разрешает; запускает ли он заново
  операцию и действия — не проверено, осторожно с рекурсией.
- **Не создавать операцию напрямую** (`new Operation\Update(...)`): книга рассматривает только
  получение из фабрики, а прямое создание обойдёт действия подменённой фабрики (вывод команды).

## Связанное
- [[pattern-crm-action-vs-event]] — когда действие, когда событие
- [[recipe-crm-history-all-fields]] — рабочий пример действия
- [[recipe-smart-process-factory-customization]] — подмена фабрики и три приёма из книги

[← CRM](_index-crm.md)
