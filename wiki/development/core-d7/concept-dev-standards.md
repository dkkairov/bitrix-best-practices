---
title: "Стандарт разработки (коробка)"
type: concept
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-06-20 / Bitrix Framework (курс 43, devbook)"
tags: [стандарт, разработка, коробка, модули, регламент]
sources: ["[[source-bxfw-course43-modules]]"]
related: ["[[pattern-module-based-development-standard]]", "[[antipattern-box-core-modification]]", "[[concept-code-namespaces-and-autoloading]]"]
aliases: []
updated: "2026-06-20"
---

# Стандарт разработки (коробка)

**TL;DR:** разработка под коробку ведётся **через свои модули** и код в `/local/`, **без правки
ядра**, по пространствам имён, с расширением через события, версионированием и миграциями. Цель —
переиспользуемые, безопасные и быстро распространяемые на клиентские коробки решения.

> Применимость: **коробка** (`edition: box`). Для облака распространение делается тиражными
> REST-приложениями — это другой паттерн (см. модуль приложений маркетплейса).

## Обязательные принципы
1. **Не править ядро** `/bitrix/` — [[antipattern-box-core-modification|Правка ядра коробки]].
2. **Код — в `/local/` или своём модуле**, по пространствам имён и автозагрузке —
   [[concept-code-namespaces-and-autoloading|Пространства имён и автозагрузка]].
3. **Переиспользуемое/распространяемое — оформляем модулем** —
   [[pattern-module-based-development-standard|Когда модуль, а когда /local]].
4. **Расширяем платформу через события**, а не хаки —
   [[pattern-events-over-core-modification|Расширение через события]].
5. **Стиль и безопасность кода** — [[concept-coding-standards|Код-стайл и безопасность]].
6. **Структуру и данные переносим миграциями** — [[recipe-migrations-as-code|Миграции как код]].
7. **Окружение и git** едины в команде — [[checklist-dev-environment-and-git|Окружение разработки и git]].
8. **Тестируем свой код** — [[concept-testing-approach|Подход к тестированию]].
9. **Держим производительность** — [[checklist-box-performance|Чек-лист производительности]].

## Состав стандарта (страницы)
- [[pattern-module-based-development-standard|Модульная разработка: когда и как]]
- [[recipe-module-structure-and-install|Структура модуля и установка]]
- [[recipe-module-versioning-and-private-distribution|Версии и приватная дистрибуция]]
- [[pattern-events-over-core-modification|Расширение через события]]
- [[concept-code-namespaces-and-autoloading|Пространства имён и автозагрузка]]
- [[concept-coding-standards|Код-стайл и безопасность]]
- [[concept-testing-approach|Подход к тестированию]]
- [[recipe-migrations-as-code|Миграции как код]]
- [[checklist-dev-environment-and-git|Окружение разработки и git]]

[← Ядро D7](_index-core-d7.md)
