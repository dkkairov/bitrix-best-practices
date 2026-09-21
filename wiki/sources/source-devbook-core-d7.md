---
title: "Конспект: «Книга разработчика», ядро — пайплайн, события, агенты, локатор, валидация"
type: source-summary
module: core-d7
edition: box
status: verified
provenance: documented
verified: "2026-09-21 / сверено постранично с сайтом книги (bx24devbook), снимок-манифест 2026-09-21"
tags: [d7, пайплайн, события, servicelocator, агенты, валидация, отложенные-функции, urlrewrite]
sources: []
related: ["[[concept-request-lifecycle]]", "[[entity-urlrewrite]]", "[[concept-deferred-functions-and-page-areas]]", "[[recipe-cli-script-bootstrap]]", "[[entity-event-manager]]", "[[entity-main-event]]", "[[pattern-events-over-core-modification]]", "[[entity-cagent]]", "[[pattern-agents-vs-cron]]", "[[concept-service-locator]]", "[[concept-validation-d7]]", "[[recipe-d7-custom-validation-rule]]", "[[entity-validation-service]]", "[[entity-validation-result]]", "[[entity-main-result]]", "[[entity-site-template]]"]
aliases: []
updated: "2026-09-21"
---

# Конспект: ядро — пайплайн, события, агенты, локатор, валидация

## Об источнике
| Поле | Значение |
|------|----------|
| Тип | «Книга разработчика Bitrix24» — эталонный источник (`CLAUDE.md` §9); фокус — коробка |
| Автор | Андрей Николаев |
| Общие сведения | [Обработка uri](https://bx24devbook.website.yandexcloud.net/Obsie_svedenia/Obrabotka_uri.html) · [Ядро продукта](https://bx24devbook.website.yandexcloud.net/Obsie_svedenia/Adro_produkta.html) · [Страница](https://bx24devbook.website.yandexcloud.net/Obsie_svedenia/Stranica.html) · [Шаблон](https://bx24devbook.website.yandexcloud.net/Obsie_svedenia/Sablon.html) |
| Технологии | [Отложенные функции](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Otlozennye_funkcii.html) · [Агенты](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Agenty.html) · [События](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Sobytia.html) · [Локатор служб](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Lokator_sluzb.html) · Валидация: [основное](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Validacia/Osnovnoe.html), [существующие правила](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Validacia/Susestvuusie_pravila.html), [контроллеры](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Validacia/Kontrollery.html), [свои правила](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Validacia/Svoi_pravila.html) |
| Дата | дат нет; косвенно: агенты — около 2021–2022 («релизы 21 и 22»), примеры валидации и UI — 2023 |
| Где лежит | снимок-манифест (без текста): [`raw/sources/2026-09-21-bx24devbook-manifest.md`](../../raw/sources/2026-09-21-bx24devbook-manifest.md) |

**Как получен.** Заведён 2026-09-18 переносом из архивной вики — первичный источник тогда не
перечитывался, в «Об источнике» по ошибке стояли курс 43 и dev.1c-bitrix.ru. **2026-09-21 сверен
постранично с сайтом книги.** Главная находка: тезис о событиях ORM `DataManager` (девять событий, имя
без `Table`, регистр namespace) книге не принадлежит — это эмпирика команды, он перенесён в
[[concept-orm-datamanager-events]] с правильным источником.

## TL;DR
Как устроен запрос от URL до закрытия соединения с БД, где в нём точки расширения, чем события
старого ядра отличаются от нового, как работают агенты и когда вместо них cron, как регистрировать
сервисы и как валидировать данные атрибутами.

## Карта страниц книги
| Страница | О чём | Куда встроено |
|----------|-------|---------------|
| Обработка uri | нет единой точки входа; файл → правила ЧПУ → `404.php`; поля правила; запрет пересоздания правил | [[concept-request-lifecycle]], [[entity-urlrewrite]] |
| Ядро продукта | что считать ядром; почему не править (в т.ч. потеря права на техподдержку); состав `/bitrix/`; `.settings_extra.php` | [[antipattern-box-core-modification]], [[entity-local-directory]], [[entity-php-interface]] |
| Страница | 27 шагов выполнения (номера 26 нет); консольный скрипт и `\CMain::FinalActions()` | [[concept-request-lifecycle]], [[recipe-cli-script-bootstrap]] |
| Шаблон | единый системный шаблон, не править и не копировать; дополнительные элементы через отложенные функции | [[entity-site-template]] |
| Отложенные функции | алгоритм буфера; общие и компонентные функции; ограничения с кэшем; зоны шаблона `bitrix24` | [[concept-deferred-functions-and-page-areas]] |
| Агенты | периодические и непериодические; режимы запуска; ограничения; выбор агент/cron; API; отладка | [[entity-cagent]], [[pattern-agents-vs-cron]] |
| События | регистрация и добавление; старое и новое ядро; свои события; зацикливание и lock-флаги | [[entity-event-manager]], [[entity-main-event]], [[pattern-events-over-core-modification]] |
| Локатор служб | API; три способа регистрации; рекомендации по сервисам | [[concept-service-locator]] |
| Валидация (4 стр.) | правило и валидатор; каталог правил; контроллеры; свои правила | [[concept-validation-d7]], [[entity-validation-service]], [[entity-validation-result]], [[recipe-d7-custom-validation-rule]] |

## Ключевые тезисы
- **Пайплайн** из двух фаз: выбор файла (файл, каталог или симлинк → правила ЧПУ через обработчик
  `/bitrix/urlrewrite.php` → `404.php`) и исполнение страницы. Единой точки входа нет; «маршруты»
  исторически идут через 404.
- `init.php` (шаг 5; из `/local/` или `/bitrix/`) подключается **до** `OnPageStart` (8); `$USER`
  появляется на шаге 9. `OnBeforeProlog` (11) — пользователь и шаблон уже известны, права ещё не
  проверены; `OnProlog` (14) — после проверки прав и старта буфера.
- **Буферизация — с шага 13 по 23**; на ней стоят отложенные функции, которые выполняются в служебной
  части эпилога.
- **Консольный скрипт** подключает `prolog_before.php` (служебный пролог работает), пользователь —
  гость, нет сессии и `$_SERVER` веб-запроса; `set_time_limit`/`ignore_user_abort` — после пролога;
  в конце обязателен `\CMain::FinalActions()`.
- **Агенты:** с main 20.5.0 — фоновые работы после отдачи страницы; три режима запуска (хиты, cron,
  комбинированный — по умолчанию в Bitrix Env); периодические догоняют пропуски, непериодические —
  нет; однопоточность с блокировкой на 10 минут; нет `$USER`/`SITE_ID`; тяжёлое (5+ с) и точное
  время — cron; по умолчанию книга советует агенты, автор лично — cron.
- **События двух поколений:** старое ядро — аргументы по порядку, часто по ссылке →
  `addEventHandlerCompatible` / `registerEventHandlerCompatible`; новое — один `\Bitrix\Main\Event` →
  `addEventHandler` / `registerEventHandler`. Подписки держать в `events.php`, код — в классах. Поля в
  событии — не вся сущность. Своё событие: осмысленные имена, обработка всех результатов, объекты в
  параметрах. «Ленивые параметры» в книге — заглушка без API.
- **ServiceLocator** — простой DI-контейнер: `has`/`get`/`addInstance`/`addInstanceLazy`; три
  способа регистрации (правку `/bitrix/.settings.php` книга настоятельно не рекомендует); сервис без
  состояния (кроме кэша), без исключений при регистрации, без донастройки на месте.
- **Валидация** — атрибуты на свойствах DTO, только объекты; правило (атрибут) и валидатор
  (проверка значения); сервис `main.validation.service`; рефлексия — `private` проверяется,
  неустановленное nullable-свойство пропускается; каталог из 16 правил; валидация параметров
  контроллера и DTO через `ValidationParameter`; свои валидаторы и правила через абстрактные классы.

## Что встроено в вики
- Концепты: [[concept-request-lifecycle]], [[concept-service-locator]], [[concept-validation-d7]],
  **новый** [[concept-deferred-functions-and-page-areas]].
- Паттерны: [[pattern-agents-vs-cron]], [[pattern-events-over-core-modification]].
- Рецепты (новые): [[recipe-cli-script-bootstrap]], [[recipe-d7-custom-validation-rule]].
- Карточки классов: [[entity-event-manager]], [[entity-main-event]], [[entity-main-result]],
  [[entity-cagent]], [[entity-validation-service]], [[entity-validation-result]], [[entity-urlrewrite]].
- Шаблон и зоны: [[entity-site-template]].

## Сверка 2026-09-21
| Страница вики | Что было | Что в книге | Решение |
|---------------|----------|-------------|---------|
| этот конспект, [[concept-orm-datamanager-events]] | тезис про девять событий ORM и формат имени «из книги» | в книге событий ORM нет | тезис снят, источник страницы — эмпирика |
| [[entity-urlrewrite]] | правила в `/bitrix/urlrewrite.php`, «штатно правится» | `/bitrix/urlrewrite.php` — обработчик (ядро); правила — корневой `urlrewrite.php`, вне git | исправлено с пометкой |
| [[pattern-agents-vs-cron]] | «точность до минуты → агент»; «потолок по длительности»; база — cron | точное время → cron, интервал < 1 мин → агент; потолка нет; по умолчанию — агенты | правило исправлено; база «cron» — осознанная практика команды |
| [[entity-cagent]] | «`RemoveModuleAgents` не вызывает `Delete`» | это сказано про `RemoveAgent` | исправлено; добавлены периодичность, режимы, отладка |
| [[concept-request-lifecycle]] | «консоль — не этот пайплайн»; шаг 26 | служебный пролог в CLI выполняется; в книге шаг 27 | исправлено |
| [[entity-event-manager]], [[pattern-events-over-core-modification]] | `OnAfterCrmDealAdd` через `addEventHandler`, обработчик `Event $event` | старое ядро → `…Compatible`, `array &$fields` | исправлено |
| [[entity-site-template]] | тулбар «занимает» `above_pagetitle`/`below_pagetitle` | тулбар — между ними; конфликт с `pagetitle*` | исправлено |
| [[concept-service-locator]] | нижний регистр «обязательно»; конфликт с «приложениями» Маркетплейса | книга противоречит себе (camelCase / нижний регистр); конфликт — с **модулями** | выбор команды записан явно; формулировка исправлена |
| [[concept-validation-d7]], [[entity-validation-result]] | результат — `\Bitrix\Main\Result`; код ошибки числом | класс результата книга не называет; код — имя/путь свойства | уточнено, помечено «сверить по ядру» |
| [[concept-bitrix-framework-vs-bitrix24]] | интерфейс портала «почти не строится» на компонентах (React) | портал — компоненты в системном шаблоне | исправлено с пометкой |

## Противоречия внутри книги (не переносить молча)
- Регистр имени сервиса: camelCase в рекомендациях локатора против «только нижний регистр» в
  шаблоне `kernel.php`.
- В таблице шагов страницы после 25 идёт 27.
- `dbconn.php`: в «Ядре продукта» — параметры соединения с БД, в «Странице» — константы (переменные
  БД — «ранее»).
- `AddBufferContent` назван то статическим, то нестатическим; описания `ShowPanel`/`ShowCSS`/
  `ShowProperty` скопированы с `ShowTitle`.
- Тексты сообщений валидации для одной ошибки в разных примерах различаются.

## Открытые вопросы
- Как пайплайн меняется при включённом композитном кэше.
- API «ленивых параметров» события (в книге раздел-заглушка).
- С какой версии `main` доступна валидация; точный класс результата `ValidationService::validate()`.
- Работает ли отладка агентов через `BX_AGENTS_LOG_FUNCTION` на актуальных версиях (книга пишет о
  поломке в релизах 21–22).

[← Конспекты источников](_index-sources.md)
