---
title: "Связи ORM: Reference, OneToMany, ManyToMany"
type: concept
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-23 / курс 43 (уроки 11737, 11739, 11741, 11707) + коробка в Docker, main 26.750.0: связи сущностей ядра, типы join, цепочки переходов, маски полей, аннотации и поведение сериализованного поля в объекте — прогоном; запись связей (addTo/removeFrom) не проверяли"
tags: [orm, d7, связи, reference, onetomany, manytomany, join]
sources: ["[[source-course43-orm-events]]"]
related: ["[[concept-d7-orm-entity]]", "[[concept-d7-orm-query]]", "[[concept-d7-orm-objects]]", "[[recipe-d7-orm-crud]]"]
aliases: []
updated: "2026-09-23"
---

# Связи ORM: `Reference`, `OneToMany`, `ManyToMany`

> **Проверено на стенде** (коробка в Docker, `main` 26.750.0, 2026-09-23): связи сущностей ядра,
> типы join, цепочки переходов и маски полей — прогоном; результаты и два уточнения к курсу — в
> разделе «Что прогнали на стенде».

**TL;DR:** внешний ключ сам по себе связью для ORM не является — связь описывают отдельным полем.
Три типа: `Reference` (много-к-одному и один-к-одному), `OneToMany` (обратная сторона) и
`ManyToMany` (через промежуточную таблицу). Дальше связь работает и в `select`, и в фильтре, и через
«геттеры» объектов.

## 1:N — Reference и обратная сторона

Поле `PUBLISHER_ID` в таблице книг — ещё не связь. Связь описывается полем `Reference`:

```php
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;

(new IntegerField('PUBLISHER_ID')),
(new Reference('PUBLISHER', PublisherTable::class, Join::on('this.PUBLISHER_ID', 'ref.ID')))
    ->configureJoinType('inner'),     // по умолчанию left
```

| Параметр | Что это |
|---|---|
| имя поля | как будем обращаться: `PUBLISHER` → `getPublisher()` |
| класс сущности | `PublisherTable::class` |
| условие join | объект фильтра; **префиксы `this.` и `ref.`** обозначают текущую и связываемую сущность |

`Join::on()` — это `Query::filter()` с заранее заданным `whereColumn`, к нему можно дописать любые
условия (`->where('ref.TYPE', 'admin')`) — см. [[concept-d7-orm-query]].

Обратная сторона описывается в сущности-партнёре:

```php
(new OneToMany('BOOKS', BookTable::class, 'PUBLISHER'))->configureJoinType('inner'),
```

Третий параметр — **имя `Reference`-поля у партнёра**, а не поля-ключа. Тип join по умолчанию
наследуется от того `Reference`.

## Выборка со связью

```php
$book = BookTable::getByPrimary(1, ['select' => ['*', 'PUBLISHER']])->fetchObject();
echo $book->getPublisher()->getTitle();
```

- `'*'` связи **не тянет** — имя связи в `select` указываем явно.
- В массивах поля связанной сущности получают длинные имена вида
  `VENDOR_PROJECT_BOOK_PUBLISHER_TITLE`; лечится алиасом: `'select' => ['*', 'PUB_' => 'PUBLISHER']`
  → `PUB_TITLE`.
- **Объекты не двоят данные:** две книги одного издателя дадут один объект издателя с коллекцией
  книг, а `fetchAll()` вернёт две строки с повторяющимися полями издателя. Это главный практический
  довод за [[concept-d7-orm-objects|объектную выборку]] при работе со связями.
- Цепочки переходов работают и глубже: `USER.GROUP.OWNER.ID` — и в `select`, и в фильтре. На стенде
  `['LOGIN' => 'USER.LOGIN']` в `select` и `where('USER.LOGIN', 'admin')` в фильтре дают обычный
  `LEFT JOIN` к таблице партнёра.

## Изменение связей

```php
// со стороны «многих»: достаточно передать объект, PUBLISHER_ID заполнится сам
$book->setPublisher($publisher);
$book->save();

// со стороны «одного»
$publisher->addToBooks($book);
$publisher->removeFromBooks($book);
$publisher->removeAllBooks();
$publisher->save();
```

- Менять связь **только этими методами**. Прямые `add()` / `remove()` у коллекции-значения к
  результату не приведут (урок 11707).
- Связь живёт в памяти до `save()`.
- `removeFrom` / `removeAll` требуют заполненного поля связи: либо выбрали его в `select`, либо
  вызовите `fillBooks()`. Для `removeAll` ядро дочитает значения само.
- Удаляется **связь, а не записи**: у книг просто обнулится `PUBLISHER_ID`.
- В «массивном» API `addTo` / `removeFrom` нет — связь ставится со стороны внешнего ключа.

## 1:1

То же самое, что 1:N, но **с обеих сторон `Reference`** вместо пары `Reference` + `OneToMany`.

## N:M — ManyToMany

Когда в промежуточной таблице только два ключа, отдельная сущность не нужна:

```php
(new ManyToMany('AUTHORS', AuthorTable::class))->configureTableName('b_book_author'),
```

- Ядро само создаёт в памяти сущность-посредник с полями `BOOK_ID`, `AUTHOR_ID` и двумя
  `Reference`; имена строятся из имени сущности и её первичного ключа.
- Имена можно задать явно: `configureLocalPrimary()`, `configureLocalReference()`,
  `configureRemotePrimary()`, `configureRemoteReference()` — это важно для составных ключей.
- Тип join настраивается так же: `configureJoinType('inner')` (проверено: без настройки в SQL
  `LEFT JOIN`, с ней — `INNER JOIN`).
- Описывать поле в обеих сущностях необязательно, но тогда доступ будет только с одной стороны.
- Чтение и изменение — как у 1:N: `getAuthors()`, `addToAuthors()`, `removeFromAuthors()`.

**Если у связи есть свои данные** (количество, дата, статус) — `ManyToMany` не подходит: добавить
связь можно только со значениями по умолчанию, а обновить их нечем. Тогда промежуточную таблицу
описывают **отдельной сущностью** с составным первичным ключом и двумя `Reference`, а в обеих
исходных сущностях заводят `OneToMany` на неё:

```php
(new OneToMany('STORE_ITEMS', StoreBookTable::class, 'BOOK')),
```

Дальше объект связи живёт как обычный элемент: `createObject()->setBook()->setStore()->setQuantity()`,
`save()`, `delete()` — [[recipe-d7-orm-crud]].

## Заполнение и восстановление объектов

- `fill()` — правильный способ дочитать недостающие поля: `fillLastName()`, `fill(['NAME', 'LAST_NAME'])`,
  `fill(FieldTypeMask::SCALAR)`. Ручное «прочитать и `set()`» делает значение *изменённым*, а не
  актуальным, и дальше путает логику сохранения.
- Маски типов (`ORM\Fields\FieldTypeMask`, значения со стенда): `SCALAR` 1, `EXPRESSION` 2,
  `USERTYPE` 4, `REFERENCE` 8, `ONE_TO_MANY` 16, `MANY_TO_MANY` 32, `FLAT` 3 (скаляры и выражения),
  `RELATION` 56 (все связи), `ALL` 63 — маски складываются через `|`.
- **`fill()` в цикле по объектам — антипаттерн**: запрос на каждый объект. Для нескольких объектов
  того же типа собираем коллекцию и вызываем `fill()` у неё.
- `wakeUp()` принимает не только первичный ключ, но и вложенные значения связей:
  `['ID' => 2, 'PUBLISHER' => ['ID' => 253, …], 'AUTHORS' => [[…], […]]]`.

## Аннотации для IDE

Именованные методы виртуальные, поэтому IDE их не видит. Лечится файлом аннотаций:

- аннотации ядра поставляются с `main` 20.100.0 — `/bitrix/modules/main/meta/orm.php` (на стенде
  файл есть, ~0,8 МБ);
- свои сущности аннотируются CLI-командой: `php bitrix.php orm:annotate -m vendor.module`
  (`-m all` — все модули, `-c` — сбросить накопленное), результат —
  `bitrix/modules/orm_annotations.php`;
- **команде нужен composer, а в типовой коробке его нет.** На стенде `php bitrix.php help
  orm:annotate` отвечает «Symfony Console is not installed. Please install and configure composer in
  your project». То есть аннотации своих сущностей — это отдельный шаг по настройке окружения
  ([документация 1С-Битрикс о composer](https://docs.1c-bitrix.ru/pages/get-started/composer.html)),
  а не команда «из коробки».

## Обратная совместимость (main 18.0.4+)

- **Имена полей регистронезависимы**: сущность с `LAST_NAME` и `last_name` больше не
  инициализируется.
- Алиас, отличающийся от поля только регистром, запрещён: `['id' => 'ID']` не пройдёт.
- `BooleanField` не принимает пустую строку — только `true`/`false` или настроенные значения.
- **Сериализованные поля в объектах не поддерживаются — и ломаются молча.** Курс говорит
  «не поддерживаются», а на стенде видно, что именно происходит: у `UserFieldTable::SETTINGS`
  (поле сериализовано) `fetch()` возвращает **массив**, а `fetchObject()` — **строку `'Array'`**.
  Исключения нет, значение просто испорчено приведением к объявленному типу. Сущность с
  `configureSerialized()` читаем «массивным» API ([[concept-d7-orm-entity]]).

## Что прогнали на стенде

Коробка в Docker, `main` 26.750.0, 2026-09-23; проверяли на связях сущностей ядра
(`UserGroupTable` → `UserTable`), запись связей не трогали.

| Проверка | Результат |
|---|---|
| Классы `Reference`, `OneToMany`, `ManyToMany`, `Query\Join` | есть все |
| Связь в `select` с алиасом (`'U_' => 'USER'`) | колонки партнёра пришли как `U_LOGIN`, `U_NAME`, … |
| Связь в объектной выборке | `getUser()` вернул `EO_User`, `getLogin()` — значение |
| Цепочка через точку (`USER.LOGIN`) в `select` и фильтре | работает, в SQL — `LEFT JOIN` |
| Тип join | по умолчанию `LEFT`, `configureJoinType('inner')` → `INNER JOIN` |
| Маски `FieldTypeMask` | значения совпали с курсом (`FLAT` 3, `RELATION` 56, `ALL` 63) |
| Файл аннотаций ядра | `/bitrix/modules/main/meta/orm.php` на месте |
| CLI `orm:annotate` | **не запускается без composer** — см. выше |
| Сериализованное поле в объекте | `fetch()` даёт массив, `fetchObject()` — строку `'Array'` |

Не проверяли: `addTo*` / `removeFrom*` / `removeAll*` на живых данных — это запись в связи реальных
сущностей портала, для такой проверки нужна своя таблица.

## Связанные страницы
- [[concept-d7-orm-entity]] — описание сущности, куда добавляются поля связей
- [[concept-d7-orm-query]] — `Join::on()`, фильтр и цепочки переходов
- [[concept-d7-orm-objects]] — объекты, коллекции, `fill()`
- [[recipe-d7-orm-crud]] — запись через сущность-посредник
- [[source-course43-orm-events]] — конспект уроков курса

[← Ядро D7](_index-core-d7.md)
