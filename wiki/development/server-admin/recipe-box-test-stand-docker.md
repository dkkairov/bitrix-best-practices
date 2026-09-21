---
title: "Тестовый стенд коробки Битрикс24 в Docker (официальное окружение)"
type: recipe
module: server-admin
edition: box
status: verified
provenance: mixed
verified: "2026-09-21 / Windows 11 + Docker Desktop 29.7, env-docker 369e01a (BitrixVM 9.0.10); Битрикс24 коробка: main 26.750.0, bizproc 26.1075.0, crm 26.800.0, pull 26.100.0; PHP 8.2.33, Percona 8.0.46"
tags: [docker, стенд, тестовый портал, env-docker, установка, лицензия, push, cron, агенты]
sources: []
related: ["[[checklist-dev-environment-and-git]]", "[[recipe-mysql-connection-refused]]", "[[pattern-bizproc-ai-assisted-generation]]", "[[antipattern-cli-php-as-root]]", "[[recipe-cli-script-bootstrap]]"]
aliases: []
updated: "2026-09-21"
---

# Тестовый стенд коробки Битрикс24 в Docker

**Результат:** локальная коробка Битрикс24 на официальном окружении 1С-Битрикс: сайт открыт
только с этой машины, агенты выполняются через cron, push-сервер работает. Около часа, большая часть
времени — скачивание и мастер установки. Занимает около 8 ГБ: образы ~3 ГБ, файлы портала ~3 ГБ,
база ~0,5 ГБ, окружение 1,1 ГБ.

**Когда нужен:** клиенты на коробке, а проверять БП, модули и загрузку шаблонов на боевом портале
нельзя. Облачное поведение здесь не проверить: облако ≠ коробка, для облака нужен облачный портал.

## Предусловия
- Docker Desktop (Windows, macOS) или Docker Engine (Linux). На Windows — с WSL2.
- Лицензия: пробная версия (30 дней) или свой ключ с отметкой «Установка для разработки» — см.
  раздел «Лицензия» ниже.
- Версия PHP: **8.2** — минимум для коробки и версия по умолчанию в этом окружении. Код,
  проверенный на 8.2, как правило, работает у клиентов с 8.2 и выше; обратное неверно — на 8.4–8.5
  легко использовать то, чего у клиента нет. Под клиента на другой версии переключаются образ и
  каталог `confs/phpXX` у сервисов `php` и `cron`.

## Шаги

### 1. Окружение
Папка — вне репозитория вики и вне `home` OSPanel. На Windows клонируем без замены переводов строк:
конфиги монтируются в Linux-контейнеры.
```bash
git -c core.autocrlf=false clone --depth 1 https://github.com/bitrix-tools/env-docker <папка>
cd <папка>
```

### 2. Секреты и часовой пояс
Пароли и ключ push-сервера генерируем сразу в файлы, не выводя на экран:
```bash
gen() { LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c "$1"; }
sed -i "s/CHANGE_MYSQL_ROOT_PASSWORD_HERE/$(gen 24)/" .env_mysql
sed -i "s/CHANGE_POSTGRESQL_POSTGRES_PASSWORD_HERE/$(gen 24)/" .env_postgresql
sed -i "s/CHANGE_SECURITY_KEY_HERE/$(gen 128)/" .env_push
grep -c CHANGE_ .env_mysql .env_postgresql .env_push     # везде 0
```
Часовой пояс — в `.env` (`TZ=<зона>`) и в `confs/php82/etc/php/conf.d/timezone.ini`
(`date.timezone = <зона>`). От него зависят сроки и паузы в БП. Казахстан (UTC+5) — `Asia/Qyzylorda`.

### 3. Локальные настройки — отдельным файлом
Файл вендора не трогаем, чтобы окружение обновлялось через `git pull`.
`docker-compose.override.yml`:
```yaml
services:
  nginx:
    ports: !override
      - "127.0.0.1:8588:80"      # сайт только с этой машины
      - "127.0.0.1:8589:443"
  php:
    depends_on: !override
      - mysql
  postgres:
    profiles: ["off"]            # база — MySQL
  sphinx:
    profiles: ["off"]            # поиск для тестов не нужен
  lego:
    profiles: ["off"]            # Let's Encrypt не нужен; публикует порт 80 (на Windows его держат OSPanel, IIS)
```
Проверка: `docker compose config --services` — девять сервисов, без `postgres`, `sphinx`, `lego`.

### 4. Запуск и установщик
```bash
docker compose up -d
docker compose logs mysql | grep "port: 3306"        # база готова
docker compose exec --user=bitrix php sh -c 'cd /opt/www && wget https://www.1c-bitrix.ru/download/scripts/bitrixsetup.php'
```
Дальше — в браузере `http://localhost:8588/bitrixsetup.php`: «1С-Битрикс24», редакция клиента,
пробная или коммерческая версия.

### 5. Мастер установки
- **База данных:** сервер `mysql` (**не** `localhost`), пользователь `root`, пароль —
  `MYSQL_ROOT_PASSWORD` из `.env_mysql` **без кавычек**, база новая.
- **Шаг лицензионного ключа:** отметка «Установка для разработки», если ставим с ключом.
- Учётную запись администратора сохраняем в менеджере паролей.

### 6. После установки: cron, push, адрес сайта
Всё делается скриптом внутри контейнера `php` от пользователя `bitrix`
([[antipattern-cli-php-as-root|не от root]]). Ключ push-сервера передаётся переменной окружения и на
экран не попадает:
```bash
K=$(grep '^PUSH_SECURITY_KEY=' .env_push | cut -d= -f2- | tr -d '"')
docker compose exec -T -e PUSH_KEY="$K" --user=bitrix php php < configure.php
```
Что делает `configure.php`:
```php
<?php
$_SERVER['DOCUMENT_ROOT'] = '/opt/www';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

// 1) Агенты — на cron. Контейнер cron уже раз в минуту запускает cron_events.php
$f = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/php_interface/dbconn.php';
$t = file_get_contents($f);
if (strpos($t, 'BX_CRONTAB_SUPPORT') === false) {
    $t = str_replace('define("BX_DISABLE_INDEX_PAGE", true);',
        "define(\"BX_DISABLE_INDEX_PAGE\", true);\ndefine(\"BX_CRONTAB_SUPPORT\", true);", $t);
    file_put_contents($f, $t);
}

// 2) Push-сервер. Адреса для браузера — localhost; адреса, куда публикует сам PHP, — внутренний nginx:
//    внутри контейнера php «localhost» — это сам контейнер
$host = 'localhost:8588';
$hostSecure = 'localhost:8589';
\Bitrix\Main\Config\Configuration::getInstance()->add('pull', [
    'path_to_listener'               => "http://$host/bitrix/sub/",
    'path_to_listener_secure'        => "https://$hostSecure/bitrix/sub/",
    'path_to_modern_listener'        => "http://$host/bitrix/sub/",
    'path_to_modern_listener_secure' => "https://$hostSecure/bitrix/sub/",
    'path_to_mobile_listener'        => 'http://localhost:8893/bitrix/sub/',
    'path_to_mobile_listener_secure' => 'https://localhost:8894/bitrix/sub/',
    'path_to_websocket'              => "ws://$host/bitrix/subws/",
    'path_to_websocket_secure'       => "wss://$hostSecure/bitrix/subws/",
    'path_to_publish'                => 'http://nginx/bitrix/pub/',
    'path_to_publish_web'            => "http://$host/bitrix/rest/",
    'path_to_publish_web_secure'     => "https://$hostSecure/bitrix/rest/",
    'path_to_json_rpc'               => 'http://nginx/bitrix/api/',
    'nginx_version' => '4', 'nginx_command_per_hit' => '100', 'nginx' => 'Y', 'nginx_headers' => 'N',
    'push' => 'Y', 'websocket' => 'Y',
    'signature_key' => getenv('PUSH_KEY'), 'signature_algo' => 'sha1', 'guest' => 'N',
]);
\Bitrix\Main\Config\Configuration::getInstance()->saveConfiguration();

// 3) Адрес сайта (мастер записывает «_» — server_name из nginx) и условия отметки разработки
COption::SetOptionString('main', 'server_name', $host);
(new CSite())->Update('s1', ['SERVER_NAME' => $host]);
COption::SetOptionString('main', 'update_autocheck', '');   // без автопроверки обновлений
```
Набор адресов push-сервера — из README окружения; разделение на «браузер» и «сервер» — наше.

## Проверка результата
- **Портал:** `http://localhost:8588/` отдаёт 200.
- **Агенты на cron:** через пару минут в `b_agent` есть свежие `LAST_EXEC`:
  ```sql
  SELECT MAX(LAST_EXEC), SUM(LAST_EXEC > NOW() - INTERVAL 3 MINUTE) FROM b_agent WHERE ACTIVE = 'Y';
  ```
  На стенде 2026-09-21 — 33 запуска за 3 минуты.
- **Push-сервер:** изнутри контейнера `php` запрос
  `curl -s -o /dev/null -w '%{http_code}' http://nginx/bitrix/pub/` возвращает 400. Это ответ самого
  push-сервера на пустой запрос; 502 или 000 означали бы, что до него не достучаться.
- **Проверка системы** (`/bitrix/admin/site_checker.php`, вкладка «Работа портала») — финальная
  сверка, в том числе push-сервера.

## Лицензия
- Один ключ — **не более двух установок**. Одна из них должна быть закрыта от публичного доступа и
  использоваться только для разработки и тестирования
  ([Регистрация коммерческого продукта](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=32&LESSON_ID=1945)).
- **«Установка для разработки»** ставится на шаге ввода ключа или позже: главный модуль → вкладка
  «Система обновлений» (опция `update_devsrv`). Условия: закрыть публичный доступ, отключить
  автопроверку обновлений (`update_autocheck`), не переключать отметку туда-обратно (переключения
  отслеживаются), телефония ограничена
  ([Установка для разработки](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=135&LESSON_ID=8471&LESSON_PATH=10495.4495.8471)).
- ⚠️ **«Закрыть доступ для посетителей»** (`site_stopped = Y`) пускает только пользователей с
  операцией `edit_other_settings` — по коду `main/include/prolog_after.php` (main 26.750.0). Тестовые
  сотрудники без админских прав не смогут открыть портал и выполнить задания БП. Если стенд
  привязан к `127.0.0.1`, он и так закрыт от сети. Включать ли флаг — решение владельца лицензии;
  включённый флаг мешает тестам с несколькими пользователями.

## Откат и проблемы
| Симптом | Причина | Что делать |
|---------|---------|------------|
| `(2002) No such file or directory` на шаге базы | сервер `localhost`: PHP ищет сокет MySQL в своём контейнере, а база — в соседнем | сервер `mysql`; не путать с `(2002) Connection refused`, когда база лежит ([[recipe-mysql-connection-refused]]) |
| `lego` спорит с локальным веб-сервером за порт 80 | `lego` публикует `80:80`; у нас порт держит OSPanel | выключить профилем, шаг 3 (сделали заранее) |
| В настройках адрес сайта `_` | мастер взял `server_name _` из конфига nginx | шаг 6, пункт 3 |
| README окружения запрещает `localhost` в адресах | внутри контейнера `php` адрес `localhost` — сам контейнер, публикация push туда не дойдёт | серверные пути `path_to_publish` и `path_to_json_rpc` — через `http://nginx/...`, браузерные — через `localhost` |
| Нужен чистый стенд | — | `docker compose down -v` удаляет контейнеры **и тома с порталом и базой** |

## Источники и связанное
- Окружение: [bitrix-tools/env-docker](https://github.com/bitrix-tools/env-docker) (README: пароли,
  push-сервер, cron), [статья вендора на Хабре](https://habr.com/ru/companies/bitrix/articles/917124/).
- Требования коробки: [технические требования](https://helpdesk.bitrix24.ru/open/5825131/) — PHP 8.2+,
  MySQL 8.x (Percona).
- [[checklist-dev-environment-and-git|Окружение разработки и git]] — зачем тестовая копия и что в git.
- [[recipe-cli-script-bootstrap|Консольный скрипт: подключение ядра]] — как устроен `configure.php`.
- [[pattern-bizproc-ai-assisted-generation|AI-генерация БП]] — стенд нужен для пилота загрузки шаблонов.

[← Администрирование сервера](_index-server-admin.md)
