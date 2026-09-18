---
title: "Заливка с Windows на Linux-сервер: шесть тихих граблей"
type: checklist
module: server-admin
edition: box
status: verified
provenance: empirical
verified: "2026-09-15 / Windows 11 + Git Bash + Python/paramiko → коробка на Linux"
tags: [деплой, ssh, windows, git-bash, paramiko, fail2ban, crlf]
sources: []
related: ["[[recipe-safe-module-deploy]]", "[[antipattern-cli-php-as-root]]", "[[recipe-git-deploy-to-production]]", "[[checklist-dev-environment-and-git]]"]
aliases: ["windows-ssh-deploy-pitfalls"]
updated: "2026-09-18"
---

# Заливка с Windows на Linux-сервер

**Когда применять:** разработчик работает на Windows, портал — коробка на Linux, доставка идёт
по SSH/SFTP (не через git на сервере).

Особенность всех шести пунктов: **ни один не выглядит как ошибка**. Из шести сессий доставки две
молча не сделали ничего, а одна проверка соврала об успехе.

## Предусловия
- Доступ по SSH к серверу, известен владелец сайта (`stat -c %U /home/bitrix/www`).
- Учтено правило про владельца процесса — [[antipattern-cli-php-as-root]].

## Чек-лист

### Пути и кодировки
- [ ] **`MSYS_NO_PATHCONV=1` перед вызовом.** Git Bash (MSYS) превращает аргумент, начинающийся с
      `/`, в путь Windows: `/tmp/upload` → `C:/Users/<user>/AppData/Local/Temp/upload`. SFTP
      послушно создаёт на сервере каталог `/root/C:/Users/.../upload`, а скрипт файлов в `/tmp`
      не находит. Касается любых аргументов нативных программ (python, php.exe, git для Windows).
- [ ] **Скрипты `.sh` пишутся только с LF.** Python на Windows по умолчанию пишет CRLF:
      `io.open(path, 'w', encoding='utf-8')` → `\r\n`. На сервере это даёт
      `bash: line 5: $'hostname\r': command not found` и пути вида `/home/bitrix/www\r/…`.
      Скрипт «отрабатывает без ошибок выхода» и не делает ничего.
      Правильно: `io.open(path, 'w', encoding='utf-8', newline='\n')`.
      PHP и JS с CRLF на сервере работают — ломаются именно shell-скрипты.
- [ ] **Проверять `\r` побайтно, а не `grep`.** В Git Bash `grep -c $'\r' script.sh` вернул 114
      при 114 строках в файле **без единого** `\r` — шаблон теряется, и `grep` считает все строки.
      Правильно: `python -c "print(open('script.sh','rb').read().count(b'\r'))"`.
- [ ] **Вывод скрипта — принудительно UTF-8.** При перенаправлении stdout в файл Python на Windows
      пишет в кодировке консоли (cp1251) и падает на любом не-cp1251 символе:
      `UnicodeEncodeError: 'charmap' codec can't encode character '→'`. Падение происходит **после**
      заливки, но **до** запуска скрипта на сервере — файлы лежат в `/tmp`, подключение потрачено.
      В самом скрипте:
      ```python
      for stream in (sys.stdout, sys.stderr):
          if hasattr(stream, "reconfigure"):
              stream.reconfigure(encoding="utf-8", errors="replace")
      ```
      Снаружи — запасной `PYTHONIOENCODING=utf-8`.

### Подключения
- [ ] **Одна сессия = одно SSH-подключение.** На стендах с fail2ban порт 22 закрывается на
      15–30 минут уже после 2–3 подключений подряд — **в том числе успешных**. SFTP-заливку и
      выполнение скрипта делать через один клиент (`open_sftp()` → `put` →
      `exec_command('bash -s')`), всю работу собирать в один серверный скрипт.
- [ ] **Между сессиями выдерживать ~10 минут.** Удобно фоном: `sleep 600 && <сессия>`.
- [ ] **Доступность проверять без SSH** — TCP на 443: fail2ban следит за sshd, и «проверка связи»
      по ssh сама же вас и забанит.

### Сам скрипт доставки
- [ ] **Скрипт останавливается на первой неудаче.** В инциденте `mv` упал, а скрипт честно прошёл
      остальные 17 шагов вслепую — установку, тесты и «уборку». Портал уцелел только потому, что
      модуль не разложился вообще.
      ```bash
      abort() { echo "  ABORT: $*"; rm -rf "$UP"; exit 1; }

      [ "$(hostname)" = "$EXPECT_HOSTNAME" ] || abort "это не тот сервер"
      [ -f "$UP/install/index.php" ]         || abort "аплоад не найден"
      cp -a "$MOD" "$BACKUP"                 || abort "не смог сделать бэкап"
      rm -rf "$MOD" && mv "$UP" "$MOD"
      [ -f "$MOD/install/index.php" ] || { cp -a "$BACKUP" "$MOD"; abort "замена не удалась — откат"; }
      ```
- [ ] **Линт новой версии на PHP сервера — до замены файлов.**
- [ ] **Автооткат `init.php` из бэкапа**, если `php -l init.php` упал после установки.
- [ ] **Уборка удаляет только своё**: сверять число файлов в мусорном каталоге с числом файлов
      аплоада; пустые родительские каталоги убирать через `rmdir`, который не удалит непустое.

## Критерии приёмки
- Скрипт на чужом сервере останавливается на проверке hostname, а не начинает заливку.
- Намеренно испорченный шаг (недоступный каталог) приводит к откату, а не к «успеху».
- После заливки владелец файлов кэша — владелец сайта.

## Частые ошибки
- [[antipattern-cli-php-as-root]] — запуск PHP с ядром от root
- `LC_ALL=C` в скрипте ради `grep` по исходникам ломает кириллицу в выводе PHP: для PHP-частей
  оставлять `C.UTF-8`
- Консоль Windows показывает UTF-8-вывод кракозябрами — это отображение, не данные; важные
  результаты писать в файл и читать файлом

## Связанное
- [[recipe-safe-module-deploy]] — готовая процедура установки/обновления модуля
- [[recipe-git-deploy-to-production]] — альтернативный путь доставки, когда код в git

[← Администрирование сервера](_index-server-admin.md)
