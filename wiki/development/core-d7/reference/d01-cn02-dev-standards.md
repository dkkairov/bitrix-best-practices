---
id: "D01-CN02"
title: "Стандарт разработки (коробка)"
type: concept
module: core-d7
edition: box
status: verified
confidence: high
provenance: mixed
verified: "2026-06-20 / Bitrix Framework (курс 43, devbook)"
tags: [стандарт, разработка, коробка, модули, регламент]
sources: ["[[s-ss03-src-bxfw-course43-modules]]"]
related: ["[[d03-pt01-module-based-development-standard]]", "[[d01-ap01-box-core-modification]]", "[[d01-cn01-code-namespaces-and-autoloading]]"]
aliases: []
updated: "2026-06-20"
---

# Стандарт разработки (коробка)

**TL;DR:** разработка под коробку ведётся **через свои модули** и код в `/local/`, **без правки
ядра**, по пространствам имён, с расширением через события, версионированием и миграциями. Цель —
переиспользуемые, безопасные и быстро распространяемые на клиентские коробки решения.

> Применимость: **коробка** (`edition: box`). Для облака распространение делается тиражными
> REST-приложениями — это другой паттерн (см. модуль [Приложения маркетплейса](../../../modules/marketplace-apps/_index.md)).

## Обязательные принципы
1. **Не править ядро** `/bitrix/` — [[d01-ap01-box-core-modification|Правка ядра коробки]].
2. **Код — в `/local/` или своём модуле**, по пространствам имён и автозагрузке —
   [[d01-cn01-code-namespaces-and-autoloading|Пространства имён и автозагрузка]].
3. **Переиспользуемое/распространяемое — оформляем модулем** —
   [[d03-pt01-module-based-development-standard|Когда модуль, а когда /local]].
4. **Расширяем платформу через события**, а не хаки —
   [[d01-pt01-events-over-core-modification|Расширение через события]].
5. **Стиль и безопасность кода** — [[d01-cn03-coding-standards|Код-стайл и безопасность]].
6. **Структуру и данные переносим миграциями** — [[d06-rc01-migrations-as-code|Миграции как код]].
7. **Окружение и git** едины в команде — [[d07-cl01-dev-environment-and-git|Окружение разработки и git]].
8. **Тестируем свой код** — [[d01-cn04-testing-approach|Подход к тестированию]].
9. **Держим производительность** — [[d05-cl01-box-performance-checklist|Чек-лист производительности]].

## Состав стандарта (страницы)
- **D03-PT01** [[d03-pt01-module-based-development-standard|Модульная разработка: когда и как]]
- **D03-RC01** [[d03-rc01-module-structure-and-install|Структура модуля и установка]]
- **D03-RC02** [[d03-rc02-module-versioning-and-private-distribution|Версии и приватная дистрибуция]]
- **D01-PT01** [[d01-pt01-events-over-core-modification|Расширение через события]]
- **D01-CN01** [[d01-cn01-code-namespaces-and-autoloading|Пространства имён и автозагрузка]]
- **D01-CN03** [[d01-cn03-coding-standards|Код-стайл и безопасность]]
- **D01-CN04** [[d01-cn04-testing-approach|Подход к тестированию]]
- **D06-RC01** [[d06-rc01-migrations-as-code|Миграции как код]]
- **D07-CL01** [[d07-cl01-dev-environment-and-git|Окружение разработки и git]]

[← Ядро D7](../_index.md)
