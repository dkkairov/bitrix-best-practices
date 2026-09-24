---
title: "Организация кода: пространства имён и автозагрузка"
type: concept
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-06-19 / Bitrix Framework (курс 43); раздел «Где размещать свой код» — 2026-09-21 / сверено с «Книгой разработчика Bitrix24» (bx24devbook): Структура папки local"
tags: [d7, namespaces, автозагрузка, local, разработка]
sources: ["[[source-bxfw-course43-namespaces]]", "[[source-devbook-dev-rules]]"]
related: ["[[antipattern-box-core-modification]]", "[[recipe-module-structure-and-install]]", "[[pattern-local-solution-structure]]", "[[entity-local-directory]]"]
aliases: ["code-namespaces-and-autoloading"]
updated: "2026-09-24"
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
- **Целое пространство имён по PSR-4** регистрируется одной строкой (метод есть в ядре 26.750.0,
  документация фреймворка называет его основным способом):
  ```php
  \Bitrix\Main\Loader::registerNamespace('Vendor\\Module', '/local/modules/vendor.module/lib');
  ```
  Парный метод — `unregisterNamespace()`. Регистрацию кладём в `include.php` модуля: ядро
  подключает этот файл при `includeModule()`.
- **Порядок поиска класса:** сначала то, что зарегистрировано через `registerAutoLoadClasses`,
  затем PSR-4-пространства, и только потом ошибка «класс не найден». Отсюда практическое следствие:
  если класс упорно «не находится», сначала проверяем, какой из двух механизмов должен был его
  отдать.

## Сокращение путей через `use`
```php
use Bitrix\Main\Localization\Loc;

Loc::getMessage('CODE'); // вместо \Bitrix\Main\Localization\Loc::getMessage(...)
```
Допустимы: полный путь `\Bitrix\Main\Application::getInstance()`
([[entity-main-application|о самом классе]]), сокращённый
`Main\Application::...` (если код уже в пространстве `Bitrix`), либо алиас через `use`.

## Где размещать свой код

Только в **`/local/`** — это «зеркало» `/bitrix/` для своего кода: ядро ищет компоненты, шаблоны,
модули и обработчики в `/local/` так же, как в `/bitrix/`, причём **`/local/` имеет приоритет** (если
элемент есть в обеих папках, берётся из `/local/`). **Не править `/bitrix/`** — это антипаттерн
[[antipattern-box-core-modification|Правка ядра коробки]].

Рекомендуемая структура:

```text
local/
├── php_interface/        точка входа проекта (структура решения — см. ниже)
│   ├── init.php          автоподключается ядром: только автозагрузчик и подключение файлов
│   ├── events.php        подписки на события; kernel.php — .env и сервисы; classes/ — классы
│   └── <lang>/           языковые файлы (message.php) — опционально
├── modules/              свои модули (аналог /bitrix/modules/)
│   └── vendor.module/    lib/ install/ lang/ options.php (структура — см. рецепт ниже)
├── components/           свои компоненты (аналог /bitrix/components/)
│   └── vendor/component/ component.php, .parameters.php, templates/
├── templates/            шаблоны сайта
│   └── <template_id>/    header.php, footer.php, .style.css, components/
├── activities/           пользовательские действия бизнес-процессов (BP activity)
├── blocks/               блоки для сайтов
├── js/                   JS-расширения (грузятся через \Bitrix\Main\UI\Extension::load)
└── gadgets/              гаджеты рабочего стола (legacy)
```

> **Сверено с книгой 2026-09-21.** Список каталогов приведён к перечню «Книги разработчика»:
> `activities`, `blocks`, `components`, `gadgets`, `js`, `modules`, `php_interface`, `templates`;
> других каталогов книга заводить не рекомендует (исключение — `tools` для технических скриптов)
> ([Структура папки local](https://bx24devbook.website.yandexcloud.net/Razrabotka/Struktura_papki_local/Osnovnoe.html#podderzivaemye-direktorii)).
> Убран `wizards/` (в перечне книги его нет), добавлен `blocks/`. Подробно — [[entity-local-directory]].

- **Минимум для старта:** `php_interface/init.php` — точка входа: автозагрузчик и подключение
  `events.php`/`kernel.php`. Остальные папки заводят по мере надобности, заранее их создавать не нужно.
- **Переиспользуемый** функционал оформляйте отдельным модулем `vendor.module` с пространством имён
  `Vendor\Module` — [[recipe-module-structure-and-install|Структура модуля и установка]]. Код под
  одного клиента — решением в `/local/php_interface`: [[pattern-local-solution-structure]].

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
