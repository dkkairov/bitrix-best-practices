---
title: "Конспект: курс 43 «Разработчик Bitrix Framework» — ORM и события"
type: source-summary
module: core-d7
edition: box
status: verified
provenance: documented
verified: "2026-09-23 / dev.1c-bitrix.ru, COURSE_ID=43: 17 уроков раздела ORM и 2 урока раздела «События» (снимок-манифест raw/sources/2026-09-23-course43-orm-events-manifest.md); спорное сверено на коробке main 26.750.0"
tags: [курс-43, orm, d7, события, datamanager, источник]
sources: []
related: ["[[concept-d7-orm-entity]]", "[[concept-d7-orm-query]]", "[[concept-d7-orm-objects]]", "[[recipe-d7-orm-crud]]", "[[concept-orm-datamanager-events]]", "[[source-course57-basics]]"]
aliases: []
updated: "2026-09-23"
---

# Конспект: курс 43, разделы ORM и «События»

**Источник:** [курс «Разработчик Bitrix Framework»](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&INDEX=Y),
уровень Middle, разделы **ORM** (17 уроков) и **События** (2 урока). Снимок-манифест с датами и
хэшами уроков — `raw/sources/2026-09-23-course43-orm-events-manifest.md`. Текст курса в репозиторий
не копируем.

## TL;DR

ORM в D7 — это описание таблицы PHP-классом `<Имя>Table`: поля, ограничения, выражения. По этому
описанию ядро строит SQL, проверяет данные, шлёт девять событий и умеет отдавать результат как
массивами, так и объектами. Раздел «События» в курсе крошечный и показывает **старое** ядро
(`AddEventHandler`, `RegisterModuleDependences`, отмена через `return false`), а всё интересное про
события ORM лежит в уроке об операциях с сущностями.

## Ключевые тезисы

**Сущность (уроки 4803, 2244)**
- Класс всегда `…Table`; имя без суффикса зарезервировано под класс объекта.
- `getMap()` — только первичная конфигурация; действительный состав — `getEntity()->getFields()`.
- Настройки поля: `primary`, `autocomplete`, `required`, `column_name`, `default_value` (в том числе
  `callable`), `serialized`, `save_data_modification` / `fetch_data_modification`, `validation`.
- `ExpressionField` — SQL-выражение с плейсхолдерами `sprintf`; **только на чтение**, выражения
  вкладываются друг в друга.
- Пользовательские поля подключаются методом `getUfId()`; штатные валидаторы к ним не применяются.
- С `main` 24.100.0 таблицу можно объявить некэшируемой методом `isCacheable()`.

**Запись (урок 2244)**
- `add` / `update` / `delete` возвращают `AddResult` / `UpdateResult` / `DeleteResult`;
  `getId()`, `getAffectedRowsCount()`.
- Даты — объектами `Type\Date` / `Type\DateTime`. Ключ `FIELDS` только заглавными: `fields` и
  `auth_context` зарезервированы.
- **Если результат не проверить, а валидация не прошла — ядро поднимет `E_USER_WARNING` в лог**, а
  код пойдёт дальше.
- Валидаторы: `validation` — callback, возвращающий массив; штатные `RegExp`, `Length`, `Range`,
  `Unique`; свой — `callable`, возвращающий `true`, текст или `FieldError` со своим кодом; штатные
  коды `BX_INVALID_VALUE`, `BX_EMPTY_REQUIRED`. Срабатывают и на `add`, и на `update`.
- Счётчики — `DB\SqlExpression('?# + ?i', 'FIELD', $n)`; плейсхолдеры `?`/`?s`, `?#`, `?i`, `?f`.

**События сущности (урок 2244)**
- Девять событий; обработчиком может быть одноимённый метод **в самом Table-классе**.
- `EventResult`: `modifyFields()`, `unsetFields()`, `addError(FieldError|EntityError)`.
- Подписка со стороны — `\Bitrix\Main\ORM\EventManager` с константой `DataManager::EVENT_ON_*`.

**Выборка (уроки 5753, 5751, 3030)**
- `getList` — единый набор секций: `select`, `filter`, `group`, `order`, `limit`, `offset`,
  `runtime`, плюс `cache` и `count_total`.
- `'*'` берёт только скалярные поля; выражение можно класть прямо в `select`; `runtime`-поле живёт
  один запрос.
- **Фильтр-массив без оператора означает `LIKE`** — наследие инфоблоков.
- Объектный фильтр с `main` 17.5.2: `where`, `whereIn`, `whereBetween`, `whereLike`, `whereNull`,
  `whereExists`, `whereColumn`, `whereExpr`, вложенные `Query::filter()` с `logic('or')`,
  хелпер `Query::expr()`.
- `Query` — тот же механизм, что внутри `getList`: можно собрать запрос по частям, получить SQL без
  выполнения, использовать как подзапрос. Отсюда вывод курса: **переопределять `getList` в своей
  сущности бессмысленно** — запрос выполнят мимо него.
- Кэш выборки с 16.5.9 (`cache => ttl`); JOIN не кэшируются без `cache_joins`; сброс — при любой
  записи или `getEntity()->cleanCache()`.

**Объекты и коллекции (уроки 11689–11749)**
- `fetchObject()` / `fetchCollection()` дают классы `EO_<Имя>` и `EO_<Имя>_Collection`; свои классы
  подключаются `getObjectClass()` / `getCollectionClass()`.
- Именованные методы (`getTitle`, `setTitle`, `remindActualTitle`, …) реализованы через `__call`;
  IDE их видит только по сгенерированным аннотациям.
- Строгая типизация, в том числе `BooleanField` → `true`/`false`.
- Состояния объекта: `RAW`, `ACTUAL`, `CHANGED`; проверки `isFilled`, `isChanged`, `has`.
- Коллекции дают групповые `save()` (один INSERT или один UPDATE) и `fill()` (один SELECT вместо
  цикла запросов); `$ignoreEvents` обязателен при мульти-вставке в таблицу с автоинкрементом.

**События старого ядра (уроки 3113, 3395)**
- `new Event('main', 'OnPageStart')` + `send()`; результаты обработчиков — `getResults()` с типами
  `SUCCESS`, `ERROR`, `UNDEFINED`.
- Урок-практикум разбирает старое ядро: число аргументов и передача по ссылке определяются по
  исходникам, отмена — `return false`, текст причины — `$APPLICATION->throwException()`.
- Совет курса: держать обработчики методами класса (по классу на модуль), а не отдельными функциями.

## Что встроено в вики

| Страница | Что добавилось |
|---|---|
| [[concept-d7-orm-entity]] | новая: Table-класс, карта полей, настройки, выражения, UF, кэш таблицы |
| [[recipe-d7-orm-crud]] | новая: запись, валидаторы, события-хуки, `SqlExpression`, `E_USER_WARNING` |
| [[concept-d7-orm-query]] | новая: секции `getList`, оба формата фильтра, `Query`, кэш выборки |
| [[concept-d7-orm-objects]] | новая: объекты, состояния, коллекции и групповые операции |
| [[concept-orm-datamanager-events]] | три способа подписки вместо одного; что умеет `EventResult` |

## Расхождения и уточнения (сверка на коробке 26.750.0)

- **`whereExpr` у `Query` «не существует» для `method_exists`.** Методы `where*` живут в
  `ORM\Query\Filter\ConditionTree`, `Query` проксирует их через `__call`: вызов работает, статический
  анализ его не видит.
- **Беглый API полей шире курса.** Курс показывает настройки массивом; в ядре есть
  `configurePrimary()`, `configureNullable()`, `configureUnique()`, `configureSize()` и другие — но
  **валидаторы так не задать**: `configureValidation()` нет, только параметр конструктора или
  `addValidator()`.
- **`reset*()` не возвращает состояние объекта.** Значение откатывается и `isChanged()` становится
  `false`, но `state` остаётся `CHANGED` — ориентируемся на `isChanged()`.
- **Фильтр без оператора** на стенде даёт `UPPER(поле) like upper('значение')` — то есть ещё и
  регистронезависимое сравнение; массив в `=` разворачивается в `IN (...)`.
- **Namespace `Entity\*` и `ORM\*`** сосуществуют: `Entity\DataManager` и `Entity\Query` — наследники
  новых классов. Курс написан на старых именах.

## Открытые вопросы

- Связи между сущностями (`ReferenceField`, `N:M`) — в этот заход не брали, отдельные уроки курса.
- Аннотации ORM для IDE: курс упоминает генерацию, но команда и место файла не разобраны.
- `whereMatch` в `ConditionTree` — в курсе не описан, назначение не проверяли.

## Связанное
- [[concept-d7-orm-entity]], [[concept-d7-orm-query]], [[concept-d7-orm-objects]], [[recipe-d7-orm-crud]]
- [[concept-orm-datamanager-events]] — события сущности и подписка
- [[recipe-d7-orm-event-subscription]] — подписка из модуля, живая проверка
- [[source-course57-basics]] — конспект другого официального курса

[← Конспекты источников](_index-sources.md)
