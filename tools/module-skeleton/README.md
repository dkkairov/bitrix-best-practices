# module-skeleton — стартовый каркас модуля для `/local/modules/`

Минимальный обезличенный скелет коробочного модуля: то, без чего он либо не виден в админке,
либо не работает молча. Обобщён из реальных модулей, проверенных на портале
(main 26.600–26.700, PHP 8.2–8.3).

**Это не рабочий модуль**, а отправная точка: скопировать, переименовать, дописать логику.

## Что внутри

```
local/modules/vendor.module/
├── include.php                     автозагрузчик + регистрация обработчиков и сервисов
├── install/
│   ├── version.php                 ← без него модуль невидим
│   └── index.php                   установщик + инжект загрузки в init.php
├── lang/ru/install/index.php       название и описание модуля
└── lib/
    └── Guard.php                   сторож: портал важнее модуля
```

## Как использовать

1. Скопировать каталог и переименовать:
   - папку `vendor.module` → `<вендор>.<модуль>`;
   - класс установщика `vendor_module` → `<вендор>_<модуль>` (точки → подчёркивания);
   - namespace `Vendor\Module\` в `include.php` и `lib/`;
   - константу `INIT_MARKER` и `MODULE_ID`;
   - коды языковых сообщений `VENDOR_MODULE_*`.
2. Заполнить `lang/ru/install/index.php`.
3. Дописать логику в `DoInstall` / `DoUninstall` и `lib/`.
4. Установить: *Marketplace → Установленные решения* или скриптом
   (см. [recipe-safe-module-deploy](../../wiki/development/server-admin/recipe-safe-module-deploy.md)).

## Почему именно так

Каждое решение в скелете — следствие граблей, разобранных в вики:

| Что в скелете | Почему | Страница |
|---|---|---|
| `install/version.php` обязателен | Битрикс строит список модулей, обходя именно его; без файла модуль невидим и ошибки нет | [recipe-module-structure-and-install](../../wiki/development/modules-custom/recipe-module-structure-and-install.md) |
| `spl_autoload_register` вместо `registerAutoLoadClasses` | штатный автозагрузчик резолвит пути в `/bitrix/modules/`, а не в `/local/` | там же |
| `strncasecmp` в автозагрузчике | `Resolver` лоуэркейсит namespace, если имя контроллера в AJAX-действии со строчной буквы | [antipattern-ajax-controller-lowercase-name](../../wiki/development/core-d7/antipattern-ajax-controller-lowercase-name.md) |
| Инжект `init.php` вместо `RegisterModuleDependences` | `MESSAGE_ID` в `b_module_to_module` — `VARCHAR(50)`, имена событий D7 ORM длиннее: запись усекается, обработчик молча не вызывается | [recipe-d7-orm-event-subscription](../../wiki/development/core-d7/recipe-d7-orm-event-subscription.md) |
| Маркеры `BEGIN`/`END` вокруг блока в `init.php` | корректное снятие при удалении модуля; файл общий на портал | там же |
| `Loader::includeModule` в `DoInstall` до своих классов | автозагрузчик живёт в `include.php` и до этого момента не подключён | [recipe-smart-process-programmatic-creation](../../wiki/modules/smart-process/recipe-smart-process-programmatic-creation.md) |
| `lib/Guard.php` | модуль в горячем пути не имеет права ронять портал; обновление продукта может изменить сигнатуру ядрового метода | [pattern-module-self-disabling-guard](../../wiki/development/modules-custom/pattern-module-self-disabling-guard.md) |

## Чего в скелете сознательно нет

- `.settings.php` (контроллеры, провайдер раздела меню), `options.php`, админ-страницы, таблицы
  `install/db/` — добавляются по надобности, чтобы каркас оставался читаемым.
- Тесты и скрипты заливки: процедура — в
  [recipe-safe-module-deploy](../../wiki/development/server-admin/recipe-safe-module-deploy.md),
  а конкретные скрипты зависят от стенда и содержат его параметры.
- Любых реальных адресов, доменов, ID порталов и учётных данных здесь нет и быть не должно
  (`CLAUDE.md` §4.4).
