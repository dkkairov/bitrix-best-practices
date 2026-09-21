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
- [[concept-request-lifecycle|Жизненный цикл HTTP-запроса: где именно вмешиваться]]
- [[concept-orm-datamanager-events|События ORM DataManager: девять хуков и формат имени]]
- [[concept-service-locator|ServiceLocator: регистрация и подмена сервисов]]
- [[concept-validation-d7|Валидация D7: PHP-атрибуты вместо простыней if]]
- [[concept-code-namespaces-and-autoloading|Пространства имён, автозагрузка, размещение кода в /local/]]
- [[pattern-local-solution-structure|Решение в /local/php_interface: структура клиентского проекта]]
- [[recipe-composer-third-party-libraries|Сторонние Composer-пакеты (dompdf, PhpWord)]]
- [[pattern-events-over-core-modification|Расширение через события]]
- [[recipe-d7-orm-event-subscription|Подписка модуля на событие D7 ORM]]
- [[pattern-agents-vs-cron|Агенты или cron: выбор способа фонового запуска]]
- [[antipattern-ajax-controller-lowercase-name|Антипаттерн: строчное имя контроллера в AJAX-действии]]
- [[concept-coding-standards|Код-стайл и безопасность]]
- [[concept-testing-approach|Подход к тестированию]]
- [[antipattern-box-core-modification|Антипаттерн: правка ядра вместо событий]]

### Классы и объекты ядра (справочник)
- [[entity-loader|Loader]] — подключение модулей
- [[entity-module-manager|ModuleManager]] — регистрация модуля · черновик
- [[entity-config-option|Config\Option]] — настройки модулей
- [[entity-event-manager|EventManager]] — подписка на события
- [[entity-main-event|Event и EventResult]] — объект события и результат
- [[entity-main-result|Result и Error]] — возврат результата операции
- [[entity-cagent|CAgent]] — агенты
- [[entity-validation-service|ValidationService]] — исполнитель валидации
- [[entity-validation-result|ValidationResult и ValidationError]] — ошибки валидации
- [[entity-local-directory|Каталог /local/]] — где живут доработки
- [[entity-php-interface|Каталог php_interface]] — init.php, dbconn.php и соседи
- [[entity-urlrewrite|urlrewrite.php]] — правила обработки адресов
- [[entity-admin-php-console|Командная PHP-строка]] — диагностика из админки

## Статус покрытия
Есть стандарт разработки и иерархия инвазивности, организация кода и соглашения именования,
события, приёмы исследования платформы, код-стайл/безопасность, тесты и ключевой антипаттерн.
Не хватает: рецепты ORM D7 (запросы, связи), отложенные функции и зоны страницы.
`Loader` и `Config\Option` сверены по справочнику D7 на `dev.1c-bitrix.ru` (2026-09-18).
`ModuleManager` остаётся `draft`: страница справочника не отдала содержимое, а поиск выводит
на функции старого ядра.

[← Обзор вики](../../../index.md)
