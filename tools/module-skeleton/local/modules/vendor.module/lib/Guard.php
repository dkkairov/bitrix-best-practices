<?php

namespace Vendor\Module;

use Bitrix\Main\Config\Option;

/**
 * Сторож: портал важнее модуля.
 *
 * Любая наша ошибка не должна мешать людям работать. Три уровня защиты:
 *   1) исключение в нашем коде — try/catch на месте (в вызывающем коде);
 *   2) фатал PHP в наших файлах — register_shutdown_function (try/catch его не видит);
 *   3) несовместимость с ядром — сверка сигнатур рефлексией ДО объявления наших классов.
 *
 * Во всех случаях: enabled = N. На следующем хите include.php выходит сразу,
 * сервисы не подменяются, портал работает из коробки.
 */
final class Guard
{
    private const MODULE_ID = 'vendor.module';

    public static function isEnabled(): bool
    {
        return Option::get(self::MODULE_ID, 'enabled', 'Y') === 'Y';
    }

    /**
     * Уровень 2: фатал внутри каталога модуля.
     *
     * Проверка «файл ошибки наш» обязательна — иначе модуль будет выключать себя
     * из-за чужих падений.
     */
    public static function registerShutdownHandler(): void
    {
        $moduleDir = dirname(__DIR__);

        register_shutdown_function(static function () use ($moduleDir): void {
            $error = error_get_last();
            if (!$error) {
                return;
            }

            $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
            if (!in_array($error['type'], $fatal, true)) {
                return;
            }

            if (strpos($error['file'], $moduleDir) !== 0) {
                return;   // чужой фатал — не наше дело
            }

            self::panic(sprintf('fatal: %s @ %s:%d', $error['message'], $error['file'], $error['line']));
        });
    }

    /**
     * Уровень 3: сверка сигнатуры ядрового метода до того, как PHP увидит наш наследник.
     *
     * Несовместимое переопределение даёт фатал на всём портале. Проверяем заранее —
     * не совпало, значит отдаём штатное поведение и выключаемся.
     */
    public static function isCompatible(string $parentClass, string $method, string $returnType, int $paramCount): bool
    {
        try {
            $ref = new \ReflectionMethod($parentClass, $method);
        } catch (\ReflectionException $e) {
            return false;
        }

        if ($ref->isFinal()) {
            return false;
        }

        if (count($ref->getParameters()) !== $paramCount) {
            return false;
        }

        return (string)$ref->getReturnType() === $returnType;
    }

    /**
     * Выключить модуль и уведомить администратора — один раз, на переходе
     * «работал → выключен», чтобы не спамить.
     */
    public static function panic(string $reason): void
    {
        if (!self::isEnabled()) {
            return;
        }

        Option::set(self::MODULE_ID, 'enabled', 'N');
        Option::set(self::MODULE_ID, 'panic_reason', mb_substr($reason, 0, 1000));

        self::notifyAdmins($reason);
    }

    private static function notifyAdmins(string $reason): void
    {
        // Получатель из настроек модуля; не задан — активные администраторы портала.
        // Отправка через CIMNotify::Add / CIMMessage::Add.
        // Сбой уведомления не должен ничего ломать:
        try {
            // ...
        } catch (\Throwable $e) {
            // осознанно молчим
        }
    }
}
