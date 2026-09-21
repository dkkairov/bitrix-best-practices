---
title: "MySQL (2002) Connection refused: диагностика и подъём портала"
type: recipe
module: server-admin
edition: box
status: verified
provenance: empirical
verified: "2026-07-16 / коробка на «Веб-окружении Bitrix», Linux, инцидент на тест-стенде"
tags: [инцидент, mysql, сервер, диагностика, bitrix-vm, диск, oom]
sources: []
related: ["[[checklist-box-performance]]", "[[checklist-dev-environment-and-git]]"]
aliases: ["bitrix-mysql-connection-refused"]
updated: "2026-09-21"
---

# MySQL `(2002) Connection refused`

**Результат:** портал поднят, причина падения зафиксирована, а не затёрта рестартом.

## Симптом

Вместо страниц портал отдаёт исключение:

```
[Bitrix\Main\DB\ConnectionException]
Mysql connect error [localhost]: (2002) Connection refused (400)
```

Стек упирается в `prolog_before.php` → `Application->initializeExtendedKernel`: падение происходит
**до любого кода сайта**, на инициализации ядра. PHP и веб-сервер живы; лежит именно СУБД.
`(2002) Connection refused` = на порту 3306 / сокете никто не слушает, демон не запущен.

## Предусловия
- SSH-доступ к серверу с правами на управление сервисами.

## Шаги

### 1. Диагностика — строго ДО рестарта

Рестарт затирает картину: после успешного старта причину часто уже не восстановить.

```bash
systemctl status mysqld        # на «Веб-окружении Bitrix» сервис mysqld, иначе mariadb
df -h                          # раздел со 100% — самая частая причина
dmesg -T | grep -i oom | tail  # не убил ли mysqld OOM-killer
```

### 2. Подъём

Если диск не полон и OOM нет:

```bash
systemctl start mysqld
systemctl status mysqld        # ждём Active: active (running)
systemctl is-enabled mysqld    # должно быть enabled — автостарт после ребута
```

### 3. Если не стартует — читать лог, а не гадать

```bash
tail -50 /var/log/mysqld.log
# либо: journalctl -u mysqld -n 50
# либо: /var/lib/mysql/*.err
```

## Проверка результата
- Портал отдаёт страницы.
- `systemctl is-enabled mysqld` → `enabled`.
- В логе нет повторяющихся ошибок старта.

## Частые причины

| Причина | Признак | Что делать |
|---|---|---|
| **Диск 100 %** | `df -h` показывает заполненный раздел | Сначала чистить, потом стартовать. Пухнут обычно `/var/log/`, каталог бэкапов портала и бинлоги в `/var/lib/mysql/`. Бэкапы Bitrix способны съесть сотни гигабайт |
| **OOM-killer** | строки oom в `dmesg` | `start` поможет, но падение вернётся: уменьшить `innodb_buffer_pool_size` и/или добавить swap |
| **`Error: 17 (File exists)`** в статусе | статус упавшего **автостарта**, возможно многодневной давности | Ручной `start` часто проходит: конфликтный сокет/pid к тому моменту уже убран. Если не прошёл — искать устаревшие `mysql.sock` / `mysqld.pid` и смотреть лог |

Не путать с **`(2002) No such file or directory`**: это подключение к `localhost` через сокет там,
где MySQL работает в другом контейнере. База жива, в настройках нужен хост контейнера —
[[recipe-box-test-stand-docker|стенд в Docker]].

## Подводные камни
- Сервис может быть `enabled` и всё равно не подняться после ребута (гонка при загрузке). После
  каждого перезапуска стенда проверять статус явно.
- Портал мог лежать задолго до обращения: `systemctl status` показывает время последнего падения —
  сверяйте его с моментом, когда «всё работало».
- Рестарт всей машины или `service mysqld restart` «лечит», но теряет причину: инцидент вернётся.

## Связанное
- [[checklist-box-performance]] — что вообще следить на коробочном сервере

[← Администрирование сервера](_index-server-admin.md)
