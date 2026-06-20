---
id: "D03-RC01"
title: "Структура модуля и установка"
type: recipe
module: modules-custom
edition: box
status: verified
confidence: high
provenance: mixed
verified: "2026-06-20 / Bitrix Framework (курс 43)"
tags: [модуль, install, version, события, опции, агенты]
sources: ["[[s-ss03-src-bxfw-course43-modules]]"]
related: ["[[d03-pt01-module-based-development-standard]]", "[[d03-rc02-module-versioning-and-private-distribution]]", "[[d01-cn01-code-namespaces-and-autoloading]]"]
aliases: []
updated: "2026-06-20"
---

# Структура модуля и установка

**Результат:** корректно устанавливаемый/удаляемый модуль `vendor.module` с классами, событиями,
опциями и агентами.

## Предусловия
- Понимание пространств имён и автозагрузки — [[d01-cn01-code-namespaces-and-autoloading|Пространства имён]].
- Решено, что нужен именно модуль — [[d03-pt01-module-based-development-standard|когда модуль]].

## Структура каталога
```
local/modules/vendor.module/
├── include.php                # константы, базовая инициализация
├── lib/                       # классы (неймспейс Vendor\Module), автозагрузка по пути
├── lang/ru/                   # языковые файлы
├── options.php                # страница настроек в админке (опционально)
├── admin/                     # админ-скрипты/меню (опционально)
└── install/
    ├── version.php            # версия и дата
    ├── index.php              # класс установки (CModule)
    ├── db/                    # SQL установки/удаления (если своя БД)
    └── components/            # компоненты модуля (если есть)
```

## `install/version.php`
```php
<?php
$arModuleVersion = [
    'VERSION'      => '1.0.0',
    'VERSION_DATE' => '2026-06-20 00:00:00',
];
```

## `install/index.php` (скелет)
```php
<?php
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\EventManager;

Loc::loadMessages(__FILE__);

class vendor_module extends CModule
{
    public $MODULE_ID = 'vendor.module';

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';
        $this->MODULE_VERSION      = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME         = Loc::getMessage('VENDOR_MODULE_NAME');
        $this->MODULE_DESCRIPTION  = Loc::getMessage('VENDOR_MODULE_DESC');
    }

    public function DoInstall()
    {
        ModuleManager::registerModule($this->MODULE_ID);
        $this->InstallEvents();
        // $this->InstallDB();  $this->InstallFiles();
    }

    public function DoUninstall()
    {
        $this->UnInstallEvents();
        // $this->UnInstallDB();  $this->UnInstallFiles();
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }

    public function InstallEvents()
    {
        EventManager::getInstance()->registerEventHandler(
            'crm', 'OnAfterCrmDealAdd', $this->MODULE_ID,
            \Vendor\Module\Handler\Deal::class, 'onAfterAdd'
        );
    }

    public function UnInstallEvents()
    {
        EventManager::getInstance()->unRegisterEventHandler(
            'crm', 'OnAfterCrmDealAdd', $this->MODULE_ID,
            \Vendor\Module\Handler\Deal::class, 'onAfterAdd'
        );
    }
}
```

## Опции и агенты
```php
use Bitrix\Main\Config\Option;
Option::set('vendor.module', 'API_KEY', $value);
$key = Option::get('vendor.module', 'API_KEY', '');

\CAgent::AddAgent('\\Vendor\\Module\\Agent::run();', 'vendor.module', 'N', 86400);
```

## Проверка результата
- Модуль виден и устанавливается в *Marketplace → Установленные решения*; повторная установка/удаление
  проходят без ошибок (идемпотентность).
- Обработчики событий зарегистрированы (срабатывают); опции читаются; агент в списке агентов.

## Откат и проблемы
- «Класс не найден» → проверь неймспейс/путь в `/lib/` и `Loader::includeModule`.
- Остатки после удаления → дочисти в `DoUninstall` (события, опции, агенты, БД).
- Структуру/данные **не** заливай в `install` намертво — выноси в миграции
  ([[d06-rc01-migrations-as-code|Миграции как код]]).

## Связанное
- [[d03-rc02-module-versioning-and-private-distribution]], [[d03-pt01-module-based-development-standard]]

[← Свои модули](../_index.md)
