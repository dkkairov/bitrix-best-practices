---
title: "События ORM DataManager: девять хуков и формат имени, на котором все спотыкаются"
type: concept
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-06-03 / коробка: исходники main и bizproc, проверено живой подпиской; 2026-09-23 — курс 43 и код main/lib/ORM/EventManager.php: метод-хук в сущности и ORM\\EventManager"
tags: [orm, d7, datamanager, события, eventmanager, opcache]
sources: ["[[source-course43-orm-events]]"]
related: ["[[recipe-d7-orm-crud]]", "[[concept-d7-orm-entity]]", "[[recipe-d7-orm-event-subscription]]", "[[pattern-events-over-core-modification]]", "[[concept-bitrix-naming-conventions]]", "[[pattern-crm-action-vs-event]]", "[[entity-event-manager]]"]
aliases: ["bitrix24-orm-datamanager"]
updated: "2026-09-23"
---

# События ORM DataManager

**TL;DR:** любой наследник `DataManager` автоматически получает девять событий жизненного цикла.
Ошибка в формате имени не даёт ни ошибки, ни предупреждения — обработчик просто молчит.

> **Источник уточнён 2026-09-21.** Страница ссылалась на конспект «Книги разработчика», но при сверке
> с сайтом книги выяснилось: событий ORM `DataManager` в книге нет вообще. Всё ниже — из исходников
> `main`/`bizproc` и живой проверки (см. `verified`); книге отсюда соответствует только общее правило
> «обработчик нового ядра получает один `Event`»
> ([События](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Sobytia.html#kak-podpisat-sa-na-sobytia)).

> **Дополнено 2026-09-23 по курсу 43** ([Операции с сущностями](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=2244)):
> у подписки есть два способа проще того, что описан ниже, — метод-хук в самой сущности и
> `ORM\EventManager`. Строка с именем события и её ловушки нужны только тогда, когда подписываетесь
> на **чужую** сущность «снаружи» и хотите обойтись без её класса.

## Девять событий

| Событие | Момент |
|---|---|
| `OnBeforeAdd` / `OnAdd` / `OnAfterAdd` | до INSERT / в транзакции / после успешного INSERT |
| `OnBeforeUpdate` / `OnUpdate` / `OnAfterUpdate` | до UPDATE / в транзакции / после успешного UPDATE |
| `OnBeforeDelete` / `OnDelete` / `OnAfterDelete` | до DELETE / в транзакции / после успешного DELETE |

## Три способа подписаться

| Способ | Когда | Цена ошибки |
|---|---|---|
| **Метод в самой сущности** — `public static function onBeforeAdd(ORM\Event $event)` | своя сущность | ядро находит метод само, ошибиться в имени события нельзя |
| **`ORM\EventManager`** — `addEventHandler(BookTable::class, DataManager::EVENT_ON_BEFORE_ADD, $callback)` | чужая сущность, класс которой доступен | имя события собирает ядро; неизвестный тип события даёт `ArgumentException` |
| **`Main\EventManager`** со строкой `'\Ns\Class::OnAfterUpdate'` | класс сущности недоступен или подписка регистрируется в `.settings.php`/установщике | опечатка = молчаливый отказ (см. ниже) |

```php
// способ 2: имя события и модуль ядро вычислит само
\Bitrix\Main\ORM\EventManager::getInstance()->addEventHandler(
    BookTable::class,                                    // Entity, Table-класс или класс объекта
    \Bitrix\Main\ORM\Data\DataManager::EVENT_ON_BEFORE_ADD,
    [\Vendor\Module\Handler::class, 'onBeforeAdd']
);
```

Внутри `ORM\EventManager` делает ровно то, что ниже описано руками: строит имя
`<namespace><Имя сущности>::<Событие>` и передаёт его обычному `Main\EventManager` вместе с модулем
сущности (код `main/lib/ORM/EventManager.php`, 26.750.0). То есть это не другой механизм, а удобная
обёртка — и она снимает обе ловушки: суффикс `Table` и регистр namespace.

## Что может обработчик

Возвращённый `ORM\EventResult` (он же `Entity\EventResult`) умеет три вещи:

| Метод | Действие |
|---|---|
| `modifyFields([...])` | подменить значения перед записью — нормализация, значения по умолчанию |
| `unsetFields([...])` | «тихо» выбросить поле из набора, будто его не передавали |
| `addError(new FieldError($field, '…'))` / `addError(new EntityError('…'))` | прервать операцию с ошибкой поля или записи целиком |

Пример нормализации и запрета правки поля — в [[recipe-d7-orm-crud]].

## Формат подписки

```php
\Bitrix\Main\EventManager::getInstance()->addEventHandler(
    'bizproc',                                        // короткое имя модуля
    '\Bitrix\BizProc\Workflow\Task\TaskUser::OnAfterUpdate',  // БЕЗ суффикса Table
    [\Vendor\Module\Handler::class, 'onAfterUpdate']
);
```

Два правила, которые дают 90 % молчаливых отказов:

1. **Имя класса без суффикса `Table`:** `TaskUserTable` → `TaskUser`, `DiscountTable` → `Discount`.
2. **Регистр namespace значим.** Имя события — строка, сравнение точное. Проверять по пути к файлу:
   `modules/bizproc/lib/workflow/task/taskusertable.php` → `\Bitrix\BizProc\Workflow\Task\TaskUser`
   (заглавная `P` в `BizProc`). См. [[concept-bitrix-naming-conventions]].

## Почему важен namespace в имени

Внутри `callOnAfterUpdateEvent` ядро шлёт событие **дважды**:

1. `new Event($object->entity, 'OnAfterUpdate', [...])` — без namespace;
2. `new Event($object->entity, 'OnAfterUpdate', [...], true)` — с namespace (фильтрованная).

Подписка вида `'\Ns\Class::EventName'` ловит **вторую**. Подписка просто `'OnAfterUpdate'` ловит
первую — но срабатывает для **всех** таблиц модуля, поэтому без дополнительной фильтрации её не
используют.

## Что получает обработчик

```php
public static function handler(\Bitrix\Main\Event $event): void
{
    $id     = $event->getParameter('id');      // ['ID' => int] — первичный ключ
    $fields = $event->getParameter('fields');  // только изменённые поля
    $object = $event->getParameter('object');  // EO_* — объект после операции
}
```

**Способ регистрации определяет сигнатуру обработчика:**

| Способ | Что приходит |
|---|---|
| `addEventHandler` | `(Event $event)` — один объект, читать через `getParameters()` |
| `RegisterModuleDependences` | `($id, $fields, $object, $primary)` — отдельные аргументы по порядку `array_values(getParameters())` |

Для D7-ORM-событий из модуля работает только первый способ — почему, разобрано в
[[recipe-d7-orm-event-subscription]].

## Подводные камни

- **Тишина при неверном имени.** Ни ошибки, ни лога — обработчик просто не вызывается. При отладке
  первым делом сверять точный namespace по пути к файлу.
- **`fields` содержит только изменённые поля.** Проверка «поле пустое» на отсутствующем ключе даёт
  ложное срабатывание. Для CRM-сущностей это главный довод в пользу
  [[pattern-crm-action-vs-event|действий операций]].
- **OPcache.** После замены PHP-файла модуля старый байткод остаётся в кэше: поведение не меняется,
  хотя код новый. Лечится перезапуском php-fpm — учитывать в процедуре заливки
  ([[recipe-safe-module-deploy]]).

## Открытые вопросы
- Что именно ловит подписка без namespace и в каких случаях она оправдана.

## Связанные страницы
- [[recipe-d7-orm-event-subscription]] — как подписаться из своего модуля так, чтобы работало
- [[recipe-d7-orm-crud]] — что делает обработчик с данными
- [[concept-d7-orm-entity]] — сущность, к которой относятся эти события

[← Ядро D7](_index-core-d7.md)
