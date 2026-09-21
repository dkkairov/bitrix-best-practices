---
title: "Структура модуля и установка"
type: recipe
module: modules-custom
edition: box
status: verified
provenance: mixed
verified: "2026-09-15 / коробка: main 26.700, PHP 8.2 (грабли /local/modules/); 2026-06-20 / курс 43 (скелет)"
tags: [модуль, install, version, события, опции, агенты]
sources: ["[[source-bxfw-course43-modules]]"]
related: ["[[pattern-module-based-development-standard]]", "[[pattern-module-library-monorepo]]", "[[recipe-module-versioning-and-private-distribution]]", "[[concept-code-namespaces-and-autoloading]]", "[[recipe-d7-orm-event-subscription]]", "[[pattern-module-self-disabling-guard]]"]
aliases: []
updated: "2026-09-18"
---

# Структура модуля и установка

**Результат:** корректно устанавливаемый/удаляемый модуль `vendor.module` с классами, событиями,
опциями и агентами.

## Предусловия
- Понимание пространств имён и автозагрузки — [[concept-code-namespaces-and-autoloading|Пространства имён]].
- Решено, что нужен именно модуль — [[pattern-module-based-development-standard|когда модуль]].

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
        // OnAfterCrmDealAdd — событие старого ядра: регистрируем …Compatible,
        // обработчик получает array &$fields (см. pattern-events-over-core-modification)
        EventManager::getInstance()->registerEventHandlerCompatible(
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

## Обязательный минимум, без которого модуль не виден

Bitrix строит список доступных модулей, обходя `/local/modules/*/install/version.php`. Нет файла —
модуля нет в админке, и никакой ошибки при этом не будет. Минимальный видимый скелет:

```
local/modules/vendor.module/
├── install/
│   ├── index.php
│   └── version.php        ← сканируется Битриксом; без него модуль невидим
└── lang/ru/install/
    └── index.php          ← MODULE_NAME / MODULE_DESCRIPTION
```

Версию в конструкторе **не хардкодить** — подключать `version.php`, иначе она разъедется со
сканером. В имени класса установщика точки заменяются подчёркиваниями: `vendor.module` →
`class vendor_module`.

## Грабли `/local/modules/` (проверено вживую)

| Ловушка | Почему не работает | Как правильно |
|---|---|---|
| `Loader::registerAutoLoadClasses('vendor.module', …)` | резолвит пути в `/bitrix/modules/<id>/`, а не в `/local/` | `spl_autoload_register` с `__DIR__` в `include.php` |
| `registerAutoLoadClasses(null, …)` с абсолютным путём | Bitrix добавляет `DOCUMENT_ROOT` перед уже абсолютным путём → двойной путь | то же: `spl_autoload_register` |
| `RegisterModuleDependences` / `registerEventHandler` для D7-ORM-события | `MESSAGE_ID` в `b_module_to_module` — `VARCHAR(50)`, имена ORM-событий длиннее → запись усекается, обработчик молча не вызывается | `addEventHandler` в `include.php` + инжект `init.php` — [[recipe-d7-orm-event-subscription]] |
| Свои классы в `DoInstall` сразу после `registerModule` | автозагрузчик из `include.php` ещё не подключён | сначала `Loader::includeModule($this->MODULE_ID)` |
| `CModule::RegisterModule()` | старый API | `ModuleManager::registerModule()` |

Автозагрузчик для модуля в `/local/`:

```php
// local/modules/vendor.module/include.php
spl_autoload_register(static function (string $class): void {
    $prefix = 'Vendor\\Module\\';
    if (strncasecmp($class, $prefix, strlen($prefix)) !== 0) {   // без учёта регистра — см. ниже
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $path = __DIR__ . '/lib/' . $relative . '.php';
    if (!file_exists($path)) {
        $path = __DIR__ . '/lib/' . implode('/', array_map('ucfirst', explode('/', $relative))) . '.php';
    }
    if (file_exists($path)) {
        require_once $path;
    }
});
```

`strncasecmp` вместо `strncmp` — страховка от [[antipattern-ajax-controller-lowercase-name|лоуэркейса
имени контроллера в AJAX-действиях]]; запасной путь через `ucfirst` — от регистрозависимой ФС Linux.

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
  ([[recipe-migrations-as-code|Миграции как код]]).

## Связанное
- [[recipe-module-versioning-and-private-distribution]], [[pattern-module-based-development-standard]]
- [[recipe-d7-orm-event-subscription]] — подписка модуля на событие D7 ORM
- [[pattern-module-self-disabling-guard]] — модуль не должен ронять портал
- [[recipe-safe-module-deploy]] — безопасная заливка на сервер

[← Свои модули](_index-modules-custom.md)
