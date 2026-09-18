---
title: "Конспект: «Книга разработчика», модуль бизнес-процессов"
type: source-summary
module: bizproc
edition: box
status: verified
provenance: documented
verified: "2026-09-18 / перенос из архивной вики; первичный источник — dev.1c-bitrix.ru"
tags: [bizproc, активити, условия, окружение, типы]
sources: []
related: ["[[concept-bizproc-engine]]", "[[entity-cbp-activity]]", "[[entity-cbp-activity-condition]]", "[[entity-bizproc-field-type]]", "[[entity-bizproc-globals-manager]]"]
aliases: []
updated: "2026-09-18"
---

# Конспект: модуль бизнес-процессов

## Об источнике
| Поле | Значение |
|------|----------|
| Тип | официальная документация / «Книга разработчика Bitrix24», курс 57 |
| Разделы | о модуле, действия, окружение действия, PHP-код, свои действия, свои условия |
| URL | https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=57 |
| Raw | снимки глав не копировались (см. [[source-devbook-dev-rules]]) |

**Как получен:** перенос из архивной вики 2026-09-18, первичный источник при переносе не
перечитывался.

## TL;DR
Шаблон, собранный в дизайнере, при запуске копируется в экземпляр, и дальше процесс идёт по копии.
Единица исполнения — активити; робот — тот же класс с другим значением `TYPE`. Свои действия и
условия — штатная точка расширения BPM без правки ядра.

## Ключевые тезисы
- Принцип изоляции: правка шаблона не влияет на уже запущенные процессы.
- Три типа активити: `activity`, `condition`, `robot_activity`. Один класс может быть
  зарегистрирован сразу как действие и как робот.
- По характеру выполнения: немедленное, событийное (`IBPEventActivity`) и задание
  (`CBPCompositeActivity` плюс методы формы).
- Порядок поиска активити: `/local/activities/` → `/local/activities/custom/` →
  `/bitrix/activities/custom/` → `/bitrix/activities/bitrix/` → каталог модуля. Имя каталога —
  имя класса без префикса `CBP` в нижнем регистре.
- `BaseActivity` (D7) добавляет `internalExecute()` с возвратом `ErrorCollection`, автоматическое
  подключение модулей через `$requiredModules`, декларативное описание формы настроек.
- У условий (`CBPActivityCondition`) **нет** доступа к окружению процесса: ни `$this->workflow`,
  ни записи в журнал, ни переменных — всё через родительское активити.
- Глобальные переменные и константы привязаны к типу документа и имеют видимость `GLOBAL`,
  `<module>` или `<module>_<entity>`. «Глобальные константы» изменяемы, в отличие от констант
  шаблона.
- `bizproc` подключён не всегда — нужен `Loader::includeModule('bizproc')`.

## Что встроено в вики
- Концепт: [[concept-bizproc-engine]].
- Карточки: [[entity-cbp-activity]], [[entity-cbp-activity-condition]],
  [[entity-bizproc-field-type]], [[entity-bizproc-globals-manager]].
- Эмпирическая часть (форма задания, категории дизайнера, ловушки вёрстки) — в
  [[recipe-bizproc-custom-task-activity]] и [[entity-cbp-task-service]]; источник её не покрывает.

## Противоречия с текущими страницами
Нет.

## Открытые вопросы
- Полный набор `CBPActivityExecutionStatus::*` и `CBPTrackingType::*`.
- Где лежат системные классы условий для штатных блоков.
- Совпадает ли набор типов глобальных переменных с `FieldType`.

[← Конспекты источников](_index-sources.md)
