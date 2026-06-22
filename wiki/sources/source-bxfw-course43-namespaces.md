---
title: "Конспект: курс 43, урок «Пространства имён»"
type: source-summary
module: core-d7
edition: box
status: verified
provenance: documented
verified: "2026-06-19 / dev.1c-bitrix.ru"
tags: [d7, namespaces, разработка, коробка]
sources: []
related: ["[[concept-code-namespaces-and-autoloading]]"]
aliases: ["src-bxfw-course43-namespaces"]
updated: "2026-06-20"
---

# Конспект: курс 43, урок «Пространства имён»

## Об источнике
| Поле | Значение |
|------|----------|
| Тип | официальный учебный курс |
| Курс | COURSE_ID=43 «Разработчик Bitrix Framework» → Middle → Ядро D7 |
| Урок | LESSON_ID=3524 «Пространства имён» |
| URL | https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=3524 |
| Raw | `raw/sources/2026-06-19-bxfw-course43-namespaces.md` |

## TL;DR
Соглашения по пространствам имён в Bitrix Framework: корень `Bitrix`, по подпространству на модуль,
классы в `/lib/`, именование UpperCamelCase (латиница), сокращение путей через `use`.

## Ключевые тезисы
- Корень стандартных классов — `Bitrix`; модуль = подпространство (`main` → `Bitrix\Main`).
- Партнёрские модули — свой корень (`Asd\Metrika`); классы в каталоге `/lib/`.
- Под-пространства — только при необходимости (`Bitrix\Main\IO`).
- Именование: UpperCamelCase, только латиница; классы — существительные.
- `use` для алиасов длинных путей; допустимы полный/сокращённый/алиас-варианты обращения.

## Что встроено в вики
- Создана страница [[concept-code-namespaces-and-autoloading|Пространства имён]] — соглашения по неймспейсам, автозагрузке и
  размещению кода в `/local/` (с практикой автозагрузки и связью с антипаттерном правки ядра).

## Противоречия с текущими страницами
- Нет. Дополняет [[antipattern-box-core-modification|Правка ядра коробки]] (свой код в `/local/`, а не в ядре).

## Открытые вопросы
- Ингестить соседние уроки курса 43: автозагрузка классов, ORM D7, события, агенты, service locator.

[← Конспекты источников](_index-sources.md)
