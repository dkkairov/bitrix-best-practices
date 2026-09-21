---
title: "urlrewrite.php и $arUrlRewrite"
type: entity
module: core-d7
edition: box
status: verified
provenance: documented
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Общие сведения — Обработка uri; Разработка — GIT, Введение"
tags: [роутинг, чпу, urlrewrite, файл]
sources: ["[[source-devbook-core-d7]]", "[[source-devbook-dev-rules]]"]
related: ["[[concept-request-lifecycle]]", "[[concept-change-invasiveness-hierarchy]]", "[[recipe-custom-left-menu-section]]", "[[checklist-dev-environment-and-git]]"]
aliases: ["bitrix24-urlrewrite"]
updated: "2026-09-21"
---

# `urlrewrite.php`

**Что это:** два файла с похожими именами. **`/bitrix/urlrewrite.php`** — обработчик правил ЧПУ,
часть ядра: на шаге выбора файла он подключается, если запрошенного файла нет. **Сами правила** —
массив `$arUrlRewrite` в файле `urlrewrite.php` в корне сайта
([Обработка uri](https://bx24devbook.website.yandexcloud.net/Obsie_svedenia/Obrabotka_uri.html)).

> **Исправлено 2026-09-21 при сверке с книгой.** Раньше здесь было сказано, что правила лежат в
> `/bitrix/urlrewrite.php` и это «один из немногих файлов внутри `/bitrix/`, который штатно
> правится». Книга называет `/bitrix/urlrewrite.php` файлом **обработки** правил и считает всё в
> `/bitrix/` ядром; корневой `/urlrewrite.php` в её `.gitignore` исключён из репозитория — он
> генерируется системой и у каждой среды свой.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | файл с массивом `$arUrlRewrite` (корень сайта) + обработчик `/bitrix/urlrewrite.php` (ядро) |
| Роль | фаза выбора файла в [[concept-request-lifecycle|пайплайне]] |
| Git | корневой `/urlrewrite.php` — вне репозитория ([[checklist-dev-environment-and-git]]) |
| Edition | box |

## Поля правила

| Поле | Назначение |
|---|---|
| `CONDITION` | регулярное выражение против `$_SERVER['REQUEST_URI']` — обязательно |
| `PATH` | физический файл, который подключится — обязательно |
| `RULE` | строка замены: из неё берутся параметры запроса |
| `ID` | имя компонента, добавившего правило (например, `bitrix:crm.deal_category`) |
| `SORT` | приоритет |

Порядок проверки: сначала по `SORT`, при равенстве — по длине `CONDITION` (направление этой
сортировки книга не называет). Реальный адрес книга описывает как результат `preg_replace` с
аргументами `CONDITION` и `PATH` + `RULE` над `REQUEST_URI`: запрос обрабатывается так, будто пришёл
на `PATH?<параметры из RULE>`.

Именно этим механизмом работают адреса собственных разделов меню
(`#^/page/#` → компонент интранета, [[recipe-custom-left-menu-section]]).

## Подводные камни

- **Bitrix подменяет глобальные переменные.** Параметры, добавленные правилом, попадают в `$_GET`,
  `$_POST` и `$_REQUEST`, причём поля из `RULE` **перекрывают** одноимённые параметры запроса, а
  `$_SERVER['QUERY_STRING']` может не отражать исходную строку запроса. Код, читающий `QUERY_STRING`
  напрямую, здесь ошибётся.
- **«Пересоздание правил обработки адресов» в админке использовать нельзя.** Это прямой пункт
  списка «никогда» ([[concept-change-invasiveness-hierarchy]]): теряются правила модулей «Диск»,
  REST, мобильного приложения; файлы с `require`/`include` не индексируются
  ([пересоздание правил](https://bx24devbook.website.yandexcloud.net/Obsie_svedenia/Obrabotka_uri.html#peresozdania-pravil-obrabotki-adresov)).
- При равном `SORT` порядок решает длина `CONDITION` — неочевидно и объясняет «почему сработало не
  то правило».

## Открытые вопросы
- Что делать, если файл правил всё-таки повреждён, — безопасной альтернативы пересозданию в книге нет.
- Как современный роутинг Bitrix Framework соотносится с этим файлом: на момент написания книги
  роутинг не поставлялся по умолчанию и требовал отдельной настройки
  ([роутинг](https://bx24devbook.website.yandexcloud.net/Obsie_svedenia/Obrabotka_uri.html#routing)).

## Связанное
- [[concept-request-lifecycle]] — где этот файл в обработке запроса

[← Ядро D7](_index-core-d7.md)
