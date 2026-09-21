---
title: "CIntranetUtils — оргструктура и отсутствия (C-API интранета)"
type: entity
module: administration
edition: box
status: verified
provenance: mixed
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Модуль Интранет — Организационная структура, Отсутствия"
tags: [интранет, оргструктура, отсутствия, класс, c-api]
sources: ["[[source-devbook-intranet]]"]
related: ["[[concept-org-structure]]", "[[entity-user-absence]]", "[[concept-bitrix-naming-conventions]]", "[[recipe-intranet-absence-import]]"]
aliases: ["bitrix24-cintranetutils"]
updated: "2026-09-21"
---

# `CIntranetUtils`

**Что это:** C-API модуля `intranet` для оргструктуры и отсутствий. Сосуществует с D7-классами
`\Bitrix\Intranet\Util` и [[entity-user-absence|`\Bitrix\Intranet\UserAbsence`]] — типичный случай
двух поколений ([[concept-bitrix-naming-conventions]]). Книга называет устаревшим только прокси
`getDepartmentEmployees()`; `GetStructure()` и выборку отсутствий за период она рекомендует — их
D7-аналогов книга не называет
([Организационная структура](https://bx24devbook.website.yandexcloud.net/Modul_Intranet/Orgstruktura.html#api);
атрибуция исправлена при сверке 2026-09-21). О возможном переходе структуры компании на модуль
`humanresources` в новых версиях — [[concept-org-structure]].

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
`EMPLOYEES` содержит только **прямых** сотрудников подразделения, без вложенных. **Все ID —
строки** (`ID`, `UF_HEAD`, `DEPTH_LEVEL`, элементы `TREE` и `EMPLOYEES`; `IBLOCK_SECTION_ID` — `0`
или строка): строгие сравнения с `int` (`===`, `in_array(…, true)`) не сработают.

### `GetIBlockSectionChildren($arSections): array`

Для списка подразделений — **исходные ID плюс** все вложенные, плоским списком (так в примере
книги: корень попадает в результат).

### `GetDeparmentsTree($sectionId = 0, $bFlat = false): array`

Дерево от одного подразделения: по умолчанию ассоциативное `parentId → [childId, …]`,
с `$bFlat = true` — плоский список дочерних ID.

### Сотрудники подразделения

`CIntranetUtils::getDepartmentEmployees(...)` — прокси для совместимости, книга его не рекомендует.
**Звать напрямую D7** (возвращает наследника `CDBResult` — перебирать через `fetch()`; `RECURSIVE`
по умолчанию `N`):

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
| `GetAbsenceData($params, $mode = BX_INTRANET_ABSENCE_ALL): array` | отсутствия за период |

Режимы (целочисленные константы): `BX_INTRANET_ABSENCE_ALL` — из календаря и графика отсутствий,
**это значение по умолчанию**; `BX_INTRANET_ABSENCE_HR` — только из графика. Для кадровых данных
режим `…_HR` задавайте явно, иначе в результат попадут и события календаря.

Параметры: `DATE_START` и `DATE_FINISH` (в формате сайта), `USERS` (пусто — без фильтра),
`PER_USER` (группировать по сотруднику).

## Подводные камни

- **`GetIBlockSectionChildren` делает запросы в цикле** — на большом списке подразделений это
  заметно. И **ключам результата доверять нельзя**: массив собирается через `array_merge` и
  `array_unique`, индексы получаются произвольными. Обходить только по значениям.
- `GetStructure()` возвращает **всю** структуру: на крупном портале это заметный объём. Пример в
  книге подписан как кэшированная структура — нужен ли свой кэш поверх, проверить (совет «кэшируйте
  у себя» — гипотеза команды).
- Даты в `GetAbsenceData` — в формате сайта, а не в ISO. Для России это `d.m.Y H:i:s`.

## Связанное
- [[concept-org-structure]] — зачем это при внедрении
- [[entity-user-absence]] — D7-часть по отсутствиям

[← Администрирование портала](_index-administration.md)
