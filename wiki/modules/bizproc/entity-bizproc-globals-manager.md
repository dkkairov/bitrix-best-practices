---
title: "GlobalsManager — глобальные переменные и константы БП"
type: entity
module: bizproc
edition: box
status: verified
provenance: documented
verified: "2026-06-01 / документация модуля bizproc, раздел окружения (dev.1c-bitrix.ru)"
tags: [bizproc, глобальные-переменные, константы, класс, видимость]
sources: ["[[source-devbook-bizproc]]"]
related: ["[[concept-bizproc-engine]]", "[[entity-cbp-activity]]", "[[antipattern-bizproc-hardcoded-portal-ids]]"]
aliases: ["bitrix24-bizproc-globals-manager"]
updated: "2026-09-18"
---

# `GlobalsManager` — глобальные переменные и константы

**Что это:** API глобальных переменных и констант бизнес-процессов. Практический смысл — держать
общие для портала значения (ID ответственного отдела, пороги сумм) **вне шаблонов**, а не
вписывать их в каждый процесс.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | абстрактный класс `\Bitrix\Bizproc\Workflow\Type\GlobalsManager` |
| Наследники | `GlobalVar` (переменные), `GlobalConst` (константы) |
| Модуль | `bizproc` |
| Привязка | к **типу документа**: `['crm', 'CCrmDocumentDeal', 'DEAL']` |

Все методы живут в родительском классе, наследники различаются только семантикой.

## Методы

| Метод | Что делает |
|---|---|
| `getAll($documentType): array` | все глобалы для типа документа |
| `getById($id): ?array` | описание одного |
| `upsert($id, array $property, $userId = null): bool` | создать или обновить |
| `delete($id): bool` | удалить |

Описание глобала: `Name`, `Description`, `Type`, `Required`, `Multiple`, `Options`, `Default`,
`Visibility`, `CreatedBy` / `CreatedDate`, `ModifiedBy` / `ModifiedDate`.

## Видимость

| `Visibility` | Где доступно |
|---|---|
| `GLOBAL` | везде |
| `<module>` | только в модуле, например `CRM` |
| `<module>_<entity>` | только в сущности, например `CRM_COMPANY` |

## Почему это важно при внедрении

Глобалы — штатная замена [[antipattern-bizproc-hardcoded-portal-ids|зашитым в шаблон ID портала]].
Шаблон, ссылающийся на глобальную константу, переносится между порталами: достаточно один раз
задать значение на новом портале, а не править десятки шагов.

## Подводные камни

- **`upsert()` принимает описание целиком.** Чтобы поменять одно поле, надо прочитать, изменить и
  сохранить — иначе затрёте остальные:

  ```php
  $prop = GlobalVar::getById($id);
  if ($prop === null) { throw new \Exception('Глобал не найден'); }
  $prop['Default'] = 'новое значение';
  GlobalVar::upsert($id, $prop);
  ```

- **«Глобальные константы» здесь можно менять** — в отличие от **констант шаблона**, которые
  задаются в дизайнере и доступны только на чтение (`getConstant()` в
  [[entity-cbp-activity|CBPActivity]]). Одинаковое слово «константа» означает две разные вещи.
- **ID выглядит как `Variable1684762282405`** — имя вида «класс + отметка времени». Не
  конструируйте его вручную и не зашивайте в код: получайте через `getAll()` по имени.

## Открытые вопросы
- Полный API (есть ли `add`, `exists`, события на изменение).
- Где физически хранятся глобалы.
- Совпадает ли набор значений `Type` с [[entity-bizproc-field-type|FieldType]].

## Связанное
- [[antipattern-bizproc-hardcoded-portal-ids]] — проблема, которую глобалы решают

[← Бизнес-процессы](_index-bizproc.md)
