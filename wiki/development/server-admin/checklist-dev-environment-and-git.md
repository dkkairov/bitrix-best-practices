---
title: "Окружение разработки и git"
type: checklist
module: server-admin
edition: box
status: verified
provenance: empirical
verified: "2026-06-20 / эмпирика"
tags: [окружение, git, деплой, local, разработка]
sources: []
related: ["[[recipe-module-versioning-and-private-distribution]]", "[[antipattern-box-core-modification]]"]
aliases: []
updated: "2026-06-20"
---

# Окружение разработки и git

**Когда применять:** старт разработки под коробку в команде — единые среда, git и порядок выкладки.

## Окружение
- [ ] Локальная среда повторяет прод по версиям (PHP/MySQL) — `bitrix-env` или Docker (BitrixDock)
- [ ] Отдельные среды dev / staging / prod; конфиги вынесены по средам (`.settings.php`/`dbconn`)
- [ ] У каждого разработчика — изолированная копия, не общий прод

## Git: что версионируем
- [ ] Версионируем **`/local`** (php_interface, modules, components, templates) и свои модули
- [ ] **Не** версионируем ядро `/bitrix/` (кроме своих модулей в `/bitrix/modules`, если так принято),
      `upload/`, кэш, логи, локальные конфиги с паролями — через `.gitignore`
- [ ] Не правим ядро ([[antipattern-box-core-modification|почему]]) — git-ревью отклоняет диффы в `/bitrix/`
- [ ] Секреты (ключи, пароли) — вне репозитория

## Ветки и ревью
- [ ] Принятая модель веток (например, trunk-based или feature-ветки), защищённый основной бранч
- [ ] Обязательное код-ревью; проверки стиля/тестов в CI ([[concept-testing-approach|тесты]])

## Выкладка (деплой)
- [ ] Деплой **собранными артефактами** по тегу версии, а не правкой файлов на проде
- [ ] После выкладки кода — применение миграций ([[recipe-migrations-as-code|миграции как код]])
- [ ] Реестр «клиент → версия модулей» для приватной дистрибуции
      ([[recipe-module-versioning-and-private-distribution|дистрибуция]])

## Критерии приёмки
- Новый разработчик поднимает среду по инструкции за разумное время.
- Любая правка проходит git → ревью → CI → деплой; прод руками не редактируется.

## Связанное
- [[recipe-module-versioning-and-private-distribution]], [[concept-dev-standards|Стандарт разработки]]

[← Администрирование сервера](_index-server-admin.md)
