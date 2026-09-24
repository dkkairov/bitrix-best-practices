---
title: "Ядро D7"
type: index
module: core-d7
edition: box
status: verified
verified: "2026-09-24 / состав папки сверен со списком файлов"
updated: "2026-09-24"
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
- [[concept-d7-orm-entity|Сущность ORM: Table-класс и описание полей]]
- [[concept-d7-orm-query|Выборка ORM: getList, Query и два формата фильтра]]
- [[concept-d7-orm-objects|Объекты и коллекции ORM: EO_-классы вместо массивов]]
- [[concept-d7-orm-relations|Связи ORM: Reference, OneToMany, ManyToMany]]
- [[recipe-d7-orm-crud|Запись через ORM: add, update, delete и проверки данных]]
- [[concept-orm-datamanager-events|События ORM DataManager: девять хуков и формат имени]]
- [[concept-d7-sql-layer|Прямой SQL: соединение, SqlHelper, SqlExpression]]
- [[recipe-d7-transactions|Транзакции D7: начать, зафиксировать, откатить]]
- [[concept-postgresql-compatibility|Код, совместимый с PostgreSQL]]
- [[concept-service-locator|ServiceLocator: регистрация и подмена сервисов]]
- [[concept-validation-d7|Валидация D7: PHP-атрибуты вместо простыней if]]
- [[recipe-d7-custom-validation-rule|Свой валидатор и правило валидации D7]] · черновик
- [[concept-deferred-functions-and-page-areas|Отложенные функции и зоны страницы]] · черновик
- [[concept-code-namespaces-and-autoloading|Пространства имён, автозагрузка, размещение кода в /local/]]
- [[pattern-local-solution-structure|Решение в /local/php_interface: структура клиентского проекта]]
- [[recipe-composer-third-party-libraries|Сторонние Composer-пакеты (dompdf, PhpWord)]]
- [[pattern-events-over-core-modification|Расширение через события]]
- [[recipe-d7-orm-event-subscription|Подписка модуля на событие D7 ORM]]
- [[pattern-agents-vs-cron|Агенты или cron: выбор способа фонового запуска]]
- [[recipe-cli-script-bootstrap|Консольный и cron-скрипт: подключение ядра и завершение]] · черновик
- [[antipattern-ajax-controller-lowercase-name|Антипаттерн: строчное имя контроллера в AJAX-действии]]
- [[concept-coding-standards|Код-стайл и безопасность]]
- [[concept-testing-approach|Подход к тестированию]]
- [[antipattern-box-core-modification|Антипаттерн: правка ядра вместо событий]]

### Классы и объекты ядра (справочник)
- [[entity-main-application|Application]] — соединение, контекст, кэши и завершение хита
- [[entity-loader|Loader]] — подключение модулей
- [[entity-module-manager|ModuleManager]] — регистрация модуля, версии, события установки
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
структура клиентского решения в `/local/php_interface`, события, отложенные функции и зоны
страницы, консольные скрипты, своя валидация, приёмы исследования платформы, код-стайл/безопасность,
тесты и ключевой антипаттерн. Страницы по «Книге разработчика» сверены с сайтом книги 2026-09-21.
ORM разобрана по курсу 43 (2026-09-23) и прогнана на стенде: сущность, запись, выборка,
объекты и коллекции, связи.
Слой БД добавлен по документации фреймворка (2026-09-24): прямой SQL и `SqlHelper`, транзакции,
переносимость на PostgreSQL.
`Loader` и `Config\Option` сверены по справочнику D7 на `dev.1c-bitrix.ru` (2026-09-18).
`ModuleManager` сверен по исходникам ядра 2026-09-23 — черновиков в разделе не осталось.

[← Обзор вики](../../../index.md)
