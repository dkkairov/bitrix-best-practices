---
title: "Смарт-процесс (СПА)"
type: entity
module: smart-process
edition: both
status: verified
provenance: mixed
verified: "2026-06-19 / Bitrix24 cloud + box; коробочная часть — 2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook): Смарт-процессы / Описание; бизнес-процессы в СПА — 2026-09-22 / курс 57 dev.1c-bitrix.ru (уроки 26610, 23586, 23580)"
tags: [спа, smart-process, crm, сущности]
sources: ["[[source-devbook-crm]]", "[[source-course57-actions-crm-disk]]"]
related: ["[[pattern-crm-sales-funnel-design]]", "[[antipattern-everything-in-one-funnel]]", "[[pattern-robots-vs-bizproc-decision]]", "[[antipattern-bizproc-hardcoded-portal-ids]]", "[[recipe-smart-process-programmatic-creation]]", "[[recipe-smart-process-factory-customization]]"]
aliases: ["smart-process"]
updated: "2026-09-22"
---

# Смарт-процесс (СПА)

**Что это:** пользовательская CRM-сущность со своими полями, воронкой стадий, автоматизацией и
связями с базовыми объектами CRM (сделки, контакты, компании).

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | сущность CRM (конструктор) |
| Назначение | моделировать процессы, которые не являются продажей, и вспомогательные данные продаж |
| Появление | в CRM с версии 20.700.0 (коробка) |
| Edition | cloud и box (доступность зависит от тарифа/редакции) |
| Автоматизация | роботы/триггеры и бизнес-процессы, как у сделок |

## Хранение (коробка)

По «Книге разработчика» ([Смарт-процессы](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Smart_processy/Opisanie.html#arhitektura-hranenia)):
тип — строка в `b_crm_dynamic_type`, элементы — в `b_crm_dynamic_items_<ENTITY_TYPE_ID>`, поисковый
индекс — в `…_index`, по таблице на каждое множественное пользовательское поле, описания полей — в
`b_user_field`. По идее книга ставит СПА рядом с инфоблоками и универсальными списками. Код —
[[recipe-smart-process-programmatic-creation]], [[recipe-smart-process-factory-customization]].

## Когда использовать
- Договоры, заявки, поставки, рекламации, абонементы, согласования, объекты учёта.
- Когда натягивание процесса на продажную воронку даёт [[antipattern-everything-in-one-funnel|Всё в одной воронке]].

## Когда НЕ использовать
- Для собственно продаж — это обычные сделки и направления.
- Когда достаточно полей/стадий существующей сущности.

## Подводные камни
- Не плодить СПА без необходимости: каждая сущность — это поддержка, права и обучение.
- Заранее продумать **связи** с CRM и **права доступа**.
- Нумерация и обязательные поля — как и в сделках, привязывать к стадиям.
- ID смарт-процесса (`DYNAMIC_XXX`), стадий (`DTXXX_YY:…`) и полей (`UF_CRM_…`) на каждом портале
  свои. При переносе автоматизации их нужно сопоставлять
  ([[antipattern-bizproc-hardcoded-portal-ids|Зашитые ID портала]]).

## Бизнес-процессы в смарт-процессе
- Шаблоны БП для СПА появляются, только если у типа включена опция «Использовать в смарт-процессе
  дизайнер бизнес-процессов» ([урок 26610](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=26610));
  чтобы действия чтения возвращали поля, нужны ещё роботы и триггеры
  ([урок 23586](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=23586)).
- «Изменить элемент смарт-процесса» при нескольких подходящих под фильтр меняет только первый по ID;
  чтобы изменить все, нужен «Итератор» с фильтром по ID
  ([урок 23580](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=23580)).
- Изменения, которые делает сам бизнес-процесс, по умолчанию не запускают ни роботов, ни автозапуск
  БП «при изменении» — [[entity-robots-triggers|роботы и триггеры]].
- Действия БП для СПА и их поведение — [[concept-bizproc-activity-catalog|каталог действий]].

## Связанное
- [[pattern-crm-sales-funnel-design|Проектирование воронки]], [[antipattern-everything-in-one-funnel|Всё в одной воронке]], [[pattern-robots-vs-bizproc-decision|Роботы vs бизнес-процессы]]

[← Глоссарий](_index-glossary.md)
