---
title: "Селектор сущностей ui.entity-selector"
type: concept
module: templates-design
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / коробка в Docker, main 26.750.0: расширение /bitrix/js/ui/entity-selector на месте; текст — документация фреймворка (docs.1c-bitrix.ru, раздел «ui.entity-selector»: обзор и стандартные провайдеры)"
tags: [ui, селектор, пользователи, отделы, провайдеры, js]
sources: []
related: ["[[concept-ui-subsystem]]", "[[concept-js-extensions]]", "[[recipe-custom-list-page-filter-grid]]", "[[concept-org-structure]]"]
aliases: []
updated: "2026-09-24"
---

# Селектор сущностей `ui.entity-selector`

**TL;DR:** штатный виджет выбора людей, отделов, проектов, чатов и объектов модулей — тот самый, что
стоит в задачах, календаре и CRM. Свой «выбор сотрудника» писать не нужно: берём расширение и
нужный провайдер.

**Когда применять:** своя форма, где надо выбрать пользователя, отдел или сущность портала. Это
уровень «штатный механизм» в [[concept-change-invasiveness-hierarchy|иерархии изменений]].

## Из чего состоит

| Часть | Что делает |
|---|---|
| `Dialog` | всплывающее окно выбора с поиском и вкладками |
| `TagSelector` | поле, где выбранное показано тегами |
| Провайдеры (`Bitrix\UI\EntitySelector`) | серверная часть: что искать и что показывать |

## Подключение

```php
\Bitrix\Main\UI\Extension::load('ui.entity-selector');
```

```javascript
import { Dialog, TagSelector } from 'ui.entity-selector';
```

Элемент минимально описывается тремя полями: `id`, `entityId` (тип объекта) и `title`.

## Стандартные провайдеры

| `entityId` | Что выбирает | Модуль |
|---|---|---|
| `user` | пользователи | socialnetwork |
| `department` | подразделения и сотрудники | intranet / socialnetwork / iblock |
| `project` | проекты и группы | socialnetwork |
| `meta-user` | служебные «пользователи» (все, отдел, роль) | socialnetwork |
| `im-chat`, `im-bot`, `im-recent` | чаты, боты, недавние диалоги | im |
| `project-access-codes` | коды доступа проекта | socialnetwork |

Полезные опции: у `user` — `userId`, `activeUsers`, `intranetUsersOnly`, `emailUsers`,
`networkUsers`; у `department` — `selectMode` (`usersOnly`, `departmentsOnly`,
`usersAndDepartments`) и `allowOnlyUserDepartments`; у `project` — `projectId`, `type`, `extranet`,
`features`.

Провайдеры живут в интеграциях модулей (`Bitrix\Socialnetwork\Integration\UI\EntitySelector\…`,
`Bitrix\Intranet\Integration\UI\EntitySelector\…`), поэтому **доступность провайдера зависит от
установленных модулей** портала.

## Свой провайдер

Когда выбирать нужно свою сущность (договор, объект, оборудование), пишем провайдер на PHP и
регистрируем его — тогда виджет остаётся штатным, а данные свои. Это правильная точка расширения
вместо копирования виджета.

## Ловушки

- **Права.** Провайдер обязан фильтровать по правам текущего пользователя: селектор показывает то,
  что вернул сервер, и без фильтрации станет источником утечки оргструктуры
  ([[concept-org-structure]]).
- **Не путать с выбором в фильтре.** У фильтра списков свой механизм полей
  ([[recipe-custom-list-page-filter-grid]]); селектор — отдельный виджет.
- **`entityId` — договор между клиентом и сервером.** Своё значение берём с префиксом вендора,
  чтобы не столкнуться с чужим провайдером.
- **Расширение тянет свои CSS** — на странице без `ui.entity-selector` виджет отрисуется «голым»
  ([[concept-js-extensions]]).

## Связанные страницы
- [[concept-ui-subsystem]] — остальные части UI-подсистемы
- [[concept-js-extensions]] — как подключаются расширения
- [[concept-org-structure]] — оргструктура, которую чаще всего и выбирают

[← Шаблоны и дизайн](_index-templates-design.md)
