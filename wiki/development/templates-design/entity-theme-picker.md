---
title: "ThemePicker — темы оформления портала"
type: entity
module: templates-design
edition: box
status: verified
provenance: documented
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Модуль Интранет — Темы (основное, перекрытие); примеры книги 2020–2021, в дизайне Air не проверялось"
tags: [темы, оформление, интранет, класс, d7]
sources: ["[[source-devbook-intranet]]"]
related: ["[[entity-site-template]]", "[[concept-change-invasiveness-hierarchy]]", "[[concept-org-structure]]"]
aliases: ["bitrix24-theme-picker"]
updated: "2026-09-21"
---

# `ThemePicker`

**Что это:** API пользовательских и групповых тем оформления портала
([Темы](https://bx24devbook.website.yandexcloud.net/Modul_Intranet/Temy/Osnovnoe.html)). Тема — набор
CSS, который выводится после стилей шаблона и меняет только внешний вид; сейчас работает только в
шаблоне `bitrix24`.

> **Уточнено 2026-09-21 при сверке с книгой.** Раньше страница называла темы «штатным механизмом
> смены оформления вместо правки шаблона». По книге тема — пользовательская настройка (цвет текста,
> фон, подложка); **управлять составом тем разработчику штатно нечем**, это не инструмент
> кастомизации интерфейса. Добавлены: тема по умолчанию, создание и удаление темы, групповая тема
> меняется без проверки прав. Атрибуция исправлена.

## Ключевые факты
| Поле | Значение |
|------|----------|
| Полное имя | `\Bitrix\Intranet\Integration\Templates\Bitrix24\ThemePicker` |
| Модуль | `intranet` |
| Работает с шаблоном | `bitrix24` |
| Константы | `ENTITY_TYPE_USER`, `ENTITY_TYPE_SONET_GROUP`, `MAX_CUSTOM_THEMES` (40 на момент написания книги; вендорские темы не считаются) |

## Создание объекта

```php
// текущий сайт
$picker = ThemePicker::getInstance();

// явно (типы параметров в книге не указаны)
new ThemePicker($templateId, $siteId = false, $userId = 0, $entityType, $entityId = 0, $params = []);
```

По таблице книги `$entityId` нужен для группы, но в её примере тема **пользователя** тоже задаётся
через `$entityId` (его ID) при `$userId = 0` — таблица и пример расходятся, проверить на стенде.

## Методы

| Метод | Назначение |
|---|---|
| `getList()` | все доступные темы |
| `getCurrentThemeId()` | ID текущей, например `light:tulips`; у пользовательской темы — вида `bitrix24:custom_<n>` |
| `getCurrentTheme()` | конфигурация: `title`, `previewImage`, размеры, `removable`, **`css[]`**, `prefetchImages[]` |
| `getCurrentBaseThemeId()` / `getCurrentSubThemeId()` | базовая группа и тема внутри неё |
| `setCurrentThemeId($themeId, $currentUserId = 0)` | установить; `false`, если `entityId` не определён или темы нет |
| `create(...)` | создать пользовательскую тему (`bgColor`, `bgImage`, `textColor`); ошибка — `\Bitrix\Main\SystemException` |
| `remove($themeId)` | удалить; `false` — только для непользовательской темы |
| `canSetDefaultTheme()` | есть ли право менять тему по умолчанию |

Уровни: тема сотрудника (`ENTITY_TYPE_USER`), тема рабочей группы (`ENTITY_TYPE_SONET_GROUP`, видна
только при просмотре группы в слайдере) и тема по умолчанию для портала. В пользовательской теме
настраиваются только цвет текста (светлый/тёмный), цвет фона и подложка (jpg/png/gif до 20 МБ и
5000×5000).

## Подводные камни

- **Для группы `setCurrentThemeId()` меняет тему без проверки прав** (`$currentUserId` игнорируется) —
  права проверяйте сами.
- **`setCurrentThemeId()` возвращает `false` молча** — и когда темы не существует, и когда не
  определён `entityId`. Результат нужно проверять, иначе «тема не меняется» без объяснений.
- **Число пользовательских тем ограничено** (`MAX_CUSTOM_THEMES`) — при массовом создании упрётесь.
- **Базовая группа:** в одном месте книги — `default`/`light`/`black`, в другом — `dark`/`light`;
  брать через `getCurrentBaseThemeId()`, а не резать ID вручную.
- **Ограничить набор тем штатно нельзя.** Книга описывает обходной путь (копия тем в `/local/themes`
  и подмена класса через автозагрузчик и `eval`) и сама называет его некрасивым; он зависит от текста
  системного файла и ниже «параллельной разработки» в
  [[concept-change-invasiveness-hierarchy|иерархии изменений]] — без явной необходимости не применять
  ([Перекрытие](https://bx24devbook.website.yandexcloud.net/Modul_Intranet/Temy/Perekrytie.html)).

## Связанное
- [[entity-site-template]] — системный шаблон, поверх которого работают темы

[← Шаблоны и вёрстка](_index-templates-design.md)
