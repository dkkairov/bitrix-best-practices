<?php

// Автозагрузчик модуля в /local/modules/.
//
// Loader::registerAutoLoadClasses('vendor.module', …) резолвит пути в /bitrix/modules/ —
// для /local/ не работает. С null + абсолютным путём Битрикс добавляет DOCUMENT_ROOT
// перед уже абсолютным путём. Надёжен только spl_autoload_register с __DIR__.
//
// strncasecmp, а не strncmp: Resolver приводит namespace к нижнему регистру, если имя
// контроллера в AJAX-действии начинается со строчной буквы. Запасной путь через ucfirst —
// на случай регистрозависимой файловой системы Linux.
spl_autoload_register(static function (string $class): void {
    $prefix = 'Vendor\\Module\\';
    if (strncasecmp($class, $prefix, strlen($prefix)) !== 0) {
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

// Сторож: если модуль выключил сам себя, дальше не идём — портал работает из коробки.
if (!\Vendor\Module\Guard::isEnabled()) {
    return;
}

\Vendor\Module\Guard::registerShutdownHandler();

// Дальше — регистрация обработчиков событий и сервисов.
// Выполняется на каждом хите, потому что init.php грузит модуль (см. install/index.php).
//
// \Bitrix\Main\EventManager::getInstance()->addEventHandler(
//     'crm',
//     '\Bitrix\Crm\SomeEntity::OnAfterUpdate',       // имя класса БЕЗ суффикса Table
//     [\Vendor\Module\Handler::class, 'onAfterUpdate']
// );
//
// Ленивая регистрация сервиса (например, подмена фабрики смарт-процесса):
//
// \Bitrix\Main\DI\ServiceLocator::getInstance()->addInstanceLazy(
//     'crm.service.factory.dynamic.' . $entityTypeId,
//     ['constructor' => static function () use ($entityTypeId) {
//         \Bitrix\Main\Loader::requireModule('crm');
//         $type = \Bitrix\Crm\Service\Container::getInstance()->getTypeByEntityTypeId($entityTypeId);
//         return new \Vendor\Module\Crm\Factory($type);
//     }]
// );
