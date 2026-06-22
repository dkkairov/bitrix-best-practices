---
title: "Модульная разработка: когда и как"
type: pattern
module: modules-custom
edition: box
status: verified
provenance: mixed
verified: "2026-06-20 / Bitrix Framework (курс 43)"
tags: [модуль, local, дистрибуция, переиспользование, коробка]
sources: ["[[source-bxfw-course43-modules]]"]
related: ["[[recipe-module-structure-and-install]]", "[[recipe-module-versioning-and-private-distribution]]", "[[antipattern-box-core-modification]]"]
aliases: []
updated: "2026-06-20"
---

# Модульная разработка: когда и как

## Проблема и контекст
Нужна функциональность, которую предстоит **переиспользовать и массово ставить на коробки клиентов**.
Если разложить её по `/local` точечно, переносить и обновлять на десятках порталов тяжело и ненадёжно.

## Решение
Оформлять переиспользуемую функциональность **отдельным модулем** `vendor.module`. Модуль даёт:
- жизненный цикл `install`/`uninstall` (БД, события, агенты, права, опции) —
  [[recipe-module-structure-and-install|Структура модуля и установка]];
- версионирование и распространение — [[recipe-module-versioning-and-private-distribution|Версии и дистрибуция]];
- изоляцию (пространство имён `Vendor\Module`, без правки ядра — [[antipattern-box-core-modification|Правка ядра]]).

## Когда модуль
- Функциональность **переиспользуется** на нескольких проектах/коробках.
- Нужна воспроизводимая установка и обновление у многих клиентов.
- Есть своя БД-структура, события, агенты, настройки.

## Когда НЕ модуль (достаточно `/local`)
- Разовая правка под одного клиента → `/local/php_interface/init.php` и `/local/` без модуля.
- Маленький обработчик события, не требующий установки и переноса.
- Оверинжиниринг: не заворачивать в модуль каждую мелочь.

## `/local/modules` vs `/bitrix/modules`
- **`/local/modules/vendor.module`** — своё, не затирается обновлениями платформы. **Рекомендация по
  умолчанию** для приватных решений.
- **`/bitrix/modules/vendor.module`** — требуется для **тиражных решений marketplace.1c-bitrix.ru**
  (там свои правила упаковки и обновлений).

## Последствия
- **Плюсы:** воспроизводимая установка, обновляемость, изоляция, готовность к дистрибуции.
- **Минусы:** оверхед на структуру — не оправдан для разовых мелочей.

## Связанное
- [[recipe-module-structure-and-install]], [[recipe-module-versioning-and-private-distribution]]
- [[concept-dev-standards|Стандарт разработки]]

[← Свои модули](_index-modules-custom.md)
