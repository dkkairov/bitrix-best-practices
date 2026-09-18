---
title: "Подписка модуля на событие D7 ORM (RegisterModuleDependences молча не работает)"
type: recipe
module: core-d7
edition: box
status: verified
provenance: empirical
verified: "2026-06-04 / коробка: модуль bizproc, D7 ORM; повторно 2026-09-15 на main 26.700"
tags: [события, orm, d7, модуль, init-php, eventmanager]
sources: []
related: ["[[pattern-events-over-core-modification]]", "[[recipe-module-structure-and-install]]", "[[pattern-crm-action-vs-event]]"]
aliases: ["bitrix24-d7-orm-event-module"]
updated: "2026-09-18"
---

# Подписка модуля на событие D7 ORM

**Результат:** свой модуль ловит `OnAfterAdd` / `OnAfterUpdate` / `OnAfterDelete` ORM-сущности
(`DataManager`) — надёжно, на каждом хите, с корректным снятием при удалении модуля.

## Почему штатный путь не работает

Регистрация обработчика в `DoInstall` через `RegisterModuleDependences` (и D7-обёртку
`registerEventHandler`) кладёт запись в `b_module_to_module`, где `MESSAGE_ID` — **`VARCHAR(50)`**.
Имена событий D7 ORM длиннее:

```
\Bitrix\BizProc\Workflow\Task\TaskUser::OnAfterUpdate   → 53 символа
```

Запись усекается, событие не матчится. **Ошибки нет** — обработчик просто никогда не вызывается.
Это самый дорогой вид дефекта: всё «установилось успешно».

## Предусловия
- Модуль в `/local/modules/vendor.module/` с рабочим автозагрузчиком —
  [[recipe-module-structure-and-install]].
- Известно точное имя события (см. «Подводные камни»).

## Шаг 1. `include.php` — `addEventHandler`

`addEventHandler` держит обработчик в памяти процесса, ограничения на длину имени нет.

```php
// local/modules/vendor.module/include.php
spl_autoload_register(static function (string $class): void {
    $prefix = 'Vendor\\Module\\';
    if (strncasecmp($class, $prefix, strlen($prefix)) !== 0) return;
    $file = __DIR__ . '/lib/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($file)) require_once $file;
});

\Bitrix\Main\EventManager::getInstance()->addEventHandler(
    'bizproc',
    '\Bitrix\BizProc\Workflow\Task\TaskUser::OnAfterUpdate',
    ['\Vendor\Module\EventHandler', 'onTaskUserUpdate']
);
```

## Шаг 2. `DoInstall` — инжект загрузки модуля в `init.php`

`include.php` выполняется только при `Loader::includeModule()`. Чтобы это происходило на каждом
хите, установщик вписывает одну строку в `/local/php_interface/init.php` — с маркерами, чтобы
корректно снять при удалении.

```php
private const INIT_MARKER = 'vendor.module';

public function DoInstall(): bool
{
    $this->injectInit();
    \Bitrix\Main\ModuleManager::registerModule($this->MODULE_ID);
    return true;
}

public function DoUninstall(): bool
{
    $this->ejectInit();
    \Bitrix\Main\ModuleManager::unRegisterModule($this->MODULE_ID);
    return true;
}

private function injectInit(): void
{
    $initFile = \Bitrix\Main\Application::getDocumentRoot() . '/local/php_interface/init.php';
    $block    = $this->getInitBlock();

    if (file_exists($initFile)) {
        $content = file_get_contents($initFile);
        if (strpos($content, self::INIT_MARKER) !== false) return;   // идемпотентность
        $content .= "\n" . $block;
    } else {
        $content = "<?php\n" . $block;
    }
    file_put_contents($initFile, $content);
}

private function ejectInit(): void
{
    $initFile = \Bitrix\Main\Application::getDocumentRoot() . '/local/php_interface/init.php';
    if (!file_exists($initFile)) return;
    $content = file_get_contents($initFile);
    $content = preg_replace(
        '/\n?\/\/ --- BEGIN ' . preg_quote(self::INIT_MARKER, '/') . ' ---.*?'
        . '\/\/ --- END ' . preg_quote(self::INIT_MARKER, '/') . ' ---\n?/s',
        '',
        $content
    );
    file_put_contents($initFile, $content);
}

private function getInitBlock(): string
{
    $id = self::INIT_MARKER;
    return "// --- BEGIN {$id} ---\n"
        . "\\Bitrix\\Main\\Loader::includeModule('{$id}');\n"
        . "// --- END {$id} ---\n";
}
```

После установки в `init.php` появляется:

```php
// --- BEGIN vendor.module ---
\Bitrix\Main\Loader::includeModule('vendor.module');
// --- END vendor.module ---
```

`init.php` — файл общий на портал, его правят и другие модули. Перед заливкой делайте резервную
копию и откатывайте при сбое — [[recipe-safe-module-deploy]].

## Архитектура обработчика: ранний выход + исходы

Обработчик, встроенный в чужой поток сохранения, обязан **никогда не выбрасывать наружу**.
Рабочая форма:

```php
public static function onSomeEntityUpdate($id, $fields = null): void
{
    // 1. Разбор аргументов: Event или массивы
    // 2. Ранний выход, если это не наш случай — молча, без уведомления
    if (!array_key_exists('SOME_FIELD', $fields)) return;

    $ctx = ['id' => $id];
    [$outcome, $detail] = self::process($ctx);   // никогда не кидает
    self::notify($outcome, $detail, $ctx);       // вызывается всегда
}

private static function process(array &$ctx): array
{
    try {
        // guard-клаузы → [self::OUTCOME_SKIPPED, 'причина']
        return [self::OUTCOME_DONE, ''];
    } catch (\Throwable $e) {
        return [self::OUTCOME_ERROR, $e->getMessage()];
    }
}
```

Четыре исхода — `done` / `already` (ничего не поменялось) / `skipped` (не наш случай) /
`error`. Разделение «пропустить» и «ошибка» экономит часы при разборе инцидентов.

## Проверка результата
- Поменять сущность и убедиться, что обработчик отработал (лог/уведомление).
- Проверить, что `init.php` содержит блок с маркерами, а после `DoUninstall` — не содержит.
- Проверить ранний выход: изменение «не нашего» поля не должно порождать никакой реакции.

## Подводные камни

- **Имя класса без суффикса `Table`.** Класс — `TaskUserTable`, в имени события — `TaskUser`.
- **Регистр namespace значим.** Имя события — строка, сравнение точное:
  `\Bitrix\BizProc` (заглавная `P`) ≠ `\Bitrix\Bizproc`. Проверять по исходникам или
  диагностическим скриптом, а не по памяти — [[concept-platform-reverse-engineering]].
- **`fields` в `OnAfterUpdate` содержит только изменённые поля.** Проверка «поле пустое» на
  отсутствующем ключе даёт ложное срабатывание. Это же — главный аргумент за
  [[pattern-crm-action-vs-event|`Operation\Action` вместо событий]] для CRM-сущностей.
- **Оба шага обязательны.** `addEventHandler` без инжекта в `init.php` = модуль не грузится =
  подписки нет.
- **`addEventHandler` прямо в `init.php` без модуля** тоже работает, но теряется упаковка:
  логика размазана, нет корректного снятия при удалении.

## Откат
- Удалить блок между маркерами `BEGIN`/`END` из `/local/php_interface/init.php` — обработчик
  перестаёт регистрироваться на следующем хите.

## Связанное
- [[pattern-events-over-core-modification]] — общий механизм событий
- [[recipe-module-structure-and-install]] — каркас модуля и автозагрузка
- [[pattern-module-self-disabling-guard]] — что делать, если обработчик всё-таки упал

[← Ядро D7](_index-core-d7.md)
