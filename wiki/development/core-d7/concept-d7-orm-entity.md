---
title: "Сущность ORM: Table-класс и описание полей"
type: concept
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-23 / курс 43 «Разработчик Bitrix Framework» (уроки 4803, 2244) + коробка в Docker, main 26.750.0: наличие isCacheable(), алиасы Entity\\* на ORM\\*, состав полей через getEntity(); 2026-09-24 — полный состав классов main/lib/ORM/Fields сверен по ядру стенда и docs.1c-bitrix.ru"
tags: [orm, d7, datamanager, сущность, поля, кэш]
sources: ["[[source-course43-orm-events]]"]
related: ["[[concept-d7-orm-query]]", "[[recipe-d7-orm-crud]]", "[[concept-d7-orm-objects]]", "[[concept-orm-datamanager-events]]", "[[concept-bitrix-naming-conventions]]"]
aliases: []
updated: "2026-09-24"
---

# Сущность ORM: Table-класс и описание полей

**TL;DR:** сущность D7 — это PHP-класс `<Имя>Table`, который описывает таблицу декларативно:
имя таблицы, поля, их типы, ограничения и вычисляемые выражения. Ядро по этому описанию само
строит SQL, проверяет данные и шлёт события. Источник — курс 43, уроки
[Концепция, описание сущности](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=4803)
и [Операции с сущностями](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=2244).

## Минимальное описание

```php
namespace Vendor\Project\Catalog;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields;

class BookTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'vendor_book';
    }

    public static function getMap(): array
    {
        return [
            (new Fields\IntegerField('ID'))->configurePrimary()->configureAutocomplete(),
            (new Fields\StringField('ISBN'))->configureRequired()->configureColumnName('ISBNCODE'),
            (new Fields\StringField('TITLE')),
            (new Fields\DateField('PUBLISH_DATE')),
        ];
    }
}
```

| Метод | Зачем |
|---|---|
| `getTableName()` | имя таблицы; без него ядро соберёт имя из namespace и класса (`b_vendor_project_catalog_book`) |
| `getMap()` | описание полей — **только первичная конфигурация** |
| `getUfId()` | идентификатор для пользовательских полей (`UF_*`) |
| `getObjectClass()`, `getCollectionClass()` | свои классы объекта и коллекции ([[concept-d7-orm-objects]]) |
| `isCacheable()` | `false` запрещает кэширование выборок этой таблицы (с `main` 24.100.0; на стенде метод есть и по умолчанию `true`) |

**Действительный список полей — не `getMap()`, а `BookTable::getEntity()->getFields()`**: ядро
дополняет карту пользовательскими полями и runtime-полями запроса.

## Соглашения имён

- Класс сущности **всегда заканчивается на `Table`**. Имя без суффикса (`Book`) зарезервировано под
  класс объекта ([[concept-d7-orm-objects]], [[concept-bitrix-naming-conventions]]).
- Поля называем заглавными буквами, имена уникальны в пределах сущности.
- Namespace старый (`Bitrix\Main\Entity\*`) и новый (`Bitrix\Main\ORM\*`) — **оба рабочие**: на стенде
  `Entity\DataManager` и `Entity\Query` наследуют соответствующие классы `ORM\` (26.750.0). В курсе
  примеры на `Entity\`, в новом коде пишем `ORM\`.

## Настройки поля

| Настройка | Что делает |
|---|---|
| `primary` | поле входит в первичный ключ; по нему идентифицируются `update` и `delete` |
| `autocomplete` | значение выдаёт база (автоинкремент) — при добавлении его не требуют |
| `required` | обязательное поле; без него `add` вернёт ошибку `BX_EMPTY_REQUIRED` |
| `column_name` | другое имя колонки в таблице; на одну колонку можно повесить несколько полей сущности |
| `default_value` | значение или **любой `callable`** — вычисляется при добавлении |
| `serialized` | ярлык для пары «сериализовать при записи / развернуть при чтении» |
| `save_data_modification`, `fetch_data_modification` | свои преобразования значения в обе стороны |
| `validation` | callback, возвращающий массив валидаторов ([[recipe-d7-orm-crud]]) |

Составной первичный ключ возможен: `primary` ставится нескольким полям, а в `update`/`delete`
передаётся массив значений.

**Форма записи.** Курс показывает настройки массивом (`['primary' => true]`), но в текущем ядре у
полей есть беглый API, и он шире курса: кроме `configurePrimary()`, `configureRequired()`,
`configureAutocomplete()`, `configureColumnName()`, `configureDefaultValue()`,
`configureSerialized()` есть ещё `configureNullable()`, `configureUnique()`, `configureSize()`,
`configureTitle()`, `configureFulltext()` (стенд, 26.750.0). Массивная форма остаётся для
совместимости.

**Про сериализованные поля:** фильтровать и джойнить по ним неэффективно — `WHERE` по подстроке
внутри сериализованной строки не индексируется. Если по значению нужно искать, нормализуйте схему.

## Типы полей

Курс называет восемь скалярных типов (`ScalarField`): целое, число, строка, текст, дата,
дата-время, да/нет, значение из списка. Два из них требуют настройки значений:

```php
(new Fields\BooleanField('IS_ARCHIVED'))->configureValues('N', 'Y'),   // false, true
(new Fields\EnumField('STATUS'))->configureValues(['NEW', 'DONE']),
```

`BooleanField` хранит в базе пару значений, а в коде работает с `true`/`false` — в объектах
приведение строгое ([[concept-d7-orm-objects]]).

**Типов в ядре больше восьми.** Состав `main/lib/ORM/Fields` на стенде (26.750.0) совпадает с тем,
что показывает [новая документация фреймворка](https://docs.1c-bitrix.ru/pages/orm/orm-concepts.html):

| Класс | Наследует | Когда нужен |
|---|---|---|
| `IntegerField`, `FloatField`, `StringField`, `DateField` | `ScalarField` | базовые скаляры |
| `TextField` | `StringField` | длинный текст |
| `DatetimeField` | `DateField` | дата со временем |
| `DecimalField` | `FloatField` | фиксированная точность: деньги, количества |
| `BooleanField`, `EnumField` | `ScalarField` | пара значений / список, оба с `configureValues()` |
| `ArrayField` | `ScalarField` | массив: `configureSerializationJson()`, `configureSerializationPhp()` или своя пара `configureSerializeCallback()` / `configureUnserializeCallback()` |
| `JsonField` | `ScalarField` | JSON-значение |
| `ObjectField` | `ScalarField` | объект: `configureObjectClass()` плюс те же callback'и |
| `CryptoField` | `TextField` | шифрование при записи и расшифровка при чтении; ключ — параметр `crypto_key` поля или `crypto` → `crypto_key` из `.settings.php`, без ключа поле ведёт себя как обычный текст |
| `SecretField` | `CryptoField` | секрет с генерацией значения: `configureSecretLength()` |
| `UserTypeField` | `ExpressionField` | UF-поле; в `getMap()` руками не пишем — его добавляет ядро по `getUfId()` |
| `ExpressionField` | `Field` | вычисляемое поле, только чтение (ниже) |

`ArrayField` и `ObjectField` — штатная замена ярлыку `serialized`: способ сериализации задаётся
явно, а JSON вместо `serialize()` читается человеком и переживает переименование класса.

## Вычисляемые поля (ExpressionField)

```php
new Fields\ExpressionField('AGE_DAYS', 'DATEDIFF(NOW(), %s)', ['PUBLISH_DATE']),
```

- Плейсхолдеры — как в `sprintf`: `%s` подряд или `%1$s`, `%2$s`, если поле участвует дважды.
- Выражения **вкладываются**: новое выражение может ссылаться на другое, ядро развернёт цепочку.
- **Только на чтение.** Выбирать, фильтровать, группировать и сортировать можно; записать —
  исключение, физической колонки нет.
- Часто нужны не в описании сущности, а прямо в запросе — тогда их объявляют в `runtime` или сразу
  в `select` ([[concept-d7-orm-query]]).

## Пользовательские поля

Своя сущность получает поддержку UF одним методом:

```php
public static function getUfId(): string
{
    return 'VENDOR_BOOK';
}
```

Дальше поля заводятся через административный интерфейс и выбираются наравне со штатными. С
`main` 20.5.200 в UF-полях ORM поддерживаются значения `SqlExpression`. Штатные валидаторы полей
сущности к пользовательским полям **не применяются** — их проверки настраиваются в интерфейсе.

## Где лежит код

Класс сущности — в `lib/` модуля (`lib/book.php` для `BookTable`) или в
`/local/php_interface/classes/` для решения без модуля ([[pattern-local-solution-structure]],
[[concept-code-namespaces-and-autoloading]]). Автозагрузка найдёт класс по пути.

Черновой `CREATE TABLE` по описанию сущности даёт
`BookTable::getEntity()->compileDbTableStructureDump()` — удобно, когда таблицу ещё предстоит
создать в миграции ([[recipe-migrations-as-code]]).

## Подводные камни

- **`getMap()` — не источник истины о полях.** Для списка полей — `getEntity()->getFields()`.
- **Старая форма описания массивом** (`'ID' => ['data_type' => 'integer', ...]`) работает для
  совместимости, но в новом коде используем объекты полей.
- **Имя таблицы по умолчанию** длинное и зависит от namespace: у переименования класса будет цена,
  если `getTableName()` не задан явно.
- **`isCacheable()` появился в 24.100.0** — на коробках постарше его нет, кэширование там
  отключают на уровне конкретной выборки.

## Связанные страницы
- [[recipe-d7-orm-crud]] — запись данных, валидаторы, ошибки
- [[concept-d7-orm-query]] — выборка: `getList`, `Query`, фильтр
- [[concept-d7-orm-objects]] — объекты и коллекции вместо массивов
- [[concept-orm-datamanager-events]] — девять событий сущности
- [[source-course43-orm-events]] — конспект уроков курса

[← Ядро D7](_index-core-d7.md)
