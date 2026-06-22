---
title: "Код-стайл и безопасность"
type: concept
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-06-20 / Bitrix Framework"
tags: [код-стайл, безопасность, sql, xss, csrf, права]
sources: []
related: ["[[concept-code-namespaces-and-autoloading]]", "[[antipattern-box-core-modification]]"]
aliases: []
updated: "2026-06-20"
---

# Код-стайл и безопасность

**TL;DR:** единый стиль (неймспейсы, `Result`/`Error`, `Loc`) и обязательные меры безопасности
(защита от SQLi/XSS/CSRF, проверка прав) для любого кода под коробку.

## Стиль
- Пространства имён и автозагрузка — [[concept-code-namespaces-and-autoloading|по стандарту D7]];
  классы — UpperCamelCase, методы — camelCase.
- Результаты операций — через `Bitrix\Main\Result`/`Error`, а не «echo + die»:
  ```php
  $result = new \Bitrix\Main\Result();
  if ($bad) { $result->addError(new \Bitrix\Main\Error('Текст', 'CODE')); }
  return $result;
  ```
- Тексты — через локализацию `Bitrix\Main\Localization\Loc` (`Loc::getMessage('CODE')`), без хардкода.
- Работа с данными — через **D7 ORM**, а не прямые SQL, где возможно.

## Безопасность (обязательно)
- **SQL-инъекции:** ORM/параметризация; для сырых запросов — экранирование
  `$connection->getSqlHelper()->forSql($v)` (или `$DB->ForSql()`). Не склеивать ввод в SQL.
- **XSS:** экранировать вывод — `htmlspecialcharsbx($v)`; не печатать пользовательский ввод как есть.
- **CSRF:** на формах/действиях — `bitrix_sessid_post()` и проверка `check_bitrix_sessid()`.
- **Права доступа:** проверять явно (`$USER->IsAdmin()`, права модуля
  `$APPLICATION->GetGroupRight('vendor.module')`, CRM-права), а не полагаться на «скрытость» пункта меню.
- **Файлы/пути:** валидировать имена/пути, не доверять `$_REQUEST` напрямую.

## Чек-лист ревью
- [ ] Неймспейсы/автозагрузка по стандарту; нет правок ядра ([[antipattern-box-core-modification|почему]])
- [ ] Ошибки через `Result`/`Error`, тексты через `Loc`
- [ ] Любой SQL параметризован/экранирован
- [ ] Весь вывод экранирован (XSS)
- [ ] Формы/действия защищены `bitrix_sessid` (CSRF)
- [ ] Проверки прав на месте

## Связанное
- [[concept-code-namespaces-and-autoloading]], [[concept-dev-standards|Стандарт разработки]]

[← Ядро D7](_index-core-d7.md)
