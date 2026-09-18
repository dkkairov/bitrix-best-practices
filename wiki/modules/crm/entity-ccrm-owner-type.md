---
title: "\\CCrmOwnerType и \\CCrmOwnerTypeAbbr"
type: entity
module: crm
edition: box
status: verified
provenance: documented
verified: "2026-06-02 / документация модуля CRM, раздел структур данных (apidocs.bitrix24.ru)"
tags: [crm, мнемокоды, типы-сущностей, класс, url]
sources: []
related: ["[[entity-crm-factory]]", "[[concept-bitrix-naming-conventions]]", "[[entity-ccrm-field-multi]]"]
aliases: ["bitrix24-ccrm-owner-type"]
updated: "2026-09-18"
---

# `\CCrmOwnerType` и `\CCrmOwnerTypeAbbr`

**Что это:** словарь типов CRM-сущностей. Старый C-API, но используется повсеместно, включая
современный Universal API: `getFactory(\CCrmOwnerType::Deal)`.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | утилитарные классы |
| Модуль | `crm` |
| Edition | box |

## Соглашение констант

- **Порядковый номер** (int) — без постфикса: `\CCrmOwnerType::Lead` = `1`, `::Deal` = `2`.
- **Мнемокод** (string) — с постфиксом `Name`: `::LeadName` = `'LEAD'`, `::DealName` = `'DEAL'`.

Это та самая семья идентификаторов вокруг кодового имени сущности, о которой
[[concept-bitrix-naming-conventions]].

Служебные группы: `Suspended*` — для корзины (recyclebin), `ScoringName` — для машинного обучения.

## Конвертация

| Метод | Возврат |
|---|---|
| `IsDefined($typeID)` | известен ли тип |
| `IsEntity($typeID)` | основная ли это сущность |
| `ResolveID($name)` | `'LEAD'` → `1` |
| `ResolveName($id)` | `1` → `'LEAD'` |
| `ParseEntitySlug($slug)` | `'L_1'` → `['ENTITY_TYPE_ID' => …, 'ENTITY_ID' => …]` |

`\CCrmOwnerTypeAbbr::ResolveByTypeID($typeID)` даёт однобуквенную аббревиатуру: сделка → `D`,
лид → `L`. Комплексные префиксы (`L_1`) нужны полям, которые хранят ID разных сущностей.

## Генерация адресов

| Метод | Пример результата |
|---|---|
| `GetEntityShowPath($typeID, $ID, $checkPerm = false, $opts = null)` | `/crm/lead/details/123/` |
| `GetEntityEditPath($typeID, $ID, …)` | `/crm/lead/details/123/?init_mode=edit` |
| `GetListUrl($typeID, $checkPerm = false)` | `/crm/lead/list/` |

При `$checkPerm = true` и отсутствии прав вернётся пустая строка — обрабатывайте этот случай,
иначе получите ссылку «в никуда».

## Подводные камни

- **Опцию `ENABLE_SLIDER` в `$opts` не трогать** без необходимости: она связана с историей
  «карточка или слайдер», и значение по умолчанию обычно правильное.
- Эти адреса **не подходят для смарт-процесса, вынесенного в свой раздел меню**: там карточка
  открывается по `/page/<раздел>/<страница>/type/<id>/details/<элемент>/`. См.
  [[recipe-custom-left-menu-section]].
- Порядковые номера — не «красивые» ID: не выводите их пользователю и не зашивайте в шаблоны
  бизнес-процессов ([[antipattern-bizproc-hardcoded-portal-ids]]).

## Связанное
- [[entity-crm-factory]] — главный потребитель констант
- [[entity-ccrm-field-multi]] — `ENTITY_ID` мультиполей приходит отсюда

[← CRM](_index-crm.md)
