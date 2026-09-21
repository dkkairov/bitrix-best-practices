---
title: "Окружение разработки и git"
type: checklist
module: server-admin
edition: box
status: verified
provenance: mixed
verified: "2026-06-20 / эмпирика; 2026-09-21 / сверено с «Книгой разработчика Bitrix24» (bx24devbook): GIT, Введение в разработку, Свой код"
tags: [окружение, git, деплой, local, разработка, gitignore, env]
sources: ["[[source-devbook-dev-rules]]"]
related: ["[[recipe-module-versioning-and-private-distribution]]", "[[antipattern-box-core-modification]]", "[[entity-local-directory]]", "[[pattern-local-solution-structure]]", "[[recipe-git-deploy-to-production]]"]
aliases: []
updated: "2026-09-21"
---

# Окружение разработки и git

**Когда применять:** старт разработки под коробку в команде — единые среда, git и порядок выкладки.

> **Сверено с «Книгой разработчика» 2026-09-21.** Добавлены `.gitignore` из книги и правило про
> `.env`. Исправлено: раньше в git предлагалось класть только `/local` и допускались «свои модули в
> `/bitrix/modules`». По книге в репозитории — `/local` **и публичная часть**, а `/bitrix/*` исключён
> целиком ([GIT](https://bx24devbook.website.yandexcloud.net/Razrabotka/GIT.html)).

## Окружение
- [ ] Минимум две независимые копии: **боевая** (пользуются) и **тестовая** (разрабатывают). На боевом
      сайте правят только контент, всё остальное — на копии и переносится автоматически
      ([Введение в разработку](https://bx24devbook.website.yandexcloud.net/Razrabotka/Vvedenie.html#nel-za-pravit-na-boevom-sajte-vse-cto-ne-kasaetsa-kontenta)).
      Для тестовой копии книга упоминает параметр «Установка для разработки».
- [ ] Локальная среда повторяет прод по версиям (PHP/MySQL) — `bitrix-env` или Docker. С 2025 года
      есть официальное Docker-окружение 1С-Битрикс — [[recipe-box-test-stand-docker|рецепт стенда]];
      сторонний BitrixDock — запасной вариант
- [ ] Отдельные среды dev / staging / prod; настройки ядра (`/bitrix/.settings.php`, `dbconn.php`) у
      каждой среды свои и в git не попадают
- [ ] Конфигурация проекта, зависящая от среды (адреса, доступы внешних сервисов, флаги) — в **`.env` выше
      `DOCUMENT_ROOT`**, загружается в `kernel.php`; в git не кладётся
      ([Свой код → kernel.php](https://bx24devbook.website.yandexcloud.net/Razrabotka/Struktura_papki_local/Svoj_kod.html#kernel-php),
      [[pattern-local-solution-structure]])
- [ ] `.env` **не попадает в штатный бэкап** — доставка и восстановление файла описаны отдельно
- [ ] У каждого разработчика — изолированная копия, не общий прод
- [ ] Работы на сервере — только с SSH-доступом и свежим бэкапом (книга относит работу без них к
      частым ошибкам)

## Git: что версионируем
- [ ] В репозитории — **`/local`** (php_interface, modules, components, templates…) **и публичная
      часть сайта**; вне git — ядро, загрузки и автоматически генерируемые правила ЧПУ
- [ ] Целевой `.gitignore` по книге — минимальный:
      ```gitignore
      *.log
      *.txt
      .htsecure
      /bitrix/*
      /urlrewrite.php
      /upload
      ```
      В книге есть и подробный вариант (кэши, служебные каталоги `/bitrix/…`, `dbconn.php`,
      `after_connect*.php`) — он гибче, но к минимальному стоит стремиться.
- [ ] Свои модули — в `/local/modules/`, не в `/bitrix/modules/` (`/bitrix/*` вне git)
- [ ] Не правим ядро ([[antipattern-box-core-modification|почему]]) — git-ревью отклоняет диффы в `/bitrix/`
- [ ] Секреты (ключи, пароли) — вне репозитория: в `.env` и в БД

## Ветки и ревью
- [ ] Принятая модель веток (например, trunk-based или feature-ветки), защищённый основной бранч
- [ ] Обязательное код-ревью; проверки стиля/тестов в CI ([[concept-testing-approach|тесты]])

## Выкладка (деплой)
- [ ] Деплой **собранными артефактами** по тегу версии, а не правкой файлов на проде
- [ ] После выкладки кода — применение миграций или инсталлеров ([[recipe-migrations-as-code|миграции как код]])
- [ ] Реестр «клиент → версия модулей» для приватной дистрибуции
      ([[recipe-module-versioning-and-private-distribution|дистрибуция]])

## Критерии приёмки
- Новый разработчик поднимает среду по инструкции за разумное время.
- Любая правка проходит git → ревью → CI → деплой; прод руками не редактируется.

## Связанное
- [[entity-local-directory]] — что лежит в `/local`
- [[recipe-git-deploy-to-production]] — доставка правки на прод
- [[recipe-module-versioning-and-private-distribution]], [[concept-dev-standards|Стандарт разработки]]

[← Администрирование сервера](_index-server-admin.md)
