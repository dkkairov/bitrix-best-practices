---
title: "Пользователи и группы: API и что важно при внедрении"
type: concept
module: permissions
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / коробка в Docker, main 26.750.0: классы CUser, Main\\UserTable, Main\\GroupTable, Engine\\CurrentUser (getId, getUserGroups, canDoOperation и другие) на месте; группы портала на стенде — 1 Администраторы, 2 Все пользователи, 3–4 RATING_*, 5 MAIL_INVITED, 6 ADMIN_SECTION, 7 SUPPORT, 8 CREATE_GROUPS, 9 PERSONNEL_DEPARTMENT, 10 DIRECTION, 11 MARKETING_AND_SALES, 12 EMPLOYEES_s1; текст — документация фреймворка (docs.1c-bitrix.ru, «Пользователи», «Группы пользователей»)"
tags: [пользователи, группы, права, авторизация, внедрение]
sources: []
related: ["[[concept-org-structure]]", "[[checklist-permissions-audit]]", "[[recipe-engine-controller-action]]", "[[concept-user-fields]]"]
aliases: []
updated: "2026-09-24"
---

# Пользователи и группы: API и что важно при внедрении

**TL;DR:** пользователь — это `CUser` (изменение) и `Bitrix\Main\UserTable` (выборка). Группы —
`GroupTable`; на них висят права модулей. В Битрикс24 поверх групп лежит оргструктура, и путать их
нельзя.

## API

| Задача | Чем |
|---|---|
| выборка | `Bitrix\Main\UserTable::query()->setSelect([...])->where(...)->fetchCollection()` |
| создание, изменение | `$user = new CUser(); $user->Add([...])`, `$user->Update($id, [...])` |
| авторизация | `$USER->Login($login, $password, 'Y')`, `$USER->Authorize($userId)`, `IsAuthorized()` |
| группы пользователя | `$USER->GetUserGroupArray()` |
| текущий пользователь в контроллере | `Bitrix\Main\Engine\CurrentUser` — `getId()`, `getLogin()`, `getFullName()`, `getUserGroups()`, `canDoOperation()` ([[recipe-engine-controller-action]]) |

`CurrentUser` подставляется в действие автоматически — в контроллере это правильнее глобального
`$USER`.

## Группы на портале (стенд)

Штатные группы Битрикс24: `1` Администраторы, `2` Все пользователи (включая неавторизованных),
`6` ADMIN_SECTION, `7` SUPPORT, `8` CREATE_GROUPS, `9` PERSONNEL_DEPARTMENT, `10` DIRECTION,
`11` MARKETING_AND_SALES, `12` EMPLOYEES_s1, плюс служебные `RATING_VOTE`, `RATING_VOTE_AUTHORITY`,
`MAIL_INVITED`.

**Строковый код (`STRING_ID`) стабильнее числового `ID`** — в коде опираемся на него, иначе решение
не переносится между порталами.

## Что важно на внедрении

- **Группы ≠ оргструктура.** Подразделения и руководители — это оргструктура
  ([[concept-org-structure]]), а группы дают права на модули. Требование «руководитель отдела видит
  своих» решается оргструктурой и правами CRM, а не новой группой.
- **Права проверяем, а не предполагаем.** Свой код обязан спрашивать права сам: ORM и REST
  не делают это за нас ([[checklist-permissions-audit]]).
- **Администраторов должно быть мало.** «Выдать администратора, чтобы работало» — самый частый
  способ обойти нерешённую задачу по правам; на аудите это первое, что видно.
- **Обязательные поля регистрации** настраиваются в параметрах главного модуля, а не в коде.
- **Добавление в группу при регистрации** — штатная настройка; свой обработчик события для этого
  обычно не нужен.
- **Свои атрибуты сотрудника** — пользовательские поля пользователя (`UF_USR_*`), а не отдельная
  таблица ([[concept-user-fields]]).

## Подводные камни

- **Массовое создание пользователей** запускает события, письма и агентов: импорт делаем порциями и
  с отключёнными уведомлениями, иначе сотрудники получат сотни писем.
- **Удаление пользователя** ломает историю: в Битрикс24 сотрудников **увольняют** (деактивируют), а
  не удаляют.
- **`GetUserGroupArray()` кэшируется в сессии** — смена групп применится не мгновенно.

## Связанные страницы
- [[concept-org-structure]] — подразделения и руководители
- [[checklist-permissions-audit]] — аудит прав
- [[concept-user-fields]] — свои поля пользователя

[← Права доступа](_index-permissions.md)
