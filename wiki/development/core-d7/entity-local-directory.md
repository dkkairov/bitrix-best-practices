---
title: "Каталог /local/"
type: entity
module: core-d7
edition: box
status: verified
provenance: documented
verified: "2026-06-01 / документация Bitrix Framework (dev.1c-bitrix.ru)"
tags: [local, доработки, структура, приоритет, git]
sources: ["[[source-devbook-dev-rules]]"]
related: ["[[concept-change-invasiveness-hierarchy]]", "[[entity-php-interface]]", "[[concept-code-namespaces-and-autoloading]]", "[[checklist-dev-environment-and-git]]"]
aliases: ["bitrix24-katalog-local"]
updated: "2026-09-18"
---

# Каталог `/local/`

**Что это:** каталог рядом с `/bitrix/`, где живут все доработки проекта. Единственное место, где
разработчику можно почти всё.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | каталог |
| Появился | с версии главного модуля 14.0.1 (вместе с D7) |
| Приоритет | при коллизии имён `/local/` **побеждает** `/bitrix/` |
| Git | единственная папка проекта, которая целиком идёт в репозиторий |

## Что внутри

| Подкаталог | Назначение |
|---|---|
| `activities/` | действия бизнес-процессов |
| `blocks/` | блоки для сайтов |
| `components/` | компоненты |
| `gadgets/` | гаджеты рабочего стола |
| `js/` | JS-расширения |
| `modules/` | свои модули |
| `php_interface/` | хуки и внутренние классы ([[entity-php-interface]]) |
| `templates/` | шаблоны сайтов |

Дополнительно допустим `tools/` для технических скриптов.

## Приоритет в действии

На шаге подключения инициализации пайплайна сначала проверяется
`/local/php_interface/init.php`, и только при его отсутствии берётся аналог в `/bitrix/`
([[concept-request-lifecycle]]). Так же работают шаблоны и компоненты.

## Подводные камни

- **`vendor/` и `composer.json` в корне `/local/` не класть** — их место внутри
  `/local/php_interface/`. Подробнее — [[recipe-composer-third-party-libraries]].
- **Копировать системный шаблон Bitrix24 в `/local/templates/` крайне не рекомендуется** —
  это нижняя ступень [[concept-change-invasiveness-hierarchy|иерархии изменений]] и вечная
  боль при обновлениях. Класть **свои** шаблоны туда — норма.
- **`/local/admin/` не роутится по URL.** В отличие от компонентов и шаблонов, Bitrix не отдаёт
  админ-страницы из `/local/`: нужен тонкий загрузчик в `/bitrix/admin/`
  ([[recipe-bizproc-custom-task-activity]]).
- **Своих CSS в списке каталогов нет** — только `js/`. Стили подключают из шаблона или своего
  расширения.
- Два случая, когда `/bitrix/` всё-таки трогают: разработка собственного модуля и техническая
  невозможность альтернативы. Оба требуют отдельного обоснования.

## Связанное
- [[concept-change-invasiveness-hierarchy]] — почему всё живёт здесь
- [[entity-php-interface]] — самая используемая часть каталога

[← Ядро D7](_index-core-d7.md)
