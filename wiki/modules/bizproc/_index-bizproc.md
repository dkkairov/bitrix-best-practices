---
title: "Бизнес-процессы, роботы, триггеры"
type: index
module: bizproc
edition: both
status: verified
updated: "2026-09-22"
---

# Бизнес-процессы — практики внедрения

Движок автоматизации: роботы и триггеры в CRM, бизнес-процессы (дизайнер БП), последовательные и
со статусами процессы.

## Что покрываем
- Когда робот/триггер, а когда полноценный бизнес-процесс
- Дизайнер БП: статусы, действия, ветвления, обработка ошибок
- Автоматизация по стадиям воронки
- Производительность и отладка БП
- Формат шаблонов `.bpt`, перенос между порталами, генерация БП агентом

## Страницы
- [[pattern-robots-vs-bizproc-decision|Паттерн выбора: роботы/триггеры vs бизнес-процессы]]
- [[concept-bizproc-engine|Устройство движка БП: шаблон, инстанс, активити]] · коробка
- [[concept-bizproc-bpt-format|Формат шаблона БП (.bpt): устройство и чтение]]
- [[concept-bizproc-activity-catalog|Каталог действий БП: свойства, вложенность, результаты]]
- [[pattern-bizproc-ai-assisted-generation|AI-генерация БП: агент проектирует, код собирает]] (черновик)
- [[recipe-bizproc-custom-task-activity|Своё действие БП с заданием (CBPTaskService)]] · коробка
- [[antipattern-bizproc-php-code-activity|Действие «PHP код» в шаблонах БП]] · коробка

### Классы и файлы (справочник API, коробка)
- [[entity-cbp-activity|CBPActivity и BaseActivity]] — базовые классы действия
- [[entity-bizproc-activity-description|Файл .description.php]] — паспорт действия, робота или условия
- [[entity-cbp-activity-condition|CBPActivityCondition]] — условия для блоков «Условие» и «Цикл»
- [[entity-cbp-task-service|CBPTaskService]] — задания процессов
- [[entity-bizproc-field-type|Bizproc\FieldType]] — типы значений
- [[entity-bizproc-globals-manager|GlobalsManager]] — глобальные переменные и константы

### Смежное
- В глоссарии: [[entity-robots-triggers|роботы и триггеры]],
  [[entity-bizproc-template-rest-methods|REST-методы шаблонов БП]]
- В антипаттернах: [[antipattern-bizproc-hardcoded-portal-ids|зашитые ID портала в шаблонах]]
- Конспекты источников: [[source-devbook-bizproc|«Книга разработчика», модуль БП]]; официальный курс 57
  «Бизнес-процессы» — [[source-course57-basics|основы]], [[source-course57-templates-designer|объекты, дизайнер, шаблоны]],
  [[source-course57-expressions|выражения и ошибки]], [[source-course57-actions-core|действия: документ, задания, конструкции]],
  [[source-course57-actions-notify-other|уведомления и прочее]], [[source-course57-actions-crm-disk|CRM и Диск]],
  [[source-course57-examples|примеры]], [[source-course57-developer|для разработчика]]
- Утилита [tools/bpt](../../../tools/bpt/README.md) — разбор, сборка из спецификации, проверка и
  схема `.bpt`; формат спецификации — [SPEC.md](../../../tools/bpt/SPEC.md); порядок работы
  агента по ТЗ — навык Claude Code
  [building-bizproc-templates](../../../.claude/skills/building-bizproc-templates/SKILL.md)

## Статус покрытия
Есть выбор инструмента, формат `.bpt`, каталог действий (25 типов по корпусу; смысл значений и
поведение сверены с курсом 57 и ядром), REST-методы шаблонов, подход к генерации агентом (черновик,
инструменты и навык готовы), антипаттерны переноса и «PHP кода», своё действие с заданием, паспорт
`.description.php` и справочник по пяти классам движка. Страницы разработки действий сверены с
«Книгой разработчика» 2026-09-21 ([конспект](../../sources/source-devbook-bizproc.md)), весь кластер —
с официальным курсом 57 «Бизнес-процессы» 2026-09-22. Оба вопроса «книга ↔ практика команды» закрыты
кодом ядра: задание без веток штатно на `CBPActivity`, с ветками — на `CBPCompositeActivity`; результат
из `RETURN` дизайнер видит без `ADDITIONAL_RESULT`.
Не хватает: рецептов типовых процессов (согласование, заявка), отладки, ошибок проектирования БП
(ожидания без таймаута, циклы, ссылки на удалённые шаги), рецепта своего действия на `BaseActivity`.

[← Обзор вики](../../../index.md)
