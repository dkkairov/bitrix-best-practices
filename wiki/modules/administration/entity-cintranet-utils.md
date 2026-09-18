---
title: "CIntranetUtils — оргструктура и отсутствия (старый API)"
type: entity
module: administration
edition: box
status: verified
provenance: documented
verified: "2026-06-01 / документация модуля intranet (dev.1c-bitrix.ru)"
tags: [интранет, оргструктура, отсутствия, класс, c-api]
sources: ["[[source-devbook-intranet]]"]
related: ["[[concept-org-structure]]", "[[entity-user-absence]]", "[[concept-bitrix-naming-conventions]]"]
aliases: ["bitrix24-cintranetutils"]
updated: "2026-09-18"
---

# `CIntranetUtils`

**Что это:** старый C-API модуля `intranet` для оргструктуры и отсутствий. Сосуществует с
D7-классами `\Bitrix\Intranet\Util` и [[entity-user-absence|`\Bitrix\Intranet\UserAbsence`]] —
типичный случай двух поколений ([[concept-bitrix-naming-conventions]]).

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | класс со статическими методами |
| Модуль | `intranet` |
| Edition | box |

## Оргструктура

### `GetStructure(): array`

Всё дерево разом, два ключа:

- **`TREE`** — `parentId → [childId, …]`, корни лежат под ключом `0`;
- **`DATA`** — `id → поля раздела`: `ID`, `NAME`, `IBLOCK_SECTION_ID`, `UF_HEAD`,
  `SECTION_PAGE_URL`, `DEPTH_LEVEL`, `EMPLOYEES`.

**Читать инфоблок оргструктуры напрямую не нужно** — этот метод и есть рекомендованный путь.
`EMPLOYEES` содержит только **прямых** сотрудников подразделения, без вложенных.

### `GetIBlockSectionChildren($arSections): array`

Для списка подразделений — все потомки плоским списком.

### `GetDeparmentsTree($sectionId = 0, $bFlat = false): array`

Дерево от одного подразделения: по умолчанию ассоциативное `parentId → [childId, …]`,
с `$bFlat = true` — плоский список дочерних ID.

### Сотрудники подразделения

`CIntranetUtils::getDepartmentEmployees(...)` — прокси в D7-метод. **Звать напрямую D7:**

```php
\Bitrix\Intranet\Util::getDepartmentEmployees([
    'DEPARTMENTS' => [1, 2],
    'RECURSIVE'   => 'Y',
    'ACTIVE'      => 'Y',
    'SELECT'      => ['ID', 'NAME', 'LAST_NAME'],
]);
```

## Отсутствия

| Метод | Что делает |
|---|---|
| `IsUserAbsent($userId): bool` | отсутствует ли сотрудник **сейчас** |
| `GetAbsenceData($params, $mode): array` | отсутствия за период |

Режимы: `BX_INTRANET_ABSENCE_ALL` — из календаря и графика отсутствий;
`BX_INTRANET_ABSENCE_HR` — только из графика.

Параметры: `DATE_START` и `DATE_FINISH` (в формате сайта), `USERS` (пусто — без фильтра),
`PER_USER` (группировать по сотруднику).

## Подводные камни

- **`GetIBlockSectionChildren` делает запросы в цикле** — на большом списке подразделений это
  заметно. И **ключам результата доверять нельзя**: массив собирается через `array_merge` и
  `array_unique`, индексы получаются произвольными. Обходить только по значениям.
- `GetStructure()` возвращает **всю** структуру: на крупном портале это заметный объём, кэшируйте
  у себя, если зовёте часто.
- Даты в `GetAbsenceData` — в формате сайта, а не в ISO. Для России это `d.m.Y H:i:s`.

## Связанное
- [[concept-org-structure]] — зачем это при внедрении
- [[entity-user-absence]] — D7-часть по отсутствиям

[← Администрирование портала](_index-administration.md)
