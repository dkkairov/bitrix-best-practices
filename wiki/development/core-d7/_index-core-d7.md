---
title: "Ядро D7"
type: index
module: core-d7
edition: box
status: verified
updated: "2026-06-21"
---

# Ядро D7 — практики разработки (коробка)

Современное ядро Bitrix: ORM, события (EventManager), сервисный локатор/DI, `Result`/`Error`,
работа через `init.php` и локальные модули вместо правки ядра.

## Что покрываем
- ORM D7: сущности, запросы, связи
- События D7 и совместимость со старым ядром
- Где размещать код: `local/`, события, обработчики
- `Result`/`Error`, логирование

## Страницы
- [[concept-bitrix-framework-vs-bitrix24|Bitrix Framework vs Bitrix24: движок и продукт]]
- [[concept-dev-standards|Стандарт разработки (коробка) — обзор]]
- [[concept-code-namespaces-and-autoloading|Пространства имён, автозагрузка, размещение кода в /local/]]
- [[recipe-composer-third-party-libraries|Сторонние Composer-пакеты (dompdf, PhpWord)]]
- [[pattern-events-over-core-modification|Расширение через события]]
- [[concept-coding-standards|Код-стайл и безопасность]]
- [[concept-testing-approach|Подход к тестированию]]
- [[antipattern-box-core-modification|Антипаттерн: правка ядра вместо событий]]

## Статус покрытия
Есть стандарт разработки, организация кода, события, код-стайл/безопасность, тесты и ключевой антипаттерн. Не хватает: рецепты ORM D7.

[← Обзор вики](../../../index.md)
