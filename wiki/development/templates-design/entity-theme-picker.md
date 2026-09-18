---
title: "ThemePicker — темы оформления портала"
type: entity
module: templates-design
edition: box
status: verified
provenance: documented
verified: "2026-06-01 / документация модуля intranet, раздел тем (dev.1c-bitrix.ru)"
tags: [темы, оформление, интранет, класс, d7]
sources: ["[[source-devbook-intranet]]"]
related: ["[[entity-site-template]]", "[[concept-change-invasiveness-hierarchy]]", "[[concept-org-structure]]"]
aliases: ["bitrix24-theme-picker"]
updated: "2026-09-18"
---

# `ThemePicker`

**Что это:** штатный механизм смены оформления портала — то, чем меняют внешний вид вместо правки
[[entity-site-template|системного шаблона]].

## Ключевые факты
| Поле | Значение |
|------|----------|
| Полное имя | `\Bitrix\Intranet\Integration\Templates\Bitrix24\ThemePicker` |
| Модуль | `intranet` |
| Работает с шаблоном | `bitrix24` |
| Константы | `ENTITY_TYPE_USER`, `ENTITY_TYPE_SONET_GROUP`, `MAX_CUSTOM_THEMES` |

## Создание

```php
// текущий сайт и сущность
$picker = ThemePicker::getInstance(ThemePicker::ENTITY_TYPE_USER);

// явно
new ThemePicker(
    string $templateId,                       // 'bitrix24'
    $siteId = false,                          // false → SITE_ID
    int $userId = 0,
    string $entityType = self::ENTITY_TYPE_USER,
    int $entityId = 0,
    array $params = []
);
```

## Методы

| Метод | Назначение |
|---|---|
| `getList()` | все доступные темы |
| `getCurrentThemeId()` | ID текущей, например `light:tulips` |
| `getCurrentTheme()` | конфигурация: `title`, `previewImage`, размеры, `removable`, **`css[]`**, `prefetchImages[]` |
| `getCurrentBaseThemeId()` / `getCurrentSubThemeId()` | базовая группа (`light`, `black`, `default`) и тема внутри неё |
| `setCurrentThemeId($themeId, $currentUserId = 0)` | установить; `false`, если `entityId` не определён или темы нет |

Темы задаются на двух уровнях: персонально сотруднику (`ENTITY_TYPE_USER`) и рабочей группе
(`ENTITY_TYPE_SONET_GROUP`).

## Подводные камни

- **`setCurrentThemeId()` возвращает `false` молча** — и когда темы не существует, и когда не
  определён `entityId`. Результат нужно проверять, иначе «тема не меняется» без объяснений.
- **Число пользовательских тем ограничено** (`MAX_CUSTOM_THEMES`) — при массовом создании упрётесь.
- ID темы составной (`группа:тема`) — сравнивать целиком, а не по частям.

## Связанное
- [[entity-site-template]] — почему темы, а не правка шаблона

[← Шаблоны и вёрстка](_index-templates-design.md)
