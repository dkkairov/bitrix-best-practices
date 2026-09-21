---
title: "Стандарт разработки (коробка)"
type: concept
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-06-20 / Bitrix Framework (курс 43); 2026-09-21 / сверено с «Книгой разработчика Bitrix24» (bx24devbook): Введение в разработку, Свой код"
tags: [стандарт, разработка, коробка, модули, регламент]
sources: ["[[source-bxfw-course43-modules]]", "[[source-devbook-dev-rules]]"]
related: ["[[pattern-module-based-development-standard]]", "[[antipattern-box-core-modification]]", "[[concept-code-namespaces-and-autoloading]]", "[[pattern-local-solution-structure]]", "[[concept-change-invasiveness-hierarchy]]"]
aliases: []
updated: "2026-09-21"
---

# Стандарт разработки (коробка)

**TL;DR:** разработка под коробку ведётся **без правки ядра**, только в `/local/` и публичной части,
по пространствам имён, с расширением через события, версионированием и переносом изменений
миграциями или инсталлерами. Переиспользуемое — **своими модулями**; код под одного клиента —
**решением** в `/local/php_interface` ([[pattern-local-solution-structure]]).

> **Уточнено 2026-09-21 по «Книге разработчика».** Раньше TL;DR ставил модули на первое место для
> любой доработки. Книга для Bitrix24 по умолчанию предлагает «решение», а модуль — только для
> переиспользуемого кода ([Свой код](https://bx24devbook.website.yandexcloud.net/Razrabotka/Struktura_papki_local/Svoj_kod.html#resenie-vs-modul)).
> Решение команды — совместить: вопрос «модулем или нет?» остаётся первым.

**«Как правильно» по книге** ([Введение в разработку](https://bx24devbook.website.yandexcloud.net/Razrabotka/Vvedenie.html#kak-pravil-no)):
система контроля версий; работа только с публичной частью и `/local`; песочница для разработки;
перенос изменений миграциями или автоматически; учётные данные — в `.env` и в БД, не в коде.

> Применимость: **коробка** (`edition: box`). Для облака распространение делается тиражными
> REST-приложениями — это другой паттерн (см. модуль приложений маркетплейса).

## Обязательные принципы
1. **Не править ядро** `/bitrix/` — [[antipattern-box-core-modification|Правка ядра коробки]].
2. **Код — в `/local/` или своём модуле**, по пространствам имён и автозагрузке —
   [[concept-code-namespaces-and-autoloading|Пространства имён и автозагрузка]].
3. **Переиспользуемое/распространяемое — оформляем модулем** —
   [[pattern-module-based-development-standard|Когда модуль, а когда /local]]; клиентский код —
   решением по [[pattern-local-solution-structure|структуре /local/php_interface]].
4. **Расширяем платформу через события**, а не хаки —
   [[pattern-events-over-core-modification|Расширение через события]].
5. **Стиль и безопасность кода** — [[concept-coding-standards|Код-стайл и безопасность]].
6. **Структуру и данные переносим миграциями** — [[recipe-migrations-as-code|Миграции как код]].
7. **Окружение и git** едины в команде — [[checklist-dev-environment-and-git|Окружение разработки и git]].
8. **Тестируем свой код** — [[concept-testing-approach|Подход к тестированию]].
9. **Держим производительность** — [[checklist-box-performance|Чек-лист производительности]].

## Состав стандарта (страницы)
- [[concept-change-invasiveness-hierarchy|Иерархия способов изменения]]
- [[pattern-module-based-development-standard|Модульная разработка: когда и как]]
- [[pattern-local-solution-structure|Решение в /local/php_interface]]
- [[recipe-module-structure-and-install|Структура модуля и установка]]
- [[recipe-module-versioning-and-private-distribution|Версии и приватная дистрибуция]]
- [[pattern-events-over-core-modification|Расширение через события]]
- [[concept-code-namespaces-and-autoloading|Пространства имён и автозагрузка]]
- [[concept-coding-standards|Код-стайл и безопасность]]
- [[concept-testing-approach|Подход к тестированию]]
- [[recipe-migrations-as-code|Миграции как код]]
- [[checklist-dev-environment-and-git|Окружение разработки и git]]

[← Ядро D7](_index-core-d7.md)
