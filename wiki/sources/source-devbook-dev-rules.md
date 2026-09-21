---
title: "Конспект: «Книга разработчика», правила разработки и устройство проекта"
type: source-summary
module: core-d7
edition: box
status: verified
provenance: documented
verified: "2026-09-21 / сверено постранично с сайтом книги (bx24devbook), снимок-манифест 2026-09-21"
tags: [разработка, local, php_interface, правила, исследование, git, миграции]
sources: []
related: ["[[concept-change-invasiveness-hierarchy]]", "[[concept-platform-reverse-engineering]]", "[[entity-local-directory]]", "[[entity-php-interface]]", "[[entity-urlrewrite]]", "[[entity-admin-php-console]]", "[[checklist-dev-environment-and-git]]", "[[recipe-migrations-as-code]]", "[[pattern-module-based-development-standard]]", "[[recipe-composer-third-party-libraries]]"]
aliases: []
updated: "2026-09-21"
---

# Конспект: правила разработки и устройство проекта

## Об источнике
| Поле | Значение |
|------|----------|
| Тип | «Книга разработчика Bitrix24» — эталонный источник (`CLAUDE.md` §9); фокус книги — коробка |
| Автор | Андрей Николаев; страница «Свой код» — практика компании «ИТ-Интегратор Фьюжн» |
| Страницы | [С чего начать](https://bx24devbook.website.yandexcloud.net/S_cego_nacat.html) · [Справочник](https://bx24devbook.website.yandexcloud.net/Dokumentacia/Spravocnik.html) · [Сам себе источник](https://bx24devbook.website.yandexcloud.net/Dokumentacia/Sam_sebe_istocnik.html) · [Введение в разработку](https://bx24devbook.website.yandexcloud.net/Razrabotka/Vvedenie.html) · [GIT](https://bx24devbook.website.yandexcloud.net/Razrabotka/GIT.html) · [Структура папки local](https://bx24devbook.website.yandexcloud.net/Razrabotka/Struktura_papki_local/Osnovnoe.html) · [Свой код](https://bx24devbook.website.yandexcloud.net/Razrabotka/Struktura_papki_local/Svoj_kod.html) · [Миграции](https://bx24devbook.website.yandexcloud.net/Razrabotka/Struktura_papki_local/Migracii.html) |
| Дата | на страницах книги дат нет; сверено по снимку 2026-09-21 |
| Где лежит | снимок-манифест (адреса, якоря, хэши страниц, без текста): [`raw/sources/2026-09-21-bx24devbook-manifest.md`](../../raw/sources/2026-09-21-bx24devbook-manifest.md) |

**Как получен.** Заведён 2026-09-18 переносом из архивной вики — тогда первичный источник не
перечитывался, а в «Об источнике» по ошибке стоял курс 43 на `dev.1c-bitrix.ru`. **2026-09-21
сверен постранично с сайтом книги**: тезисы уточнены, добавлены страницы GIT, «Свой код» и
«Миграции», которых в переносе не было. Результат сверки — в разделе «Сверка 2026-09-21» ниже.

## TL;DR
Платформа технически позволяет менять что угодно, поэтому книга даёт явный кодекс: что никогда не
трогаем, в каком порядке выбираем способ доработки, как устроить репозиторий и `/local`, как
переносить изменения между средами. Для Bitrix24 книга предлагает по умолчанию не модули, а
«решение» в `/local/php_interface`; модуль — только для действительно переиспользуемого кода.

## Карта страниц книги
| Страница | О чём | Куда встроено |
|----------|-------|---------------|
| [С чего начать](https://bx24devbook.website.yandexcloud.net/S_cego_nacat.html) | фокус на коробке; сначала освоить продукт; что прочитать до книги; нужный стек (PHP 7.4–8.2, JS/ES6/TypeScript, MySQL, Linux) | этот конспект |
| [Справочник](https://bx24devbook.website.yandexcloud.net/Dokumentacia/Spravocnik.html) | ссылки на официальные курсы (48, 135, 43, 57, 93, 99) и неофициальные ресурсы | этот конспект |
| [Сам себе источник](https://bx24devbook.website.yandexcloud.net/Dokumentacia/Sam_sebe_istocnik.html) | четыре приёма исследования: REST-регистрация, кодовое имя, `grep`, logpoint | [[concept-platform-reverse-engineering]], [[entity-admin-php-console]] |
| [Введение в разработку](https://bx24devbook.website.yandexcloud.net/Razrabotka/Vvedenie.html) | не править прод; думать до кода; пять способов изменения; «никогда» и «часто»; «как правильно» | [[concept-change-invasiveness-hierarchy]], [[antipattern-box-core-modification]] |
| [GIT](https://bx24devbook.website.yandexcloud.net/Razrabotka/GIT.html) | избыточный и целевой `.gitignore` | [[checklist-dev-environment-and-git]] |
| [Структура папки local](https://bx24devbook.website.yandexcloud.net/Razrabotka/Struktura_papki_local/Osnovnoe.html) | стандарт `/local`, приоритет над `/bitrix`, список поддерживаемых каталогов, где место `vendor` | [[entity-local-directory]], [[recipe-composer-third-party-libraries]] |
| [Свой код](https://bx24devbook.website.yandexcloud.net/Razrabotka/Struktura_papki_local/Svoj_kod.html) | структура `/local/php_interface` (classes, console, install, kernel, events, legacy, composer); «решение vs модуль» | [[entity-php-interface]], [[pattern-module-based-development-standard]], [[pattern-events-over-core-modification]] |
| [Миграции](https://bx24devbook.website.yandexcloud.net/Razrabotka/Struktura_papki_local/Migracii.html) | встроенных миграций нет; sprint.migration и migrato; альтернатива — инсталлеры | [[recipe-migrations-as-code]] |

## Ключевые тезисы
- **Не править прод.** Всё, что не контент, делается на копии и переносится автоматически;
  минимум две среды — боевая и тестовая; помогает параметр установки «для разработки».
- **Думать до кода.** Официальной документации по коду нет, обратная совместимость на уровне кода не
  гарантирована. Сначала формулируем запрос в терминах Bitrix24, ищем все точки воздействия на
  сущность (для лида — карточка, список, канбан, REST, обработчики, БП), выбираем самый дешёвый путь.
- **Пять способов изменения** сверху вниз: штатный механизм → легитимное расширение (свой тип UF,
  своё действие БП, обработчик события, подписка на JS-события) → «мягкое» изменение (JS/CSS,
  `result_modifier.php`, `component_epilog.php`) → параллельная механика рядом со штатной → копия
  компонента в `/local` (крайний случай).
- **Восемь «никогда»:** `/bitrix/components/bitrix/*`, `/bitrix/modules/*`,
  `/bitrix/php_interface/*`, `/bitrix/js/*`, `/bitrix/css/*`, ручная переиндексация правил ЧПУ,
  визуальный редактор для правки компонентов, `INSERT`/`UPDATE`/`DELETE` в БД. **Не делать часто:**
  работать без SSH и свежего бэкапа, писать SQL-запросы `SELECT`.
- **Как правильно:** система контроля версий; работа только с публичной частью и `/local`;
  песочница; перенос изменений миграциями или автоматически; учётные данные — в `.env` и в БД.
- **`/local` — стандарт** (с главного модуля 14.0.1). При совпадении имён побеждает `/local`.
  Поддерживаемые каталоги: `activities`, `blocks`, `components`, `gadgets`, `js`, `modules`,
  `php_interface`, `templates`; других не заводить (исключение — `tools` для технических,
  устаревших скриптов).
  `vendor` и `composer.json` — не в корне `/local`, а внутри `php_interface`.
- **Трогать `/bitrix` допустимо** лишь при реализации модуля или когда альтернативы технически нет
  (либо правильное решение несоразмерно дорого).
- **Git:** целевой `.gitignore` минимален — `/bitrix/*`, `/upload`, `/urlrewrite.php`, `*.log`,
  `*.txt`, `.htsecure`. То есть в репозитории — `/local` **и публичная часть** сайта.
- **Структура «решения» в `/local/php_interface`:** `classes/` (свой автозагрузчик: сначала путь
  точно по namespace, затем вариант, где каждый сегмент пути — с заглавной буквы, остальное
  строчными), `console/`, `install/` (инсталлеры),
  короткий `init.php` (только автозагрузчик и подключение файлов), `kernel.php` (`.env` и
  ServiceLocator), `events.php` (только подписки, код обработчиков — в классах), `legacy.php`
  (константы и устаревшие функции), `composer.json` + каталог пакетов.
- **Старые и новые события различаем явно:** `addEventHandler` для событий нового ядра (приходит
  объект `Event`), `addEventHandlerCompatible` для старых (приходят параметры, в том числе по
  ссылке).
- **`.env` — выше `DOCUMENT_ROOT`**, читается в `kernel.php` в окружение приложения. Плюсы: не
  виден из браузера, переносится через VCS. Минус: не попадает в штатный бэкап — доставку и
  восстановление файла надо предусмотреть самим. Альтернатива — опции (таблица `b_option`,
  правка из админки): попадают в бэкап, но плохо переносятся через VCS.
- **Решение vs модуль.** 1С-Битрикс продвигает модули, но это актуально для «Управления сайтом»;
  Bitrix24 — монолит под конкретного клиента, и проект по сути один большой модуль. Модуль —
  только переиспользуемое решение. Признаки «решения, оформленного под модуль»: копирования папки
  модуля на другую установку недостаточно; обновление требует ручных действий.
- **Миграции.** Встроенного механизма нет; перспективны sprint.migration и intervolga.migrato
  (последний больше подходит «Управлению сайтом»). Альтернатива — **инсталлеры**: приводят портал
  к нужному коду состоянию, не трогая то, что создали пользователи; лежат в
  `/local/php_interface/install/` (`setup.php` — точка входа, `steps/` — шаги). Инсталлер работает
  только «вперёд», миграция умеет откат, но дороже в разработке.
- **Исследование платформы:** подписчики `onRestServiceBuildDescription` (из «Командной
  PHP-строки» — это страница админки, а не CLI), кодовое имя сущности (`DEAL`), `grep -ril` /
  `grep -rin` по исходникам модуля, logpoint на `BX.onCustomEvent` в отладчике Firefox.

## Что встроено в вики
- [[concept-change-invasiveness-hierarchy]] — иерархия, «никогда», «часто», «как правильно».
- [[concept-platform-reverse-engineering]], [[entity-admin-php-console]] — приёмы исследования.
- [[entity-local-directory]], [[entity-php-interface]], [[entity-urlrewrite]] — объекты проекта.
- [[pattern-local-solution-structure]] — **новая**: структура «решения» в `/local/php_interface`
  (classes, events.php, kernel.php с `.env`, legacy.php, install/, console/) и выбор «решение или
  модуль».
- [[checklist-dev-environment-and-git]] — `.gitignore` и `.env` по книге (добавлено при сверке).
- [[recipe-migrations-as-code]] — инсталлеры как альтернатива миграциям (добавлено при сверке).
- [[pattern-module-based-development-standard]], [[concept-dev-standards]],
  [[concept-code-namespaces-and-autoloading]] — позиция книги «решение vs модуль» и решение команды.
- [[antipattern-box-core-modification]] — довод о потере права на техподдержку.
- [[recipe-composer-third-party-libraries]] — `vendor` в `php_interface` (сверено 2026-09-21
  отдельно, решение пользователя).

## Сверка 2026-09-21
| Страница вики | Что было | Что в книге | Решение |
|---------------|----------|-------------|---------|
| все страницы кластера | `verified`: «Книга разработчика (dev.1c-bitrix.ru)» | книга живёт на отдельном сайте | атрибуция исправлена, ссылки — на страницы книги |
| [[concept-platform-reverse-engineering]] | подписчиков REST смотреть «из консольного PHP» | «Командная PHP-строка» в админке, это не CLI | исправлено, консоль — как вариант с оговоркой |
| [[entity-local-directory]] | «`/local` — единственная папка, которая целиком идёт в git» | в git — `/local` и публичная часть | исправлено со ссылкой на GIT |
| [[checklist-dev-environment-and-git]] | в git только `/local`; исключение «свои модули в `/bitrix/modules`» | `/bitrix/*` целиком вне git, модули — в `/local/modules`; `.env` выше `DOCUMENT_ROOT` | исправлено, добавлены `.gitignore` и `.env` книги |
| [[pattern-events-over-core-modification]] | `addEventHandler` для `OnAfterCrmDealAdd` с обработчиком `Event $event` | для старых событий — `addEventHandlerCompatible`, аргументы — массив полей | пример исправлен, отмечено как изменение |
| [[recipe-migrations-as-code]] | только sprint.migration | ещё migrato и инсталлеры | добавлено сравнение |
| [[pattern-module-based-development-standard]] | «не модуль» = код в `init.php` | `init.php` минимален; «решение» в `php_interface` | **решение пользователя — совместить**: вопрос «модулем или нет?» первый, модульный стандарт — осознанная практика для переиспользуемого, остальное — [[pattern-local-solution-structure]] |
| [[concept-dev-standards]] | TL;DR «разработка через свои модули» | модуль — только для переиспользуемого | TL;DR уточнён с пометкой |
| [[concept-code-namespaces-and-autoloading]] | в `/local` — `wizards/`, нет `blocks/` | перечень книги: `activities`, `blocks`, `components`, `gadgets`, `js`, `modules`, `php_interface`, `templates` | приведено к книге с пометкой |
| [[entity-php-interface]] | приоритет `/local/` у всего каталога | только у общего `init.php`; `dbconn.php`/`after_connect*` — только в `/bitrix/` | исправлено с пометкой |

## Противоречия с текущими страницами
Разрешены при сверке — см. таблицу выше. Прежний тезис о «противоречии внутри источника» про
`php_interface` уточнён: книга запрещает править `/bitrix/php_interface/*`, а штатные точки
расширения (`init.php`, `dbconn.php` и т. п.) описывает как часть пайплайна страницы; резолюция в
[[concept-change-invasiveness-hierarchy]] остаётся в силе.

## Открытые вопросы
- Где разрешено размещать собственные CSS: в списке каталогов `/local` есть `js`, но нет `css`
  (книга не отвечает).
- Формальный порядок обновления платформы при активной разработке в `/local` (книга не отвечает).
- В книге каталог пакетов Composer назван `vendors/` в дереве и `vendor/` в коде; в вики принят
  `vendor/` ([[recipe-composer-third-party-libraries]]).

[← Конспекты источников](_index-sources.md)
