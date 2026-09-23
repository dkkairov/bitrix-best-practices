---
title: "Выборка ORM: getList, Query и два формата фильтра"
type: concept
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-23 / курс 43 (уроки 5753, 5751, 3030) + коробка в Docker, main 26.750.0: SQL проверен через Query::getQuery() — фильтр без оператора даёт LIKE с UPPER, массив разворачивается в IN, работают Query::filter(), Query::expr(), whereExpr, count_total"
tags: [orm, d7, getList, query, фильтр, кэш, выборка]
sources: ["[[source-course43-orm-events]]"]
related: ["[[concept-d7-orm-entity]]", "[[concept-d7-orm-objects]]", "[[recipe-d7-orm-crud]]", "[[pattern-tasks-effectiveness-from-db]]", "[[recipe-custom-list-page-filter-grid]]"]
aliases: []
updated: "2026-09-23"
---

# Выборка ORM: `getList`, `Query` и два формата фильтра

**TL;DR:** `getList()` принимает один массив параметров и всегда возвращает `DB\Result`. Внутри он
строит тот же объект `Query`, который можно собрать руками. У фильтра два формата — старый массив с
операторами-префиксами и новый объектный; **в старом пропущенный `=` означает `LIKE`**, и это самая
дорогая ошибка новичка.

Источник — курс 43: [getList](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=5753),
[Объект Query](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=5751),
[Фильтр ORM](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=3030).

## Параметры getList

```php
$rows = BookTable::getList([
    'select'      => ['ID', 'TITLE', 'PUBLICATION' => 'PUBLISH_DATE'],  // алиас справа
    'filter'      => ['=ID' => 1],
    'order'       => ['PUBLISH_DATE' => 'DESC'],
    'limit'       => 20,
    'offset'      => 80,
    'group'       => ['PUBLISH_DATE'],   // обычно не нужен: группировку ядро выводит само
    'runtime'     => [],                 // временные поля этого запроса
    'cache'       => ['ttl' => 3600],
    'count_total' => true,
])->fetchAll();
```

- `'*'` в `select` берёт **только скалярные поля**: `ExpressionField` и связи всегда перечисляем явно.
- Выражение можно положить прямо в `select`, не объявляя `runtime`; выражения вкладываются друг в
  друга и разворачиваются в финальный SQL.
- `runtime`-поле живёт **один запрос**: в следующем `getList` его придётся объявить заново.
- `count_total => true` плюс `$result->getCount()` дают общее число записей без второго запроса
  (проверено: 11 пользователей при `limit 2`).

## Фильтр-массив: ловушка LIKE

```php
// то, что написано
BookTable::getList(['filter' => ['TITLE' => 'Патterns']]);
// то, что уходит в базу (стенд):
// WHERE UPPER(`b_book`.`TITLE`) like upper('Патterns')
```

**Без явного оператора ядро строит `LIKE`, причём регистронезависимый через `UPPER()`** — наследство
фильтров инфоблоков. Индекс по такому условию обычно не используется. Всегда пишем оператор явно:

| Префикс | Условие |
|---|---|
| `=` | равно; массив разворачивается в `IN (...)` (стенд: `['ID' => [1,2,3]]` → `IN (1, 2, 3)`) |
| `!=` | не равно |
| `%`, `=%`, `%=` | подстрока и `LIKE` (в `%=` шаблон задаёте сами: `'%тест%'`) |
| `!%`, `!=%`, `!%=` | отрицания подстроки и `LIKE` |
| `>`, `>=`, `<`, `<=` | сравнения |
| `@`, `!@` | `IN` / `NOT IN`, значение — массив или `SqlExpression` |
| `==` | булево выражение; `'==ID' => null` даёт `IS NULL`, `'!==ID' => null` — `IS NOT NULL` |

Многоуровневый фильтр склеивается через `AND`, логика меняется ключом `LOGIC`:

```php
'filter' => [
    'LOGIC' => 'OR',
    ['=ID' => 1, '=ISBN' => '9780321127426'],
    ['=ID' => 2],
],
```

## Объектный фильтр (с main 17.5.2)

```php
use Bitrix\Main\ORM\Query\Query;

$rows = UserTable::query()
    ->setSelect(['ID', 'LOGIN'])
    ->where('ACTIVE', true)                 // для Y/N-полей допустимы true/false
    ->where(Query::filter()->logic('or')
        ->where('ID', 1)
        ->where('LOGIN', 'admin'))
    ->whereNotNull('PERSONAL_BIRTHDAY')
    ->whereLike('NAME', 'A%')
    ->fetchAll();
```

- Операторные методы: `whereNull`/`whereNotNull`, `whereIn`/`whereNotIn`, `whereBetween`,
  `whereLike`, `whereExists`, `whereColumn`, `whereMatch`, `whereExpr` — и `whereNot*` к каждому.
- `whereExpr('JSON_CONTAINS(%s, 4)', ['SOME_JSON_FIELD'])` — произвольное выражение с привязкой к
  полям (плейсхолдеры как у `ExpressionField`).
- `Query::expr()` — хелпер готовых выражений: `count`, `countDistinct`, `sum`, `min`, `avg`, `max`,
  `length`, `lower`, `upper`, `concat`.
- Сравнение с колонкой — `whereColumn('NAME', 'LOGIN')` или объект `Column` внутри любого оператора.
- Объектный фильтр принимает и `getList`: `'filter' => Query::filter()->where('ID', 1)`.

**Нюанс для IDE и статических анализаторов:** методы `where*` физически живут в
`ORM\Query\Filter\ConditionTree`, а `Query` проксирует их через `__call`. `method_exists(Query::class,
'whereExpr')` вернёт `false`, хотя вызов работает (стенд).

## Объект Query

```php
$query = BookTable::query();
$query->setSelect(['ID']);
$query->setFilter(['=PUBLISH_DATE' => new Type\Date('2026-09-01', 'Y-m-d')]);

$sql = $query->getQuery();   // текст запроса, без выполнения
$result = $query->exec();    // выполнить
```

Годится, когда запрос собирается по частям (разные функции добавляют `addSelect`, `addFilter`,
`addOrder`) или когда нужен подзапрос либо сам текст SQL. `registerRuntimeField()` добавляет
временное поле.

**Почему переопределять `getList` в своей сущности бесполезно:** `Query` — основной механизм, и тот
же запрос можно выполнить мимо `getList`. Логику «на выборку» вешают не на метод, а на саму
сущность (поля-выражения, `Reference`) — иначе она обходится.

## Кэширование выборки

```php
BookTable::getList(['filter' => ['=ID' => 1], 'cache' => ['ttl' => 3600]]);
BookTable::query()->setFilter(['=ID' => 1])->setCacheTtl(150)->exec();
```

- По умолчанию выборки **не кэшируются** (возможность есть с 16.5.9).
- Выборки с `JOIN` не кэшируются, пока явно не разрешить: `'cache_joins' => true` или
  `cacheJoins(true)`.
- Кэш сбрасывается при `add`/`update`/`delete` этой сущности; принудительно —
  `BookTable::getEntity()->cleanCache()`.
- Таблицу целиком можно сделать некэшируемой методом `isCacheable()` ([[concept-d7-orm-entity]]).
- Результат кэшированной выборки может прийти объектом `ArrayResult` — рассчитывайте на интерфейс
  `fetch()`/`fetchAll()`, а не на конкретный класс.

## Подводные камни

- **Пропущенный `=` в фильтре** — тихий `LIKE` по всей таблице. Первое, что проверяют, когда выборка
  «странно медленная» или возвращает лишнее.
- **Пользовательский ввод прямо в фильтр** (особенно в формате массива с операторами) — способ
  раскрыть данные: поля и операторы пропускаем через белый список
  ([[recipe-custom-list-page-filter-grid]]).
- **`'*'` не тянет выражения и связи** — их перечисляем явно.
- **`runtime` не переживает запрос** — объявляйте заново или кладите поле в сущность.

## Связанные страницы
- [[concept-d7-orm-entity]] — описание сущности и полей
- [[concept-d7-orm-objects]] — `fetchObject()` вместо массивов
- [[recipe-d7-orm-crud]] — запись данных
- [[recipe-custom-list-page-filter-grid]] — фильтр и грид поверх такой выборки
- [[source-course43-orm-events]] — конспект уроков

[← Ядро D7](_index-core-d7.md)
