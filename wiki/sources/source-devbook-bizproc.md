---
title: "Конспект: «Книга разработчика», модуль бизнес-процессов"
type: source-summary
module: bizproc
edition: box
status: verified
provenance: documented
verified: "2026-09-21 / сверено постранично с сайтом книги (bx24devbook), снимок-манифест 2026-09-21"
tags: [bizproc, активити, условия, окружение, типы, php-код]
sources: []
related: ["[[concept-bizproc-engine]]", "[[entity-cbp-activity]]", "[[entity-cbp-activity-condition]]", "[[entity-bizproc-field-type]]", "[[entity-bizproc-globals-manager]]", "[[entity-bizproc-activity-description]]", "[[antipattern-bizproc-php-code-activity]]", "[[recipe-bizproc-custom-task-activity]]", "[[entity-cbp-task-service]]"]
aliases: []
updated: "2026-09-21"
---

# Конспект: модуль бизнес-процессов

## Об источнике
| Поле | Значение |
|------|----------|
| Тип | «Книга разработчика Bitrix24» — эталонный источник (`CLAUDE.md` §9); авторская книга, не официальная документация. Курс 57 на dev.1c-bitrix.ru книга даёт только в «Полезных ссылках» |
| Автор | Андрей Николаев |
| Страницы | [О модуле](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/O_module.html) · [Действия](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Dejstvia.html) · [Работа с окружением](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Okruzenie.html) · [Действие: PHP код](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/PHP_kod.html) · [Свои действия](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Svoi_dejstvia.html) · [Свои условия](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Svoi_uslovia.html) |
| Дата | в тексте глав дат нет |
| Где лежит | снимок-манифест (без текста): [`raw/sources/2026-09-21-bx24devbook-manifest.md`](../../raw/sources/2026-09-21-bx24devbook-manifest.md) |

**Как получен.** Заведён 2026-09-18 переносом из архивной вики: источником тогда был указан курс 57
dev.1c-bitrix.ru как «официальная документация», первичный текст не перечитывался. **2026-09-21
сверен постранично с сайтом книги.**

## TL;DR
При запуске шаблон копируется в экземпляр процесса, и дальше процесс идёт по копии. Единица
исполнения — действие: каталог с паспортом `.description.php` и классом, который движок ищет в пяти
каталогах по приоритету. Одно и то же действие бывает шагом дизайнера, роботом или условием; по
поведению — немедленным, событийным или заданием. `BaseActivity` даёт D7-удобства, окружение
процесса читается через парсер, геттер и сервисы движка. Действие «PHP код» книга не советует брать в
релизы — нужное делают своим действием.

## Карта страниц книги
| Страница | О чём | Куда встроено |
|----------|-------|---------------|
| О модуле | термины (процесс, шаблон, экземпляр), изоляция экземпляра, какие объекты поддерживают БП, модуль «Дизайнер БП», явный `includeModule('bizproc')` | [[concept-bizproc-engine]] |
| Действия | штатные базовые классы, классификация, каталоги поиска, `.description.php` (`CATEGORY`, `ROBOT_SETTINGS`, `FILTER`, `RETURN`, `ADDITIONAL_RESULT`), файл класса, `properties_dialog.php`, `robot_properties_dialog.php` | [[entity-bizproc-activity-description]], [[entity-cbp-activity]], [[concept-bizproc-engine]], [[entity-bizproc-field-type]] |
| Работа с окружением | парсер, геттер `getRuntimeProperty`, параметры, переменные, константы, документ, глобальные хранилища, дополнительные значения | [[entity-cbp-activity]], [[entity-bizproc-globals-manager]] |
| Действие: PHP код | недостатки действия, правила, журнал и типы сообщений, шаблон кода, автоподстановка и парсинг | [[antipattern-bizproc-php-code-activity]], [[entity-cbp-activity]] |
| Свои действия | пример `helloworldactivity`, `BaseActivity`, подключение модулей, возврат ошибок, `checkProperties`, своя отрисовка, поля диалога | [[entity-cbp-activity]], [[entity-bizproc-field-type]], [[entity-bizproc-activity-description]] |
| Свои условия | каталоги, паспорт условия, диалог, класс `CBPActivityCondition` | [[entity-cbp-activity-condition]], [[entity-bizproc-activity-description]] |

## Ключевые тезисы
- **Изоляция:** при запуске шаблон копируется в экземпляр; правка шаблона не трогает запущенные
  процессы. БП работает только на объектах, которые поддерживают эту механику: задачи — частично,
  старые счета и события календаря — нет. Модуль «Дизайнер бизнес-процессов» — только визуальный
  редактор. `bizproc` подключать явно через `Loader::includeModule`.
- **Типы и поведение:** `activity`, `robot_activity`, `condition`. Действие по умолчанию немедленное;
  событийное реализует `IBPEventActivity` и `IBPActivityExternalEventListener`; задание — событийное
  действие с дополнительными методами, наследующее `CBPCompositeActivity`.
- **Поиск:** `/local/activities` → `/local/activities/custom` → `BX_ROOT/activities/custom` →
  `BX_ROOT/activities/bitrix` → `BX_ROOT/modules/bizproc/activities`, до первого найденного
  каталога. Имя каталога — имя класса без `CBP`, строчными.
- **Паспорт `.description.php`:** раздел дизайнера — `['ID' => 'other']` или свой через
  `OWN_ID`/`OWN_NAME`; место робота — `ROBOT_SETTINGS` (14 групп); видимость по типам документов —
  `FILTER` (`INCLUDE`/`EXCLUDE`); результаты — `RETURN`; динамические результаты — `ADDITIONAL_RESULT`
  со свойствами-картами (образец — `FieldsMap` у `CBPGetListsDocumentActivity`).
- **`BaseActivity`:** `internalExecute(): ErrorCollection`, `$requiredModules`, обязательный
  `getFileName()`, форма через `getPropertiesDialogMap()`, `checkProperties()` — проверки во время
  выполнения, результат — `$preparedProperties` (ключ из `RETURN`, тип — `SetPropertiesTypes()`).
  `properties_dialog.php` нужен только для своей отрисовки (`renderFieldControl`).
- **Окружение:** `parseValue()`; геттер `getRuntimeProperty()` с источниками `SourceType::*`; параметры
  — через `getRootActivity()`; переменные — `getVariable`/`setVariable`; константы — только чтение;
  документ — `DocumentService` (кэширует, грузит лениво, права не проверяет); глобальные переменные и
  константы — `GlobalVar`/`GlobalConst`, привязаны к типу документа, видимость `GLOBAL`, модуль или
  сущность, «глобальные константы» изменяемы.
- **Журнал:** `WriteToTrackingService($message, $modifiedBy, $trackingType)`; в журнале видны четыре
  типа — `Error`, `Report`, `Custom`, `FaultActivity`; текст сообщения тоже проходит подстановку.
- **«PHP код»:** не для релизов — ошибка останавливает процесс, шаблон может править только
  администратор. Если используется: минимум кода, `try/catch`, журнал, значения — через
  `ParseValue()` с разрезанной строкой, без `$USER` и `SITE_ID` (код может выполняться на cron).
- **Условия:** `CBPActivityCondition`, конструктор принимает массив настроек, `Evaluate($ownerActivity)`;
  штатной записи в журнал нет (книга даёт полифил), окружение — через `$ownerActivity->workflow`.

## Что встроено в вики
- Новые страницы: [[entity-bizproc-activity-description]] (черновик — из-за противоречия книги про
  `RETURN`/`ADDITIONAL_RESULT`), [[antipattern-bizproc-php-code-activity]].
- Обновлены: [[concept-bizproc-engine]], [[entity-cbp-activity]], [[entity-cbp-activity-condition]],
  [[entity-bizproc-field-type]], [[entity-bizproc-globals-manager]], [[entity-cbp-task-service]],
  [[recipe-bizproc-custom-task-activity]].
- Эмпирическая часть — форма задания, `CBPTaskService`, коды разделов дизайнера, ловушки вёрстки —
  остаётся в [[recipe-bizproc-custom-task-activity]] и [[entity-cbp-task-service]]: книга её не
  покрывает.

## Сверка 2026-09-21
| Страница вики | Что было | Что в книге | Решение |
|---------------|----------|-------------|---------|
| все страницы кластера | `verified`: dev.1c-bitrix.ru, курс 57, «официальная документация» | материал — из книги, курс 57 — только ссылка | атрибуция исправлена |
| [[entity-cbp-activity-condition]], прежний конспект | у условия нет `$this->workflow`, журнала, переменных — как факт книги | книга прямо говорит только про журнал; окружение — через `$ownerActivity->workflow` | уточнено, остальное помечено как вывод команды |
| [[entity-cbp-activity-condition]] | диалог условия — как у действия | `GetPropertiesDialog` без `$activityName`, с `$popupWindow`; `GetPropertiesDialogValues` — 7 параметров, возвращает значения | исправлено |
| [[entity-cbp-activity]] | `checkProperties()` — валидация формы; прерывание через `throw` как норма | проверки во время выполнения; штатно — статус `Faulting` или `ErrorCollection` | исправлено; `throw` помечен как эмпирика команды |
| [[entity-bizproc-field-type]] | тип в `RETURN` должен совпадать с `getPropertiesDialogMap()` | тип результата регистрируется через `SetPropertiesTypes()` | исправлено |
| [[entity-bizproc-globals-manager]] | перенос шаблонов через глобалы и поиск глобала по имени — как факты | в книге нет | помечено как практика команды, «проверить перенос» |
| [[concept-bizproc-engine]] | «или поставляем модулем» — без уточнения, куда; `properties_dialog.php` обязателен | каталога модуля среди каталогов поиска нет; при `getPropertiesDialogMap()` файл не нужен | уточнено: модуль копирует действие в `/local/activities/custom/` |
| [[recipe-bizproc-custom-task-activity]], [[entity-cbp-task-service]] | задание наследует `CBPActivity` | задание наследует `CBPCompositeActivity` | **осознанная практика команды** (работает на стенде); открытый вопрос — сверить со штатными заданиями |
| [[recipe-bizproc-custom-task-activity]] | `ADDITIONAL_RESULT` дублирует ключи `RETURN`, «указывать оба безопасно» | `ADDITIONAL_RESULT` — свойства-карты динамических результатов | помечено; до проверки — `RETURN` + `SetPropertiesTypes()` |
| [[recipe-bizproc-custom-task-activity]] | таблица кодов `CATEGORY` | только `other` и свой раздел | помечено как наблюдения команды |
| — | действие «PHP код» не разобрано | не брать в релизы, правила для вынужденного случая | новый антипаттерн |

## Противоречия внутри книги
- **`RETURN` и `ADDITIONAL_RESULT`.** Заметка в «Действиях» говорит, что результаты без
  `ADDITIONAL_RESULT` другим действиям недоступны (и называет ключ `RESULT`), а пример
  `helloworldactivity` обходится одним `RETURN` и утверждает обратное; обещанного во вступлении
  `ADDITIONAL_RESULT` в его коде нет. Разбор — [[entity-bizproc-activity-description]].
- **Опечатки:** каталог `activites` в деревьях файлов (правильно `activities`), `activiy` и `acitivty`
  в тексте; в «Своих действиях» обрывается фраза про `CATEGORY`.

## Открытые вопросы
- Базовый класс штатных заданий дистрибутива: `CBPActivity` или `CBPCompositeActivity`.
- Виден ли результат из одного `RETURN` во «Вставке значения» — в дизайнере и в роботах.
- Прерывают ли ошибки из `ErrorCollection` в `BaseActivity` сам процесс.
- Полный набор `CBPActivityExecutionStatus::*` и `CBPTrackingType::*`.
- Где лежат системные классы условий для штатных блоков.
- Совпадает ли набор типов глобальных переменных с `FieldType`.

[← Конспекты источников](_index-sources.md)
