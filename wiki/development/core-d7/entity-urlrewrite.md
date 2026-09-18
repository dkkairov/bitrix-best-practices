---
title: "urlrewrite.php и $arUrlRewrite"
type: entity
module: core-d7
edition: box
status: verified
provenance: documented
verified: "2026-06-01 / документация Bitrix Framework, обработка URI (dev.1c-bitrix.ru)"
tags: [роутинг, чпу, urlrewrite, файл]
sources: ["[[source-devbook-dev-rules]]"]
related: ["[[concept-request-lifecycle]]", "[[concept-change-invasiveness-hierarchy]]", "[[recipe-custom-left-menu-section]]"]
aliases: ["bitrix24-urlrewrite"]
updated: "2026-09-18"
---

# `urlrewrite.php`

**Что это:** файл `/bitrix/urlrewrite.php` с правилами переадресации. Один из немногих файлов
внутри `/bitrix/`, который **штатно правится** разработчиком.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | файл с массивом `$arUrlRewrite` |
| Роль | фаза выбора файла в [[concept-request-lifecycle|пайплайне]] |
| Edition | box |

## Поля правила

| Поле | Назначение |
|---|---|
| `CONDITION` | регулярное выражение против `$_SERVER['REQUEST_URI']` — обязательно |
| `PATH` | физический файл, который подключится — обязательно |
| `RULE` | строка замены: именно она даёт управление ЧПУ |
| `ID` | имя компонента, добавившего правило (например, `bitrix:crm.deal_category`) |
| `SORT` | приоритет |

Порядок проверки: сначала по `SORT`, при равенстве — **по длине `CONDITION`**. Итоговый адрес
собирается как `preg_replace(CONDITION, PATH . RULE, REQUEST_URL)`.

Именно этим механизмом работают адреса собственных разделов меню
(`#^/page/#` → компонент интранета, [[recipe-custom-left-menu-section]]).

## Подводные камни

- **Bitrix подменяет глобальные переменные.** Параметры, добавленные правилом, попадают в `$_GET`,
  `$_POST` и `$_REQUEST`, причём поля из `RULE` **перекрывают** одноимённые GET-параметры, а
  `$_SERVER['QUERY_STRING']` может не отражать исходную строку запроса. Код, читающий
  `QUERY_STRING` напрямую, здесь ошибётся.
- **«Пересоздание правил обработки адресов» в админке использовать нельзя.** Это прямой пункт
  списка «никогда» ([[concept-change-invasiveness-hierarchy]]): теряются правила модулей «Диск»,
  REST, мобильного приложения; при `require`/`include` корректной индексации не происходит.
- Правило с более длинным `CONDITION` выигрывает при равном `SORT` — неочевидно и объясняет
  «почему сработало не то правило».

## Открытые вопросы
- Что делать, если файл всё-таки повреждён, — безопасной альтернативы пересозданию в документации
  нет.
- Как современный роутинг Bitrix Framework соотносится с этим файлом.

## Связанное
- [[concept-request-lifecycle]] — где этот файл в обработке запроса

[← Ядро D7](_index-core-d7.md)
