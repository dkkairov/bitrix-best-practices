---
title: "Конспект: «Книга разработчика», ядро D7 — пайплайн, события, сервисы, агенты, валидация"
type: source-summary
module: core-d7
edition: box
status: verified
provenance: documented
verified: "2026-09-18 / перенос из архивной вики; первичный источник — dev.1c-bitrix.ru"
tags: [d7, пайплайн, события, orm, servicelocator, агенты, валидация]
sources: []
related: ["[[concept-request-lifecycle]]", "[[concept-orm-datamanager-events]]", "[[concept-service-locator]]", "[[concept-validation-d7]]", "[[concept-bitrix-naming-conventions]]", "[[entity-event-manager]]", "[[entity-main-event]]", "[[entity-main-result]]", "[[entity-cagent]]", "[[entity-validation-service]]", "[[entity-validation-result]]", "[[pattern-agents-vs-cron]]"]
aliases: []
updated: "2026-09-18"
---

# Конспект: ядро D7

## Об источнике
| Поле | Значение |
|------|----------|
| Тип | официальная документация / «Книга разработчика Bitrix24» |
| Разделы | обработка URI, страница и её пайплайн, ядро продукта, события, локатор служб, агенты, отложенные функции, валидация, соглашения именования |
| URL | https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43 и материалы «Книги разработчика» |
| Raw | снимки глав не копировались (см. [[source-devbook-dev-rules]]) |

**Как получен:** перенос из архивной вики 2026-09-18, первичный источник при переносе не
перечитывался.

## TL;DR
Как устроен запрос от URL до закрытия соединения с БД, где в этом пайплайне точки расширения, как
работают события двух поколений, откуда берутся сервисы и чем агенты отличаются от cron.

## Ключевые тезисы
- Пайплайн из двух фаз: выбор файла по URI (физический файл → `urlrewrite` → `404.php`) и
  исполнение страницы. Единой точки входа нет.
- `init.php` подключается **до** `OnPageStart`; `$USER` появляется только на шаге авторизации.
- Буферизация вывода с начала пролога до конца эпилога — на ней стоят отложенные функции.
- Агенты после версии 20.5.0 выполняются в фоновых работах после отдачи страницы; отсюда их
  плавающее время и потолок по длительности.
- `DataManager` даёт девять событий жизненного цикла; имя события — класс **без суффикса `Table`**,
  регистр namespace значим, неверное имя не даёт ни ошибки, ни предупреждения.
- `ServiceLocator` — простой DI-контейнер; код сервиса обязательно с вендором и в нижнем регистре;
  три места регистрации, из которых правка `/bitrix/.settings.php` недопустима.
- Валидация — декларативная, через PHP-атрибуты; работает рефлексией, поэтому видит `private`
  и различает «не установлено» и «явный `null`».
- `Result` / `Error` — сквозной способ вернуть результат вместо исключения.
- Два поколения классов сосуществуют: `CCrmDeal` и `\Bitrix\Crm\DealTable`.

## Что встроено в вики
- Концепты: [[concept-request-lifecycle]], [[concept-orm-datamanager-events]],
  [[concept-service-locator]], [[concept-validation-d7]], [[concept-bitrix-naming-conventions]].
- Карточки классов: [[entity-event-manager]], [[entity-main-event]], [[entity-main-result]],
  [[entity-cagent]], [[entity-validation-service]], [[entity-validation-result]].
- Решение: [[pattern-agents-vs-cron]].

## Противоречия с текущими страницами
Одно, снятое отдельно: источник рекомендует `registerEventHandler` для модулей без оговорок, а
живая проверка показала, что для событий D7 ORM этот путь молча не работает. Оговорка добавлена в
[[pattern-events-over-core-modification]], разбор — [[recipe-d7-orm-event-subscription]].

## Открытые вопросы
- Чем `OnPageStart` отличается от `OnBeforeProlog` и `OnProlog` по доступным данным.
- Как пайплайн меняется при включённом композитном кэше.
- API «ленивых параметров» события.

[← Конспекты источников](_index-sources.md)
