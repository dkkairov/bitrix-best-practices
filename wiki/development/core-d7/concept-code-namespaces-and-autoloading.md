---
title: "Организация кода: пространства имён и автозагрузка"
type: concept
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-06-19 / Bitrix Framework (курс 43)"
tags: [d7, namespaces, автозагрузка, local, разработка]
sources: ["[[source-bxfw-course43-namespaces]]"]
related: ["[[antipattern-box-core-modification]]", "[[recipe-module-structure-and-install]]"]
aliases: ["code-namespaces-and-autoloading"]
updated: "2026-06-20"
---

# Организация кода: пространства имён и автозагрузка

**TL;DR:** код в современном Bitrix организуется по пространствам имён `Vendor\Module`, классы лежат
в `/lib/` модуля и подключаются автозагрузкой; свой код размещайте в `/local/`, а не в ядре.

## Соглашения по пространствам имён
- Корень стандартных классов — `Bitrix`; каждый модуль = подпространство по имени: `main` →
  `Bitrix\Main`, `forum` → `Bitrix\Forum`.
- Партнёрские/свои модули — свой корень `Vendor\Module` (например, `Asd\Metrika`); id модуля при
  этом `vendor.module`.
- Именование: **UpperCamelCase**, только латиница; имена классов — существительные без лишних
  аббревиатур.
- Под-пространства (`Bitrix\Main\IO`, `Bitrix\Main\Entity\Validator`) — только когда оправдано
  архитектурой.

## Где лежат классы и как работает автозагрузка
- Классы модуля — в каталоге `/lib/`. Путь файла соответствует неймспейсу в нижнем регистре:
  `Vendor\Module\Sales\OrderTable` → `<module>/lib/sales/ordertable.php`.
- Перед использованием классов модуля подключите его:
  `\Bitrix\Main\Loader::includeModule('vendor.module')`.
- Для классов вне `/lib/` — явная регистрация автозагрузки:
  ```php
  \Bitrix\Main\Loader::registerAutoLoadClasses('vendor.module', [
      'Vendor\\Module\\Foo' => 'lib/foo.php',
  ]);
  ```

## Сокращение путей через `use`
```php
use Bitrix\Main\Localization\Loc;

Loc::getMessage('CODE'); // вместо \Bitrix\Main\Localization\Loc::getMessage(...)
```
Допустимы: полный путь `\Bitrix\Main\Application::getInstance()`, сокращённый
`Main\Application::...` (если код уже в пространстве `Bitrix`), либо алиас через `use`.

## Где размещать свой код

Только в **`/local/`** — это «зеркало» `/bitrix/` для своего кода: ядро ищет компоненты, шаблоны,
модули и обработчики в `/local/` так же, как в `/bitrix/`, причём **`/local/` имеет приоритет** (если
элемент есть в обеих папках, берётся из `/local/`). **Не править `/bitrix/`** — это антипаттерн
[[antipattern-box-core-modification|Правка ядра коробки]].

Рекомендуемая структура:

```text
local/
├── php_interface/        обработчики событий и низкоуровневая настройка
│   ├── init.php          автоподключается ядром: регистрация событий, автозагрузка, сервисы
│   └── <lang>/           языковые файлы (message.php) — опционально
├── modules/              свои модули (аналог /bitrix/modules/)
│   └── vendor.module/    lib/ install/ lang/ options.php (структура — см. рецепт ниже)
├── components/           свои компоненты (аналог /bitrix/components/)
│   └── vendor/component/ component.php, .parameters.php, templates/
├── templates/            шаблоны сайта
│   └── <template_id>/    header.php, footer.php, .style.css, components/
├── activities/           пользовательские действия бизнес-процессов (BP activity)
├── js/                   JS-расширения (грузятся через \Bitrix\Main\UI\Extension::load)
├── gadgets/              гаджеты рабочего стола (legacy)
└── wizards/              мастера первичной настройки (опционально)
```

- **Минимум для старта:** `php_interface/init.php` — точка входа для регистрации событий и автозагрузки.
  Остальные папки заводят по мере надобности, заранее их создавать не нужно.
- Свой функционал оформляйте отдельным модулем `vendor.module` с пространством имён `Vendor\Module` —
  внутренняя структура модуля в [[recipe-module-structure-and-install|Структура модуля и установка]].

## Как применять (чек-лист)
- [ ] Свой код — в `/local/`, не в ядре
- [ ] Классы модуля — в `/lib/`, путь = неймспейс в нижнем регистре
- [ ] `Loader::includeModule('vendor.module')` перед использованием классов
- [ ] UpperCamelCase, латиница, имена-существительные
- [ ] Длинные пути — через `use`

## Источник и связанное
- Конспект: [[source-bxfw-course43-namespaces|курс 43: пространства имён]] (курс 43, урок «Пространства имён»)
- [[concept-bitrix-framework-vs-bitrix24|Bitrix Framework vs Bitrix24]] — движок vs продукт, облако vs коробка
- [[antipattern-box-core-modification|Правка ядра коробки]] — почему свой код не в ядре

[← Ядро D7](_index-core-d7.md)
