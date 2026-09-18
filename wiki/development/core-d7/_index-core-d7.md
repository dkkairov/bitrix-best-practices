---
title: "Ядро D7"
type: index
module: core-d7
edition: box
status: verified
updated: "2026-09-18"
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
- [[concept-change-invasiveness-hierarchy|Иерархия способов изменения: от штатного механизма до копии в /local/]]
- [[concept-platform-reverse-engineering|Исследование платформы: 4 приёма, когда документация молчит]]
- [[concept-bitrix-naming-conventions|Соглашения именования: CCrmDeal против \Bitrix\Crm\DealTable]]
- [[concept-code-namespaces-and-autoloading|Пространства имён, автозагрузка, размещение кода в /local/]]
- [[recipe-composer-third-party-libraries|Сторонние Composer-пакеты (dompdf, PhpWord)]]
- [[pattern-events-over-core-modification|Расширение через события]]
- [[recipe-d7-orm-event-subscription|Подписка модуля на событие D7 ORM]]
- [[pattern-agents-vs-cron|Агенты или cron: выбор способа фонового запуска]]
- [[antipattern-ajax-controller-lowercase-name|Антипаттерн: строчное имя контроллера в AJAX-действии]]
- [[concept-coding-standards|Код-стайл и безопасность]]
- [[concept-testing-approach|Подход к тестированию]]
- [[antipattern-box-core-modification|Антипаттерн: правка ядра вместо событий]]

## Статус покрытия
Есть стандарт разработки и иерархия инвазивности, организация кода и соглашения именования,
события, приёмы исследования платформы, код-стайл/безопасность, тесты и ключевой антипаттерн.
Не хватает: рецепты ORM D7 (запросы, связи), жизненный цикл запроса, ServiceLocator.

[← Обзор вики](../../../index.md)
