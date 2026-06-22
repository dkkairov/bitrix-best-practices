---
title: "Конспект: курс 43, раздел о модулях"
type: source-summary
module: modules-custom
edition: box
status: verified
provenance: documented
verified: "2026-06-20 / dev.1c-bitrix.ru"
tags: [d7, модули, install, события, разработка]
sources: []
related: ["[[recipe-module-structure-and-install]]", "[[pattern-module-based-development-standard]]", "[[pattern-events-over-core-modification]]"]
aliases: []
updated: "2026-06-20"
---

# Конспект: курс 43, раздел о модулях

## Об источнике
| Поле | Значение |
|------|----------|
| Тип | официальный учебный курс |
| Курс | COURSE_ID=43 «Разработчик Bitrix Framework», раздел о модулях |
| Уроки | «Модули в D7», «Структура файлов», «Описание и параметры», «Установка и удаление», «Административные скрипты/меню», «Взаимодействие модулей» |
| URL | https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43 |
| Raw | `raw/sources/2026-06-20-bxfw-course43-modules.md` |

## TL;DR
Раздел описывает устройство модуля D7 (структура файлов, классы в `/lib`, неймспейс), его установку и
удаление (класс `CModule`), регистрацию событий, опции и взаимодействие модулей без правки чужого кода.

## Ключевые тезисы
- Модуль = `include.php` + `/lib` (`Vendor\Module`) + `lang/` + `install/` (`version.php`, `index.php`).
- Установка/удаление — методы класса установки: регистрация модуля, событий, БД, файлов и обратные действия.
- Взаимодействие модулей — через события и публичный API, не трогая чужой код/ядро.

## Что встроено в вики
- [[recipe-module-structure-and-install|Структура модуля и установка]] — скелет `install/index.php`, `version.php`.
- [[pattern-module-based-development-standard|Модульная разработка]] — когда модуль и где размещать.
- [[pattern-events-over-core-modification|Расширение через события]] — регистрация обработчиков.

## Противоречия с текущими страницами
- Нет. Согласуется с [[concept-code-namespaces-and-autoloading|пространствами имён]] и
  антипаттерном [[antipattern-box-core-modification|правки ядра]].

## Открытые вопросы
- Детализировать административные страницы модуля (`options.php`, меню) отдельным рецептом при необходимости.

[← Конспекты источников](_index-sources.md)
