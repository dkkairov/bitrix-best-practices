---
title: "Конспект: «Книга разработчика», модуль CRM — словари, Universal API, сущности, смарт-процессы, дела"
type: source-summary
module: crm
edition: box
status: verified
provenance: documented
verified: "2026-09-21 / сверено постранично с сайтом книги (bx24devbook), снимок-манифест 2026-09-21"
tags: [crm, universal-api, справочники, мультиполя, фабрика, лид, сделка, смарт-процесс, дела, конвертация]
sources: []
related: ["[[concept-crm-universal-api]]", "[[concept-crm-dictionaries]]", "[[pattern-crm-action-vs-event]]", "[[entity-crm-container]]", "[[entity-crm-factory]]", "[[entity-crm-item]]", "[[entity-crm-operation]]", "[[entity-crm-settings]]", "[[entity-ccrm-status]]", "[[entity-ccrm-owner-type]]", "[[entity-ccrm-field-multi]]", "[[entity-crm-legacy-events]]", "[[recipe-crm-legacy-entity-crud]]", "[[recipe-crm-lead-conversion]]", "[[recipe-crm-todo-activity]]", "[[recipe-smart-process-factory-customization]]", "[[recipe-smart-process-programmatic-creation]]", "[[entity-smart-process]]"]
aliases: []
updated: "2026-09-21"
---

# Конспект: модуль CRM

## Об источнике
| Поле | Значение |
|------|----------|
| Тип | «Книга разработчика Bitrix24» — эталонный источник (`CLAUDE.md` §9); фокус — коробка. Не официальная документация и не REST |
| Автор | Андрей Николаев |
| Общее и словари | [О модуле](https://bx24devbook.website.yandexcloud.net/Modul_CRM/O_module.html) · [Справочники](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Slovari/Spravocniki.html) · [Типы данных](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Slovari/Tipy_dannyh.html) · [Структуры данных](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Slovari/Struktury_dannyh.html) |
| Универсальное API | [Концепция](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Koncepcia.html) · [Как включить](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Kak_vklucit.html) · [Контейнер](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Kontejner.html) · [Фабрики](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Fabriki.html) · [Элементы](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Elementy.html) · [Операции](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Operacii.html) · Кастомизация: [как работает](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Kastomizacia/Kak_rabotaet.html), [подмена фабрики](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Kastomizacia/Podmena_fabriki.html), [добавление действий](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Kastomizacia/Dobavlenie_dejstvij.html) |
| Сущности | [Лид](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Lid/Opisanie.html) (+ методы, события, примеры, [конвертация](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Lid/Konvertacia.html)) · [Контакт](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Kontakt/Opisanie.html) · [Компания](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Kompania/Opisanie.html) · [Сделка](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Sdelka/Opisanie.html) — у каждой описание, методы, события, примеры |
| Прочее | [Смарт-процессы](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Smart_processy/Opisanie.html) (процессы, элементы, операции, изменение логики) · [Счёт](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Scet.html) · [Заказ](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Zakaz/index.html) · [Предложение](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Predlozenie.html) · [Дело](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Delo/index.html) (общее API, универсальное дело) |
| Дата | дат нет; примеры — 2021–2023, конвертация — «до версии 20», смарт-процессы — с crm 20.700.0 |
| Где лежит | снимок-манифест (без текста): [`raw/sources/2026-09-21-bx24devbook-manifest.md`](../../raw/sources/2026-09-21-bx24devbook-manifest.md) |

**Как получен.** Заведён 2026-09-18 переносом из архивной вики — первичный источник тогда не
перечитывался, в «Об источнике» по ошибке стояли apidocs.bitrix24.ru и dev.1c-bitrix.ru, а охват был
только «словари + Universal API». **2026-09-21 сверен постранично с сайтом книги** (все 40 страниц
раздела), добавлены главы о сущностях, конвертации, смарт-процессах, счёте, КП и делах.

## TL;DR
CRM в коробке живёт в двух поколениях API. Старое `CCrm*` — всё ещё основной путь записи для лида,
контакта, компании и сделки, если для них не включён Universal API; события старого ядра — его точка
расширения. Universal API (`Container` → `Factory` → `Item` + `Operation`) — единый интерфейс для
смарт-процессов, счетов, КП и (по настройке) основных сущностей; расширяется действиями операций через
подмену фабрики. Под обоими — общий словарный слой (`b_crm_status`, мультиполя).

## Карта страниц книги
| Раздел | О чём | Куда встроено |
|--------|-------|---------------|
| О модуле, словари | справочники `b_crm_status` (читать `GetStatus*`, писать только `CCrmStatus`), типы данных, `CCrmOwnerType`, мультиполя | [[concept-crm-dictionaries]], [[entity-ccrm-status]], [[entity-ccrm-owner-type]], [[entity-ccrm-field-multi]] |
| Универсальное API | цели и паттерны; где включён; фабрики, элементы, 15 шагов операции, конфигурация; кастомизация | [[concept-crm-universal-api]], [[entity-crm-container]], [[entity-crm-factory]], [[entity-crm-item]], [[entity-crm-operation]], [[entity-crm-settings]], [[pattern-crm-action-vs-event]] |
| Лид, контакт, компания, сделка | поля и устаревшие поля; `GetListEx` против `*Table`; `Add/Update/Delete` и опции; события; поиск по телефону через дубли | [[recipe-crm-legacy-entity-crud]], [[entity-crm-legacy-events]], [[entity-ccrm-field-multi]] |
| Конвертация лида | что во что конвертируется; схемы `b_crm_conv_map`; синхронизация UF; простой и явный путь | [[recipe-crm-lead-conversion]] |
| Смарт-процессы | хранение; тип через `TypeTable`; элементы; операции; изменение логики (поле «только чтение», журнал удаления, запрет стадии) | [[entity-smart-process]], [[recipe-smart-process-programmatic-creation]], [[recipe-smart-process-factory-customization]] |
| Счёт, заказ, предложение | счёт — смарт-процесс с фиксированными настройками (31, `SMART_INVOICE`); раздельный режим заказов упразднён; КП — фабрика не на механике СПА | [[concept-crm-universal-api]], [[entity-crm-factory]], [[entity-ccrm-owner-type]] |
| Дело | три группы дел; права через родителя; `CCrmActivity::Delete` и события; ToDo | [[recipe-crm-todo-activity]], [[pattern-crm-timeline-client-side]] |

## Ключевые тезисы
- **Universal API** создан ради меньшего дублирования, меньшей связности и тестируемости; плата —
  сложность изучения. Всегда включён для смарт-процессов, счетов, КП и документов подписи; для лида,
  сделки, контакта, компании — по настройке (`<Type>Settings::…FactoryEnabled`, `?enableFactory=Y`),
  переключить может любой пользователь с доступом в CRM. На момент написания UA для них —
  экспериментальный, позже обещано включить принудительно и убрать старое поведение.
- **Операция — 15 шагов**; `disableAllChecks()` выключает только 4 проверки (БП, права, поля,
  обязательные UF), а БП, роботы, история и действия продолжают работать. Операцию берут только из
  фабрики. Действие (`Operation\Action::process(Item): Result`) может изменить элемент или прервать
  операцию.
- **Подмена:** контейнер (`crm.service.container`) или только фабрика смарт-процесса
  (`crm.service.factory.dynamic.<id>`, регистрировать до первого `getFactory()`); некоторые модули
  Маркетплейса сами подменяют контейнер. Правила: наследоваться, точечно переопределять, не добавлять
  новых методов.
- **Против событий** книга приводит три довода: непредсказуемый состав `$arFields` (изменённые или
  затронутые поля), нет значений «до», повторные запросы у независимых обработчиков; ловушка общего
  `static` при вложенном изменении другой сущности — «очень редкая».
- **Старое API сущностей:** чтение — `GetListEx` (права по умолчанию) или `*Table::getList` (быстрее,
  без прав, иной набор полей); запись — только `CCrm*::Add/Update/Delete` с опциями; события
  подписываются через `…Compatible`, отменить можно только в `OnBefore…`.
- **Справочники** — одна таблица `b_crm_status`; ссылаться на `STATUS_ID`, а не на ID (ID на порталах
  разные); писать только через `CCrmStatus` (кэш); `BulkCreate` ошибок не сообщает.
- **Мультиполя** — одна таблица `b_crm_field_multi`, `SetFields` (пустое значение = удаление);
  искать элемент **по телефону** — через индекс дубликатов, а не `GetListEx`.
- **Элемент:** `set*` не сохраняет; `save()`/`delete()` у `Item` существуют, но обходят обязательные
  шаги; `setProductRows()` удаляет отсутствующие позиции, `updateProductRow()` сбрасывает непереданные
  поля.
- **Смарт-процессы** — с crm 20.700.0; тип создаётся и редактируется одним путём (`set()` + `save()`);
  счёт — фиксированный тип 31, для него `isPossibleDynamicTypeId()` = `false`.
- **Дела:** дело бывает в таблице дел, в таймлайне или в обоих местах; своих прав нет — права
  родителя; ToDo — основной тип, заменил звонок и встречу; API поиска дел нет.

## Что встроено в вики
- Концепты: [[concept-crm-universal-api]], [[concept-crm-dictionaries]]. Решение:
  [[pattern-crm-action-vs-event]].
- Карточки: [[entity-crm-container]], [[entity-crm-factory]], [[entity-crm-item]],
  [[entity-crm-operation]], [[entity-crm-settings]], [[entity-ccrm-status]],
  [[entity-ccrm-owner-type]], [[entity-ccrm-field-multi]], **новая** [[entity-crm-legacy-events]].
- Рецепты (новые): [[recipe-crm-legacy-entity-crud]], [[recipe-crm-lead-conversion]],
  [[recipe-crm-todo-activity]], [[recipe-smart-process-factory-customization]].
- Дополнены: [[recipe-smart-process-programmatic-creation]], [[entity-smart-process]],
  [[recipe-crm-history-all-fields]], [[recipe-crm-hide-card-block-js]],
  [[pattern-crm-timeline-client-side]], [[checklist-crm-launch]], [[pattern-crm-sales-funnel-design]],
  [[pattern-smart-process-vs-deal-fields]], [[checklist-data-migration]],
  [[concept-bitrix-naming-conventions]].

## Сверка 2026-09-21
| Страница вики | Что было | Что в книге | Решение |
|---------------|----------|-------------|---------|
| все страницы кластера | `verified`: apidocs.bitrix24.ru | PHP-API описан в книге; apidocs — это REST | атрибуция исправлена |
| [[entity-crm-operation]] | `disableAllChecks()` — «массовый выключатель» | только 4 проверки; БП и роботы работают | исправлено; для массовых правок — `disableBizProc()`/`disableAutomation()` |
| [[recipe-smart-process-programmatic-creation]] | «смена `IS_*` требует удаления типа» | создание и правка типа — один путь `save()` | исправлено; имя UF строится от ID типа |
| [[entity-ccrm-field-multi]] | поиск и нормализация номеров — «ваша ответственность»; `ENABLE_NOTIFICATION` в `SetFields` | поиск по телефону — индекс дубликатов; `SetFields` опции игнорирует | исправлено |
| [[entity-crm-item]] | «писать можно только операцией» | `save()`/`delete()` есть, но обходят шаги | уточнено |
| [[pattern-crm-action-vs-event]] | `addEventHandler`; отмена «false / исключение» | `…Compatible`; отмена только в `OnBefore…` через `false` + `RESULT_MESSAGE` / `ThrowException` | исправлено |
| [[concept-crm-universal-api]], [[entity-crm-container]], [[concept-service-locator]] | контейнер конфликтует с «приложениями» Маркетплейса | книга допускает подмену контейнера; конфликт — с **модулями** | формулировка исправлена; подмена одной фабрики — практика команды |
| [[concept-crm-dictionaries]], [[entity-ccrm-status]] | `StatusTable` — «для чтения»; свой справочник модуля с семантикой | значения — `GetStatus*`, `StatusTable` — связи; о своём справочнике книга молчит | исправлено |
| [[entity-crm-settings]] | «у СПА и счетов нет Settings-класса» | книга говорит только, что UA там поддержан полностью | утверждение снято |
| [[recipe-crm-hide-card-block-js]] | `/crm/type/<id>/` = смарт-процесс | КП открывается по `/crm/type/7/…` | добавлено предупреждение |

**Осознанные практики команды** (эмпирика, crm 26.800): подмена одной фабрики вместо контейнера;
контекст операции вместо контекста контейнера при REST; третий аргумент `addAction` (сортировка);
вспомогательный метод `isTrackable()` в подменённой фабрике ([[recipe-crm-history-all-fields]]).

## Противоречия внутри книги
- Страница «Контейнер» дублирует «Концепцию» — отдельного описания API контейнера нет.
- Разные списки сущностей на UA в «Концепции» (3) и «Как включить» (4 + документы).
- Действие с `getItemBeforeSave()` объясняется как выполняемое после сохранения, но в «Подмене
  фабрики» регистрируется на `ACTION_BEFORE_SAVE`.
- Стадии в примерах — `D150_3:…` без «T»; по самой книге формат `DT<id>_<cat>:…`.
- В примерах событий подписка не на то событие (`OnAfterCrmLeadDelete` → `…Update`,
  `OnBeforeCrmDealAdd` → `OnBeforeCrmCompanyAdd`); `OnAfterExternalCrmLeadAdd` назван аналогом
  `OnBefore…`.
- Класс поиска дублей для лида и компании в примерах разный (`ContactDuplicateChecker`, искажённое
  имя у компании).

## Открытые вопросы
- Полный список сервисов `Container::getXxx()` и содержимое `Service\Context`.
- API `Operation\Conversion` (книга только перечисляет операцию; рабочий путь — старый механизм).
- Срабатывают ли старые события при сохранении через операции, когда UA для сущности включён.
- API поиска дел; работает ли ToDo для смарт-процессов.

[← Конспекты источников](_index-sources.md)
