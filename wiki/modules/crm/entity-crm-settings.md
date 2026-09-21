---
title: "\\Bitrix\\Crm\\Settings\\<Type>Settings"
type: entity
module: crm
edition: box
status: verified
provenance: mixed
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Модуль CRM — Универсальное API / Как включить; Лид — методы (отложенное удаление)"
tags: [crm, настройки, universal-api, класс]
sources: ["[[source-devbook-crm]]"]
related: ["[[concept-crm-universal-api]]", "[[pattern-crm-action-vs-event]]", "[[entity-crm-factory]]"]
aliases: ["bitrix24-crm-settings"]
updated: "2026-09-21"
---

# `\Bitrix\Crm\Settings\<Type>Settings`

**Что это:** пер-сущностные настройки CRM. Практически важен один вопрос, на который они
отвечают: **включён ли Universal API для лида, сделки, контакта, компании**
([Как включить](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Kak_vklucit.html);
атрибуция исправлена при сверке 2026-09-21).

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | классы-синглтоны: `DealSettings`, `LeadSettings`, `ContactSettings`, `CompanySettings` |
| Модуль | `crm` |
| Получение | `DealSettings::getCurrent()` |
| Edition | box |

Для смарт-процессов, счетов, предложений и документов подсистемы подписи переключатель не нужен —
там Universal API поддерживается полностью. (Раньше здесь утверждалось, что у них нет своего
Settings-класса; книга этого не говорит — снято.)

## Зачем это проверять

Код, написанный под [[concept-crm-universal-api|Universal API]], для сделки или лида на конкретном
портале может не работать вообще: если фабрика выключена, сущность живёт на старом API, и ваши
действия операций просто не вызовутся. Это первое, что проверяют перед проектированием доработки.

```php
use Bitrix\Crm\Settings;
use Bitrix\Main\Loader;

Loader::requireModule('crm');

var_dump([
    'deal'    => Settings\DealSettings::getCurrent()->isFactoryEnabled(),
    'lead'    => Settings\LeadSettings::getCurrent()->isFactoryEnabled(),
    'contact' => Settings\ContactSettings::getCurrent()->isFactoryEnabled(),
    'company' => Settings\CompanySettings::getCurrent()->isFactoryEnabled(),
]);
```

Тот же флаг виден через старые классы: `(new \CCrmDeal())->isUseOperation()`.

## Переключение

```php
\Bitrix\Crm\Settings\DealSettings::getCurrent()->setFactoryEnabled(true);
```

Есть и URL-механизм: параметр `?enableFactory=Y` / `?enableFactory=N` в адресе раздела CRM.

Ещё одна роль настроек: `<Type>Settings::getCurrent()->isDeferredCleaningEnabled()` задаёт значение
по умолчанию для опции `ENABLE_DEFERRED_MODE` в `CCrm*::Delete` ([[recipe-crm-legacy-entity-crud]]).

## Подводные камни

- **Переключить настройку может любой пользователь с доступом в CRM** — не только администратор.
  На проде это означает, что режим работы сущности может измениться без вашего ведома; если
  доработка от него зависит, проверяйте флаг в рантайме, а не один раз при установке.
- Включение Universal API меняет путь сохранения сущности. Книга обещает, что старый код продолжит
  работать, а о несовместимостях просит сообщать в техподдержку; **практика команды** — всё равно
  переключать на тестовом стенде и проверять обработчики и автоматизацию.

## Связанное
- [[concept-crm-universal-api]] — что именно включает этот флаг
- [[pattern-crm-action-vs-event]] — почему от него зависит выбор способа доработки

[← CRM](_index-crm.md)
