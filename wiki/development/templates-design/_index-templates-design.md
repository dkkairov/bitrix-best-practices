---
title: "Шаблоны и вёрстка"
type: index
module: templates-design
edition: box
status: verified
updated: "2026-09-21"
---

# Шаблоны и вёрстка — практики разработки (коробка)

Шаблон дизайна, UI-подсистема продукта (тулбар, фильтр, грид, кнопки), отложенные функции,
подключение своих стилей и скриптов.

## Что покрываем
- UI-подсистема: что уже есть в продукте и не надо писать заново
- Тулбар, фильтр, грид: минимальные примеры и подводные камни
- Отложенные функции и зоны страницы
- Подключение своих CSS/JS без правки шаблона продукта

## Страницы

### Обзор
- [[concept-ui-subsystem|UI-подсистема: тулбар, фильтр, грид, кнопки]]
- [[recipe-custom-list-page-filter-grid|Своя страница-список: фильтр, грид, тулбар]] · черновик
- Зоны страницы и отложенный вывод — [[concept-deferred-functions-and-page-areas]] (в разделе «Ядро D7»)

### Классы и компоненты (справочник API)
- [[entity-toolbar|UI\Toolbar]] — шапка страницы: Manager, Toolbar, Facade
- [[entity-ui-button|UI\Buttons\Button]] — кнопки
- [[entity-filter-component|bitrix:main.ui.filter]] — компонент фильтра
- [[entity-filter-field-adapter|FieldAdapter]] — типы полей фильтра
- [[entity-filter-options|UI\Filter\Options]] — настройки фильтра пользователя
- [[entity-custom-filter|Main\Filter]] — свой фильтр (рекомендуемый путь)
- [[entity-bx-main-filter|BX.Main.Filter]] — JS-API фильтра
- [[entity-grid-component|bitrix:main.ui.grid]] — компонент грида
- [[entity-grid-options|Main\Grid\Options]] — пресеты и сортировка
- [[entity-bx-main-grid|BX.Main.gridManager]] — JS-API грида
- [[entity-site-template|Шаблон дизайна]] — зоны страницы и почему его не правят
- [[entity-theme-picker|ThemePicker]] — пользовательские темы оформления

## Статус покрытия
Есть обзор UI-подсистемы, рецепт своей страницы-списка (фильтр + грид + тулбар, черновик) и
справочник по двенадцати классам и компонентам; отложенные функции и зоны страницы — в разделе
«Ядро D7». Страницы сверены с «Книгой разработчика» 2026-09-21. Не хватает: подключения своих стилей
и скриптов, панели групповых действий грида отдельной страницей.

[← Обзор вики](../../../index.md)
