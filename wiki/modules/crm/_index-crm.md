---
title: "CRM"
type: index
module: crm
edition: both
status: verified
updated: "2026-09-21"
---

# CRM — практики внедрения

Сделки, лиды, контакты и компании, воронки и стадии, счета, CRM-маркетинг, отчёты и аналитика.

## Что покрываем
- Проектирование воронок и стадий, многоворонночность
- Поля и карточки: обязательность, автозаполнение, дубликаты
- Лиды vs работа без лидов; квалификация
- CRM-маркетинг, источники, сквозная аналитика
- Права в CRM (см. [Права доступа](../permissions/_index-permissions.md))
- Доработка CRM в коробке: Universal API, действия операций, вмешательство в карточку

## Страницы

### Практики
- [[checklist-crm-launch|Чек-лист запуска CRM «под ключ»]]
- [[pattern-crm-sales-funnel-design|Как проектировать воронку и стадии]]
- [[concept-crm-universal-api|Universal API: Container → Factory → Item + Operation]] · коробка
- [[concept-crm-dictionaries|Справочники CRM: новое API читает, старое пишет]] · коробка
- [[pattern-crm-action-vs-event|Operation\Action или обработчик события]] · коробка
- [[recipe-crm-history-all-fields|История смарт-процесса: все поля + источник изменения]] · коробка
- [[recipe-crm-card-editor-js-access|Карточка CRM из JS: где редактор и модель]] · коробка
- [[recipe-crm-hide-card-block-js|Скрыть блок в карточке смарт-процесса]] · коробка
- [[pattern-crm-timeline-client-side|Таймлайн на клиенте: догрузка и фильтрация]] · коробка
- [[recipe-crm-legacy-entity-crud|Лид, контакт, компания, сделка через старое API CCrm*]] · коробка · черновик
- [[recipe-crm-lead-conversion|Конвертация лида из кода]] · коробка · черновик
- [[recipe-crm-todo-activity|Универсальное дело (ToDo) из кода]] · коробка · черновик

### Классы (справочник API, коробка)
- [[entity-crm-container|\Bitrix\Crm\Service\Container]] — точка входа в Universal API
- [[entity-crm-factory|\Bitrix\Crm\Service\Factory]] — элементы, операции, стадии, метаданные
- [[entity-crm-item|\Bitrix\Crm\Item]] — элемент сущности
- [[entity-crm-operation|\Bitrix\Crm\Service\Operation и Action]] — 15 шагов и точки расширения
- [[entity-crm-settings|\Bitrix\Crm\Settings\<Type>Settings]] — включён ли Universal API
- [[entity-ccrm-status|\CCrmStatus и StatusTable]] — справочники и стадии
- [[entity-ccrm-owner-type|\CCrmOwnerType]] — мнемокоды типов и адреса карточек
- [[entity-ccrm-field-multi|\CCrmFieldMulti]] — телефоны, почты, мессенджеры, поиск по телефону
- [[entity-crm-legacy-events|События старого ядра CRM]] — лид, контакт, компания, сделка: параметры и отмена

### Антипаттерны
- [[antipattern-crm-stage-explosion|Антипаттерн: слишком много стадий]]
- [[antipattern-everything-in-one-funnel|Антипаттерн: всё в одной воронке]]

## Статус покрытия
Есть запуск и проектирование воронок; для коробки — Universal API, справочники, выбор
«действие или событие», старое API сущностей и его события, конвертация лида, дела (ToDo), четыре
рецепта доработки карточки и справочник по классам API. Страницы по «Книге разработчика» сверены с
сайтом книги 2026-09-21. Не хватает: поля и карточки на уровне настройки, дубликаты, CRM-маркетинг,
отчёты.

[← Обзор вики](../../../index.md)
