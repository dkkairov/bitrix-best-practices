---
title: "Безопасная заливка модуля на портал: hostname-guard, линт, бэкап, откат"
type: recipe
module: server-admin
edition: box
status: verified
provenance: empirical
verified: "2026-09-16 / коробка: main 26.700, PHP 8.2; установка, обновление и откат прогнаны на тест-стенде"
tags: [деплой, модуль, откат, бэкап, дымовые-тесты, init-php]
sources: []
related: ["[[checklist-windows-to-linux-deploy]]", "[[antipattern-cli-php-as-root]]", "[[recipe-module-structure-and-install]]", "[[pattern-module-self-disabling-guard]]", "[[concept-testing-approach]]"]
aliases: []
updated: "2026-09-18"
---

# Безопасная заливка модуля на портал

**Результат:** установка или обновление модуля в `/local/modules/`, которое либо проходит целиком,
либо откатывается без следов. Портал не остаётся в промежуточном состоянии.

## Предусловия
- Известен владелец сайта: `stat -c %U /home/bitrix/www`.
- Известно ожидаемое имя сервера (`hostname`) — защита «не туда».
- Правило про владельца процесса учтено — [[antipattern-cli-php-as-root]].
- Если заливаете с Windows — [[checklist-windows-to-linux-deploy]].

## Состав процедуры

| Шаг | Зачем |
|---|---|
| 1. Проверка `hostname` | не залить прод вместо теста; несовпадение — немедленный выход |
| 2. Проверка аплоада | `install/index.php` на месте, иначе заливать нечего |
| 3. Линт **новой** версии на PHP сервера | синтаксис проверяем до того, как трогаем рабочие файлы |
| 4. Бэкап текущей версии модуля и `init.php` | единственный способ откатиться |
| 5. Замена файлов | `rm -rf` + `mv`, с проверкой результата |
| 6. `DoInstall` / `InstallFiles` от владельца сайта | иначе портим кэш |
| 7. Линт `init.php` после установки | модуль мог вписать в него битый блок |
| 8. Дымовые тесты и самопроверка | подтверждение, что модуль жив |
| 9. Возврат владельца файлам кэша | страховка |

Неудача на любом шаге → откат из бэкапа и ненулевой код возврата.

## Шаги

```bash
#!/bin/bash
set -u
abort() { echo "  ABORT: $*"; rm -rf "$UP"; exit 1; }

DOC_ROOT=${DOC_ROOT:-/home/bitrix/www}
MOD="$DOC_ROOT/local/modules/$MODULE_ID"
BACKUP="/tmp/${MODULE_ID}.backup.$(date +%s)"
INIT="$DOC_ROOT/local/php_interface/init.php"
OWNER=$(stat -c %U "$DOC_ROOT"); GROUP=$(stat -c %G "$DOC_ROOT")

# 1-2. защита от «не туда» и проверка аплоада
[ "$(hostname)" = "$EXPECT_HOSTNAME" ] || abort "это не $EXPECT_HOSTNAME"
[ -f "$UP/install/index.php" ]         || abort "аплоад не найден"

# 3. линт новой версии ДО замены
find "$UP" -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null || abort "линт новой версии"

# 4. бэкап
[ -d "$MOD" ] && { cp -a "$MOD" "$BACKUP" || abort "бэкап модуля"; }
cp -a "$INIT" "$INIT.bak" 2>/dev/null

# 5. замена
rm -rf "$MOD" && mv "$UP" "$MOD"
[ -f "$MOD/install/index.php" ] || { [ -d "$BACKUP" ] && cp -a "$BACKUP" "$MOD"; abort "замена не удалась — откат"; }
chown -R "$OWNER:$GROUP" "$MOD"

# 6. установка — от владельца сайта, не от root
runuser -u "$OWNER" -- env BX_DOCUMENT_ROOT="$DOC_ROOT" php -f "$MOD/tools/install_cli.php" || abort "DoInstall"

# 7. init.php мог быть испорчен инжектом
php -l "$INIT" >/dev/null || { cp -a "$INIT.bak" "$INIT"; abort "init.php сломан — откат"; }

# 8. дымовые тесты и самопроверка
runuser -u "$OWNER" -- env BX_DOCUMENT_ROOT="$DOC_ROOT" php -f "$MOD/tests/selfcheck.php" || abort "самопроверка"

# 9. владелец кэша
for d in bitrix/managed_cache bitrix/cache bitrix/stack_cache; do
  find "$DOC_ROOT/$d" ! -user "$OWNER" -exec chown "$OWNER:$GROUP" {} + 2>/dev/null
done
echo "OK"
```

## Проверка результата
- Модуль установлен: *Marketplace → Установленные решения*.
- `php -l` по `init.php` чистый, блок модуля между маркерами на месте
  ([[recipe-d7-orm-event-subscription]]).
- Самопроверка прошла **от владельца сайта** — запуск от root читает устаревший кэш и врёт.
- Владелец файлов в `bitrix/managed_cache` — владелец сайта.

## Что запускать только на тест-стенде
- **Сквозной тест** (e2e): создаёт служебные данные.
- **Проба сторожа настоящим фаталом** — на несколько секунд выключает модуль и рассылает аварийное
  сообщение администраторам ([[pattern-module-self-disabling-guard]]). Полезно прогнать хотя бы
  раз: сторож, который никогда не срабатывал, обычно не работает.

Оба режима включать явным флагом окружения и **никогда** не включать на проде.

## Откат и проблемы
- Модуль: вернуть каталог из бэкапа, выполнить `DoUninstall` при необходимости.
- `init.php`: вернуть из `.bak` — блок модуля исчезнет, подписки перестанут регистрироваться.
- «Опция сохранилась, но не применяется» → [[antipattern-cli-php-as-root]].
- Установка «прошла», но модуля не видно → нет `install/version.php`,
  см. [[recipe-module-structure-and-install]].

## Альтернатива без скриптов
Скопировать `local/modules/<vendor.module>/` любым SFTP-клиентом и установить в админке
(*Marketplace → Установленные решения*). Подходит для первой установки на спокойном портале;
для повторяющихся обновлений теряются бэкап, линт и откат.

## Связанное
- [[checklist-windows-to-linux-deploy]] — грабли доставки с Windows
- [[recipe-git-deploy-to-production]] — доставка через git, когда код лежит в репозитории
- [[concept-testing-approach]] — что считать дымовым тестом

[← Администрирование сервера](_index-server-admin.md)
