---
title: "Запись через ORM: add, update, delete и проверки данных"
type: recipe
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-23 / курс 43 (урок 2244) + коробка в Docker, main 26.750.0: классы валидаторов в обоих namespace, объекты результата, SqlExpression и плейсхолдеры — по коду ядра"
tags: [orm, d7, datamanager, запись, валидаторы, события, sqlexpression]
sources: ["[[source-course43-orm-events]]"]
related: ["[[concept-d7-orm-entity]]", "[[concept-d7-orm-query]]", "[[concept-orm-datamanager-events]]", "[[entity-main-result]]", "[[concept-validation-d7]]"]
aliases: []
updated: "2026-09-23"
---

# Запись через ORM: `add`, `update`, `delete` и проверки данных

**Результат:** данные пишутся через сущность, а не прямым SQL: срабатывают валидаторы, значения по
умолчанию, события и приведение типов. Основано на уроке курса 43
[Операции с сущностями](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=2244).

## Когда применять

- Пишем в свою ORM-таблицу ([[concept-d7-orm-entity]]) или в таблицу ядра, у которой есть
  Table-класс и нет более высокоуровневого API.
- **Не применять к сущностям с собственным API:** CRM пишется через фабрики и операции
  ([[concept-crm-universal-api]]), задачи — командами V2 ([[recipe-tasks-v2-commands]]). Там ORM —
  только чтение, иначе обойдёте права, события и автоматизацию.

## Три метода

```php
use Bitrix\Main\Type;

$result = BookTable::add([
    'ISBN'         => '978-0321127426',
    'TITLE'        => 'Patterns of Enterprise Application Architecture',
    'PUBLISH_DATE' => new Type\Date('2002-11-16', 'Y-m-d'),
]);
if ($result->isSuccess()) {
    $id = $result->getId();
}

$result = BookTable::update($id, ['TITLE' => 'Новое название']);
$changed = $result->getAffectedRowsCount();     // фактически ли обновилась строка

$result = BookTable::delete($id);
// составной ключ: BookTable::delete(['BOOK_ID' => 2, 'AUTHOR_ID' => 18]);
```

| Метод | Результат | Что забрать |
|---|---|---|
| `add($fields)` | `AddResult` | `getId()` |
| `update($primary, $fields)` | `UpdateResult` | `getAffectedRowsCount()` |
| `delete($primary)` | `DeleteResult` | `isSuccess()` |

Все три — наследники [[entity-main-result\|`Main\Result`]]: `isSuccess()`, `getErrors()`,
`getErrorMessages()`.

- **Даты — только объектами** `Main\Type\Date` и `Main\Type\DateTime`, в том числе для
  пользовательских полей с датой.
- **Ключ `FIELDS` пишем заглавными.** Имена `fields` и `auth_context` в нижнем регистре
  зарезервированы ядром.

## Проверка результата обязательна

```php
$result = BookTable::update($id, $fields);
if (!$result->isSuccess()) {
    // разбор ошибок, лог, сообщение пользователю
}
```

Если результат не проверить, а валидация не прошла, ядро поднимет `E_USER_WARNING` со списком
ошибок — он уйдёт в лог сайта, но код продолжит работу как ни в чём не бывало. В агентах и очередях
это выглядит как «данные молча не записались».

## Валидаторы поля

```php
new Fields\StringField('ISBN', [
    'required'   => true,
    'validation' => static fn () => [
        new Fields\Validators\RegExpValidator('/\d{13}/'),
        static function ($value, $primary, $row, $field) {
            // своя проверка: true, текст ошибки или FieldError со своим кодом
            return new \Bitrix\Main\ORM\Fields\FieldError($field, 'Контрольная цифра не сошлась', 'MY_ISBN_CHECKSUM');
        },
    ],
]),
```

- **Валидаторы задаются только через параметр конструктора или `addValidator()`** — беглого
  `configureValidation()` у поля нет (стенд, 26.750.0), в отличие от остальных настроек
  ([[concept-d7-orm-entity]]).
- `validation` — **callback, возвращающий массив**: валидаторы создаются только когда действительно
  нужны (при выборке они не нужны).
- Штатные валидаторы: `RegExp`, `Length`, `Range`, `Unique`. На стенде они есть в обоих namespace —
  новом `ORM\Fields\Validators\*` и старом `Entity\Validator\*` (26.750.0).
- Свой валидатор — любой `callable`, получает `($value, $primary, $row, $field)` и возвращает `true`,
  текст ошибки или `FieldError` (со своим кодом — по нему потом отличают, что сработало).
- Штатные коды: `BX_INVALID_VALUE` (сработал валидатор) и `BX_EMPTY_REQUIRED` (нет обязательного
  поля при добавлении).
- **Валидаторы срабатывают и на `add`, и на `update`.** Нужна проверка только для одной операции —
  это уже события.
- К пользовательским полям валидаторы сущности **не применяются**: их проверки настраиваются в
  интерфейсе поля.

Не путать с пакетом `Main\Validation` (атрибуты над DTO) — это другой механизм и другой слой,
разбор в [[concept-validation-d7]] и [[recipe-d7-custom-validation-rule]].

## События вместо ручных проверок

Самый простой обработчик — одноимённый метод **в самом Table-классе**: ядро найдёт его само.

```php
public static function onBeforeAdd(\Bitrix\Main\ORM\Event $event)
{
    $result = new \Bitrix\Main\ORM\EventResult();
    $data = $event->getParameter('fields');

    if (isset($data['ISBN'])) {
        $result->modifyFields(['ISBN' => str_replace('-', '', $data['ISBN'])]);  // нормализуем
    }

    return $result;
}
```

| Что нужно | Чем |
|---|---|
| Поправить данные перед записью | `modifyFields()` |
| Тихо выбросить поле из набора | `unsetFields()` |
| Прервать операцию с ошибкой поля | `addError(new FieldError($field, '…'))` |
| Прервать операцию с общей ошибкой | `addError(new EntityError('…'))` |

Подписка со стороны (из своего модуля, на чужую сущность) — [[concept-orm-datamanager-events]]:
там же девять событий, их параметры и два способа подписки.

## Вычисляемые значения и счётчики

```php
BookTable::update($id, [
    'READERS_COUNT' => new \Bitrix\Main\DB\SqlExpression('?# + ?i', 'READERS_COUNT', $delta),
]);
// UPDATE ... SET READERS_COUNT = READERS_COUNT + 1 WHERE ID = ...
```

Считать на стороне базы правильнее, чем «прочитать — прибавить — записать»: между чтением и записью
значение может измениться. Плейсхолдеры `SqlExpression` обязательны — они же защищают от инъекции:

| Плейсхолдер | Что делает |
|---|---|
| `?` или `?s` | экранирует значение и берёт в кавычки |
| `?#` | экранирует как идентификатор (имя таблицы, колонки) |
| `?i` | приводит к integer |
| `?f` | приводит к float |

## Хранение в одном формате, работа в другом

```php
(new Fields\TextField('EDITIONS_ISBN'))->configureSerialized(),
```

Ярлык для пары `save_data_modification` / `fetch_data_modification`: в базе строка, в коде массив.
Перед тем как заводить такое поле, проверьте, не придётся ли по нему фильтровать — поиск по
сериализованной строке не индексируется ([[concept-d7-orm-entity]]).

## Проверка результата

- `add` вернул `isSuccess()` и ID; запись видна выборкой по первичному ключу.
- Невалидное значение даёт `isSuccess() === false` и понятный текст в `getErrorMessages()`.
- Обработчик `onBeforeAdd` поправил данные: в базе лежит нормализованное значение.
- В логе сайта нет `E_USER_WARNING` от ORM — значит, все результаты проверяются в коде.

## Чего избегать

- ❌ **Прямого SQL и `$connection->query()` вместо сущности** — мимо валидаторов, событий и кэша.
- ❌ Записи в чужие таблицы ядра, у которых есть высокоуровневое API (CRM, задачи).
- ❌ Вызова `add`/`update` без проверки результата.
- ❌ Даты строкой там, где ждут `Type\Date`.
- ❌ Конкатенации переменных в `SqlExpression` вместо плейсхолдеров.

## Связанные страницы
- [[concept-d7-orm-entity]] — описание сущности и полей
- [[concept-orm-datamanager-events]] — события сущности и подписка со стороны
- [[concept-d7-orm-objects]] — те же операции через объекты и коллекции
- [[concept-validation-d7]] — второй механизм проверок, не путать
- [[source-course43-orm-events]] — конспект уроков

[← Ядро D7](_index-core-d7.md)
