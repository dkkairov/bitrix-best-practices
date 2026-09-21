---
title: "Каталог /local/"
type: entity
module: core-d7
edition: box
status: verified
provenance: documented
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Структура папки local — Основное, GIT"
tags: [local, доработки, структура, приоритет, git]
sources: ["[[source-devbook-dev-rules]]"]
related: ["[[concept-change-invasiveness-hierarchy]]", "[[entity-php-interface]]", "[[concept-code-namespaces-and-autoloading]]", "[[checklist-dev-environment-and-git]]", "[[pattern-local-solution-structure]]"]
aliases: ["bitrix24-katalog-local"]
updated: "2026-09-21"
---

# Каталог `/local/`

**Что это:** каталог рядом с `/bitrix/`, где живут все доработки проекта. Единственное место, где
разработчику можно почти всё
([Структура папки local](https://bx24devbook.website.yandexcloud.net/Razrabotka/Struktura_papki_local/Osnovnoe.html)).

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | каталог |
| Появился | с версии главного модуля 14.0.1 (вместе с D7) |
| Приоритет | при коллизии имён `/local/` **побеждает** `/bitrix/` |
| Git | идёт в репозиторий целиком — вместе с публичной частью сайта; вне git только `/bitrix/*`, `/upload`, `/urlrewrite.php` ([GIT](https://bx24devbook.website.yandexcloud.net/Razrabotka/GIT.html)) |

> **Исправлено 2026-09-21 при сверке с книгой.** Было: «единственная папка проекта, которая целиком
> идёт в репозиторий». По книге в репозитории — `/local` **и публичная часть**; целевой `.gitignore`
> исключает только ядро, загрузки и правила ЧПУ — [[checklist-dev-environment-and-git]].

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

Других каталогов книга заводить не рекомендует; исключение — `tools/` для технических (как правило,
устаревших) скриптов. Внутреннюю структуру `php_interface/` для клиентского кода —
[[pattern-local-solution-structure]].

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
- Два случая, когда `/bitrix/` всё-таки трогают: реализация модуля и техническая невозможность
  альтернативы (книга добавляет — или несоразмерно высокая цена правильного решения). Оба требуют
  отдельного обоснования. Рабочая эвристика книги: всё в `/bitrix/` — ядро; менять можно только
  `/local/`, технический `/bitrix/.settings_extra.php` и файлы, которые разработчик добавил сам
  ([Ядро продукта](https://bx24devbook.website.yandexcloud.net/Obsie_svedenia/Adro_produkta.html#osobye-fajly)).

## Связанное
- [[concept-change-invasiveness-hierarchy]] — почему всё живёт здесь
- [[entity-php-interface]] — самая используемая часть каталога

[← Ядро D7](_index-core-d7.md)
