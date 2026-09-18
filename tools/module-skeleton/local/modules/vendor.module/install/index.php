<?php

use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

Loc::loadMessages(__FILE__);

/**
 * Установщик модуля vendor.module.
 *
 * Имя класса = ID модуля с точками, заменёнными на подчёркивания.
 */
class vendor_module extends CModule
{
    public $MODULE_ID = 'vendor.module';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;

    /** Маркер блока, который модуль вписывает в init.php. */
    private const INIT_MARKER = 'vendor.module';

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';   // версию не хардкодим — берём из version.php

        $this->MODULE_VERSION      = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME         = Loc::getMessage('VENDOR_MODULE_MODULE_NAME');
        $this->MODULE_DESCRIPTION  = Loc::getMessage('VENDOR_MODULE_MODULE_DESCRIPTION');
    }

    public function DoInstall(): bool
    {
        $this->injectInit();
        ModuleManager::registerModule($this->MODULE_ID);

        // Свои классы доступны только после includeModule: автозагрузчик живёт в include.php.
        \Bitrix\Main\Loader::includeModule($this->MODULE_ID);

        // Здесь: создание таблиц, опций, смарт-процессов, регистрация агентов.

        return true;
    }

    public function DoUninstall(): bool
    {
        // Здесь: снятие агентов, удаление опций; таблицы — по решению (данные!).

        $this->ejectInit();
        ModuleManager::unRegisterModule($this->MODULE_ID);

        return true;
    }

    // ─── Инжект загрузки модуля в init.php ──────────────────────────────────
    //
    // include.php выполняется только при Loader::includeModule(). Чтобы модуль
    // грузился на каждом хите (а значит, работали addEventHandler и регистрация
    // сервисов), установщик вписывает одну строку в /local/php_interface/init.php.
    //
    // Почему не RegisterModuleDependences: MESSAGE_ID в b_module_to_module —
    // VARCHAR(50), имена событий D7 ORM длиннее, запись усекается и обработчик
    // молча не вызывается.

    private function injectInit(): void
    {
        $initFile = $this->getInitPath();
        $block    = $this->getInitBlock();

        if (file_exists($initFile)) {
            $content = file_get_contents($initFile);
            if (strpos($content, self::INIT_MARKER) !== false) {
                return;   // идемпотентность: повторная установка не дублирует блок
            }
            $content .= "\n" . $block;
        } else {
            $content = "<?php\n" . $block;
        }

        file_put_contents($initFile, $content);
    }

    private function ejectInit(): void
    {
        $initFile = $this->getInitPath();
        if (!file_exists($initFile)) {
            return;
        }

        $marker  = preg_quote(self::INIT_MARKER, '/');
        $content = preg_replace(
            '/\n?\/\/ --- BEGIN ' . $marker . ' ---.*?\/\/ --- END ' . $marker . ' ---\n?/s',
            '',
            file_get_contents($initFile)
        );

        file_put_contents($initFile, $content);
    }

    private function getInitPath(): string
    {
        return Application::getDocumentRoot() . '/local/php_interface/init.php';
    }

    private function getInitBlock(): string
    {
        $id = self::INIT_MARKER;

        return "// --- BEGIN {$id} ---\n"
            . "\\Bitrix\\Main\\Loader::includeModule('{$id}');\n"
            . "// --- END {$id} ---\n";
    }
}
