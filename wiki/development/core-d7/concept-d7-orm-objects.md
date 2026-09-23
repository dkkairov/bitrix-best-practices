---
title: "Объекты и коллекции ORM: EO_-классы вместо массивов"
type: concept
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-23 / курс 43 (уроки 11689–11749) + коробка в Docker, main 26.750.0: классы EO_User и EO_User_Collection, строгие типы, состояния объекта, поведение reset и групповые геттеры — прогон на UserTable"
tags: [orm, d7, объекты, коллекции, entityobject, состояние]
sources: ["[[source-course43-orm-events]]"]
related: ["[[concept-d7-orm-entity]]", "[[concept-d7-orm-query]]", "[[recipe-d7-orm-crud]]", "[[concept-bitrix-naming-conventions]]", "[[concept-orm-datamanager-events]]"]
aliases: []
updated: "2026-09-23"
---

# Объекты и коллекции ORM: `EO_`-классы вместо массивов

**TL;DR:** та же выборка может вернуть не массивы, а объекты: `fetchObject()` и `fetchCollection()`.
Объект знает типы своих полей, помнит исходные значения, различает «не выбрано» и «пусто», а
коллекция умеет групповые запросы. Источник — уроки курса 43 об
[объектах](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=11689) и
[коллекциях](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=11745).

## Откуда берутся классы

```php
$user = \Bitrix\Main\UserTable::query()
    ->setSelect(['ID', 'LOGIN', 'ACTIVE'])
    ->where('ID', 1)
    ->fetchObject();          // Bitrix\Main\EO_User

$users = \Bitrix\Main\UserTable::query()
    ->setSelect(['ID', 'LOGIN'])
    ->setLimit(3)
    ->fetchCollection();      // Bitrix\Main\EO_User_Collection
```

У каждой сущности свои классы объекта и коллекции, которые ядро создаёт на лету: к имени без
суффикса `Table` добавляется префикс `EO_` (EntityObject). Префикс выбран, чтобы не конфликтовать с
существующим классом `Book` в проекте ([[concept-bitrix-naming-conventions]]).

**Свой класс с человеческим именем** — наследник `EO_`-класса плюс метод в сущности:

```php
class Book extends EO_Book {}
class Books extends EO_Book_Collection {}

class BookTable extends DataManager
{
    public static function getObjectClass(): string { return Book::class; }
    public static function getCollectionClass(): string { return Books::class; }
}
```

В коде не используем `EO_Book` напрямую (`new`, `instanceof`, `::class`): либо свой класс, либо
обезличенные `BookTable::createObject()`, `BookTable::wakeUpObject()`, `BookTable::getObjectClass()`.
В своём классе нельзя заводить свойства с именами `primary`, `entity`, `dataClass` — они заняты
базовым классом.

## Именованные методы

Для каждого поля доступен свой набор методов: `getTitle()`, `setTitle()`, `remindActualTitle()`,
`resetTitle()`, `unsetTitle()`, `requireTitle()`, `fillTitle()`, `isTitleFilled()`,
`isTitleChanged()`, `hasTitle()`. У каждого есть универсальный близнец с именем поля аргументом:
`$book->get('TITLE')`, `$book->set('TITLE', $v)`.

- Реализованы через `__call`, поэтому **IDE и статический анализ их не видят** — для подсказок
  генерируют аннотации ORM.
- Переопределили именованный метод в своём классе — универсальный вызов тоже пойдёт через него.
  Исключение — `fill()`: он оптимизирован под пакетное заполнение и именованные методы не зовёт.

## Строгие типы

Объект приводит значения к типу поля: `getId()` вернёт `int`, `getLogin()` — `string`, а
`BooleanField` — настоящий `true`/`false`, хотя в базе лежит `Y`/`N` (проверено на `UserTable`).
Устанавливать тоже нужно `bool`, а не `'Y'`.

## Чтение: четыре разных «геттера»

| Метод | Что делает |
|---|---|
| `getTitle()` | текущее значение или `null`, если поле не выбрано |
| `requireTitle()` | то же, но бросает `SystemException`, если значения нет — «дальше без него нет смысла» |
| `remindActualTitle()` | значение, актуальное для базы: до `save()` оно отличается от установленного |
| `get('FIELD')` | универсальный вариант; **для runtime-полей запроса доступен только он** |

Плюс служебные: `primary` — read-only свойство с массивом первичного ключа (`['ID' => 1]`),
`collectValues()` — все значения массивом, с фильтрами `Values::ACTUAL`, `Values::CURRENT`,
`Values::ALL` и маской типов полей (`FieldTypeMask::SCALAR`, `ALL & ~USERTYPE` и т. п.).

## Запись и состояние

```php
$book->setTitle('Новое');       // текущее значение
$book->remindActualTitle();     // «Старое» — что лежит в базе
$book->resetTitle();            // отменить установку
$book->unsetTitle();            // забыть значение совсем, будто не выбирали
$book->save();                  // зафиксировать в базе
```

- Значение, равное актуальному, **в SQL не попадёт** — ядро само отбрасывает «пустые» изменения.
- `primary` меняется только у новых объектов; `ExpressionField` не устанавливается вовсе.
- Состояние объекта — read-only свойство `state` и константы `Objectify\State`: `RAW` (0) — новый,
  `ACTUAL` (1) — совпадает с базой, `CHANGED` (2) — отличается.

**Нюанс, которого нет в курсе:** `reset*()` возвращает значение и сбрасывает `isChanged()`, но
**состояние объекта остаётся `CHANGED`** — на стенде после `resetLogin()` значение снова `admin`,
`isLoginChanged()` = `false`, а `state` = 2. Ориентируйтесь на `isChanged()`, а не на `state`.

Проверки: `isFilled()` (есть актуальное значение из базы), `isChanged()` (установлено новое),
`has()` (хоть какое-то — сокращение от первых двух).

## Создание и удаление

```php
$book = new Book();                       // или BookTable::createObject()
$book->setTitle('Новое название');
$book->save();                            // state: RAW → ACTUAL

$book = Book::wakeUp(1);                  // объект по первичному ключу, без запроса
$book->delete();                          // state → RAW
```

`createObject(false)` и `new Book(false)` дают «чистый» объект без значений по умолчанию из
`getMap()`. `wakeUp()` удобен, когда ID уже известен: запрос в базу пойдёт только при `fill()` или
первом обращении к невыбранному полю.

Удаление объекта — та же операция, что `BookTable::delete()`: события срабатывают, но связанные
записи ядро не трогает, это на вас.

## Коллекции

```php
$books = BookTable::getList()->fetchCollection();

foreach ($books as $book) { /* Iterator */ }
$books->getAll();                 // массив объектов
$books->getByPrimary(1);
$books->hasByPrimary(1);
$books[] = Book::wakeUp(5);       // ArrayAccess
$books->removeByPrimary(2);
$titles = $books->getTitleList(); // групповой геттер поля (проверено: getLoginList())
```

**Групповые операции — главная причина использовать коллекции:**

| Операция | Что происходит |
|---|---|
| `save()` для новых объектов | один `INSERT` с несколькими `VALUES` |
| `save()` для изменённых | один `UPDATE ... WHERE ID IN (...)`, **если набор изменений одинаков**; иначе ядро сохранит по одному |
| `fill()` | один `SELECT ... WHERE ID IN (...)` вместо запроса на каждый объект |

У `save()` есть параметр `$ignoreEvents`. При мульти-вставке в таблицу с автоинкрементом события
приходится отключать: получить набор новых ID так же, как `mysqli_insert_id()` для одной строки,
невозможно. В остальных случаях события по умолчанию выполняются.

## Когда объекты, а когда массивы

- **Объекты** — когда работаете с одной-двумя записями и важны читаемость, типы и «что изменилось»:
  бизнес-логика, обработчики, правка данных.
- **Массивы (`fetchAll()`)** — когда нужен быстрый перебор тысяч строк на отчёт: объекты дороже по
  памяти и процессору (магические методы).
- **Коллекции** — когда записей много, но с ними нужны именно групповые операции: один запрос вместо
  цикла запросов.

## Подводные камни

- **`getTitle()` вернёт `null` и когда поля нет в `select`, и когда оно пустое.** Различать —
  `isFilled()` / `has()`, а требовать — `require*()`.
- **Цикл с `fill()` внутри** — классический антипаттерн: запрос на каждый объект. Собирайте
  коллекцию и вызывайте `fill()` у неё.
- **`EO_`-классы в коде** (`instanceof EO_Book`) ломаются при появлении своего класса объекта.
- **Групповой `UPDATE` не сработает**, если объекты изменены по-разному — будет по запросу на
  объект; для массовой правки приводите изменения к одному набору полей.

## Связанные страницы
- [[concept-d7-orm-entity]] — описание сущности, `getObjectClass()`
- [[concept-d7-orm-query]] — откуда берутся `fetchObject()` и `fetchCollection()`
- [[recipe-d7-orm-crud]] — те же операции в «массивном» API
- [[concept-orm-datamanager-events]] — события при сохранении объекта
- [[source-course43-orm-events]] — конспект уроков

[← Ядро D7](_index-core-d7.md)
