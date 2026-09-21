---
title: "Конспект: «Книга разработчика», UI-подсистема — тулбар, кнопки, фильтр, таблицы"
type: source-summary
module: templates-design
edition: box
status: verified
provenance: documented
verified: "2026-09-21 / сверено постранично с сайтом книги (bx24devbook), снимок-манифест 2026-09-21"
tags: [ui, тулбар, фильтр, грид, кнопки, свой-фильтр]
sources: []
related: ["[[concept-ui-subsystem]]", "[[entity-toolbar]]", "[[entity-ui-button]]", "[[entity-filter-component]]", "[[entity-filter-options]]", "[[entity-filter-field-adapter]]", "[[entity-bx-main-filter]]", "[[entity-custom-filter]]", "[[entity-grid-component]]", "[[entity-grid-options]]", "[[entity-bx-main-grid]]", "[[recipe-custom-list-page-filter-grid]]", "[[concept-deferred-functions-and-page-areas]]", "[[entity-site-template]]"]
aliases: []
updated: "2026-09-21"
---

# Конспект: UI-подсистема

## Об источнике
| Поле | Значение |
|------|----------|
| Тип | «Книга разработчика Bitrix24» — эталонный источник (`CLAUDE.md` §9); авторская книга, не официальная документация |
| Автор | Андрей Николаев |
| Страницы | [Введение](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Vvedenie.html) · [Тулбар](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tulbar/Osnovnoe.html) · [Кнопки](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Knopki.html) · Фильтр: [о модуле](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Filtr/O_module.html), [обзор](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Filtr/Obzor.html), [фильтры пользователя](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Filtr/Filtry_polzovatela.html), [публичная часть](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Filtr/Publicnaa_cast.html), [свой фильтр](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Filtr/Svoj_filtr.html) · Таблицы: [основное](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tablicy/Osnovnoe.html), [обзор](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tablicy/Obzor.html), [панель действий](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tablicy/Panel_dejstvij.html), [персональные настройки](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tablicy/Personalnye_nastrojki.html), [публичная часть](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tablicy/Publicnaa_cast.html) |
| Дата | дат нет; примеры — март–октябрь 2023; тулбар — «с 19 версии» |
| Где лежит | снимок-манифест (без текста): [`raw/sources/2026-09-21-bx24devbook-manifest.md`](../../raw/sources/2026-09-21-bx24devbook-manifest.md) |

**Как получен.** Заведён 2026-09-18 переносом из архивной вики — первичный источник тогда не
перечитывался, в «Об источнике» по ошибке стоял курс 43. **2026-09-21 сверен постранично с сайтом
книги** (все 13 страниц раздела UI). Страницы «Шаблон» и «Отложенные функции» относятся к другому
разделу книги — они в [[source-devbook-core-d7]].

## TL;DR
Для большинства интерфейсных задач в продукте уже есть готовый компонент; по оценке автора книги
это экономит до 30 % времени на новых функциях. Своя страница на штатных тулбаре, фильтре и гриде
визуально не отличается от продукта и бесплатно получает пресеты, групповые действия и сохранение
настроек пользователя.

## Карта страниц книги
| Страница | О чём | Куда встроено |
|----------|-------|---------------|
| Введение | четыре инструмента: тулбар, фильтр, таблицы, кнопки | [[concept-ui-subsystem]] |
| Тулбар | место между `above_pagetitle` и `below_pagetitle`; условия вывода; фасад и `Manager`; свой HTML | [[entity-toolbar]] |
| Кнопки | массив и объект; обработчики и их приоритет; иконки и схлопывание; меню | [[entity-ui-button]] |
| Фильтр: обзор | параметры компонента, `CONFIG`, типы полей, пресеты, темы, `apply_filter` | [[entity-filter-component]], [[entity-filter-field-adapter]] |
| Фильтр: пользователя | `b_user_option`, структура конфигурации, `Options`; «демонстрационный» путь `getFilter*()` | [[entity-filter-options]] |
| Фильтр: публичная часть | `BX.Main.filterManager`, поле, пресеты и значения, события | [[entity-bx-main-filter]] |
| Свой фильтр | `\Bitrix\Main\Filter\Filter`, провайдеры, фабрика и событие; всегда наследник | [[entity-custom-filter]], [[recipe-custom-list-page-filter-grid]] |
| Таблицы: обзор | параметры грида, колонки и строки (`actions` — меню строки), навигация | [[entity-grid-component]] |
| Панель действий | `ACTION_PANEL`, типы, `ONCHANGE`, `Snippet` | [[entity-grid-component]], [[entity-bx-main-grid]] |
| Персональные настройки | `Grid\Options`: колонки, сортировка, размер страницы | [[entity-grid-options]] |
| Таблицы: публичная часть | `BX.Main.gridManager`, выделение, события, грид через AJAX | [[entity-bx-main-grid]] |

## Ключевые тезисы
- «UI» — не один модуль: тулбар и кнопки — `ui`, фильтр и грид — `main`.
- **Тулбар** выводится между зонами `above_pagetitle` и `below_pagetitle` при трёх условиях: шаблон
  Bitrix24, пустые буферы `pagetitle`/`inside_pagetitle`/`in_pagetitle`, буферизация ещё идёт; вне
  шаблона компонент тулбара вызывают явно. Фасад работает только с `default-toolbar`; для своего
  HTML — экземпляр через `Manager`, HTML — заранее безопасный.
- **Кнопки:** обработчик `click` < `onclick` < `events['click']`; кнопке без иконки нужен
  `toolbar-collapsed-icon`, иначе она не схлопнется.
- **Фильтр:** настройки пользователя — в `b_user_option`; пресеты индивидуальны и не пересчитываются.
  «Устаревшим» автор называет получение значений через `Options::getFilter*()`, в новом коде — «свой
  фильтр» (`\Bitrix\Main\Filter\Filter::getValue()`), и советует всегда наследоваться от `Filter`.
  `FIELDS` или `FILTER` для полей — книга противоречит сама себе.
- **Грид:** меню строки — ключ `actions` в строке, групповые действия — `ACTION_PANEL`; без
  `default` колонка скрыта; `ENABLE_COLLAPSIBLE_ROWS` не использовать; грид через AJAX теряет
  пагинацию — лечится `Grid::beforeRequest`.
- **`Grid\Options::getUsedColumns()`** позволяет не считать невидимые колонки: кейс автора — до 60 %
  времени построения грида на столбцах, нужных меньше чем 1 % сотрудников.

## Что встроено в вики
- Концепт: [[concept-ui-subsystem]].
- Карточки: [[entity-toolbar]], [[entity-ui-button]], [[entity-filter-component]],
  [[entity-filter-options]], [[entity-filter-field-adapter]], [[entity-bx-main-filter]],
  [[entity-custom-filter]], [[entity-grid-component]], [[entity-grid-options]], [[entity-bx-main-grid]].
- Рецепт (новый): [[recipe-custom-list-page-filter-grid]] — своя страница-список.

## Сверка 2026-09-21
| Страница вики | Что было | Что в книге | Решение |
|---------------|----------|-------------|---------|
| все страницы кластера | `verified`: dev.1c-bitrix.ru; «документация рекомендует/называет» | авторская книга; оценки и оговорки — автора | атрибуция исправлена |
| [[entity-grid-component]] | «`ACTIONS_LIST`/`ACTIONS` — действия строки» | меню строки — `actions` в каждой строке | исправлено |
| [[entity-toolbar]], [[concept-ui-subsystem]], [[entity-site-template]] | тулбар «занимает» `above/below_pagetitle` | стоит между ними, зоны свободны | исправлено |
| [[concept-ui-subsystem]] | «вне шаблона Bitrix24 подсистема не работает» | так — только про тулбар | исправлено |
| [[entity-filter-options]], [[entity-custom-filter]] | `Options` «устарел» целиком; `getFilterLogic()` — «самое ценное» | устаревшим назван только путь получения значений | исправлено |
| [[entity-grid-options]] | «класс только читает» | `set*`/`save()` есть, книга их не разбирает | исправлено |
| [[entity-bx-main-grid]] | `prependRowEditor()` «молча ничего не сделает» | добавит пустую строку | исправлено |
| [[entity-toolbar]] | две локации кнопок; «90 %»; `includeModule('ui')` из книги | три локации; числа нет; `includeModule` в книге нет | исправлено; `includeModule` — практика команды |

## Противоречия внутри книги
- `FIELDS` и `FILTER` для полей фильтра (и `FILTER_ROWS` описан дважды).
- Строки грида: по таблице вывод из `columns`, в полном примере — только `data` с ключами `~`.
- Устаревших буферов у тулбара три (`pagetitle`, `inside_pagetitle`, `in_pagetitle`), на странице
  отложенных функций — два.
- В примерах кода ключи в обратных кавычках (в PHP это выполнение shell-команды) и пропущенные
  запятые — примеры книги не копировать.

## Открытые вопросы
- Какой ключ полей фильтра реально читает компонент (`FIELDS` / `FILTER`).
- Методы изменения `Grid\Options` и видимость `prepareFilterValue` — сверить с официальной
  документацией (книга даёт на неё ссылки).
- Глава о ленивой загрузке фильтра (`LAZY_LOAD`) в книге не дописана.

[← Конспекты источников](_index-sources.md)
