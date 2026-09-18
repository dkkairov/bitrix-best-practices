---
title: "\\Bitrix\\Crm\\Settings\\<Type>Settings"
type: entity
module: crm
edition: box
status: verified
provenance: documented
verified: "2026-06-02 / документация модуля CRM (apidocs.bitrix24.ru)"
tags: [crm, настройки, universal-api, класс]
sources: []
related: ["[[concept-crm-universal-api]]", "[[pattern-crm-action-vs-event]]", "[[entity-crm-factory]]"]
aliases: ["bitrix24-crm-settings"]
updated: "2026-09-18"
---

# `\Bitrix\Crm\Settings\<Type>Settings`

**Что это:** пер-сущностные настройки CRM. Практически важен один вопрос, на который они
отвечают: **включён ли Universal API для лида, сделки, контакта, компании.**

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | классы-синглтоны: `DealSettings`, `LeadSettings`, `ContactSettings`, `CompanySettings` |
| Модуль | `crm` |
| Получение | `DealSettings::getCurrent()` |
| Edition | box |

У смарт-процессов, счетов, предложений и документов своего Settings-класса нет — там Universal API
включён **всегда**.

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

Есть и URL-механизм: `?enableFactory=Y` / `?enableFactory=N`.

## Подводные камни

- **Переключить настройку может любой пользователь с доступом в CRM** — не только администратор.
  На проде это означает, что режим работы сущности может измениться без вашего ведома; если
  доработка от него зависит, проверяйте флаг в рантайме, а не один раз при установке.
- Включение Universal API меняет путь сохранения сущности: обработчики событий, написанные под
  старый API, могут начать вести себя иначе. Переключать на тестовом стенде и проверять
  автоматизацию.

## Связанное
- [[concept-crm-universal-api]] — что именно включает этот флаг
- [[pattern-crm-action-vs-event]] — почему от него зависит выбор способа доработки

[← CRM](_index-crm.md)
