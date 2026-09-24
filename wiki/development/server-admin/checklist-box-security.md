---
title: "Чек-лист безопасности коробки перед сдачей"
type: checklist
module: server-admin
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / собран по разделу «Безопасность» документации фреймворка (docs.1c-bitrix.ru) и нашим проверкам на стенде (main 26.750.0, security 26.200.0): экранирование, санитайзер, CSRF-функции, Cipher, каталоги логов"
tags: [безопасность, приёмка, чек-лист, аудит, 2fa, секреты]
sources: []
related: ["[[concept-web-vulnerabilities-bitrix]]", "[[concept-proactive-security]]", "[[recipe-encrypt-sensitive-data]]", "[[entity-quality-monitor]]", "[[checklist-golive-deployment]]"]
aliases: []
updated: "2026-09-24"
---

# Чек-лист безопасности коробки перед сдачей

**Когда применять:** перед запуском портала в работу и при приёмке чужой доработки. Дополняет
[[entity-quality-monitor|монитор качества]] вендора: здесь то, что монитор не проверяет — наш код.

## 1. Код проекта

- [ ] Вывод пользовательских данных экранирован `htmlspecialcharsbx()` или
      `HtmlFilter::encode()`; `htmlspecialcharsEx()` в новом коде **нет**
      ([[concept-web-vulnerabilities-bitrix]])
- [ ] Пользовательский HTML проходит `CBXSanitizer` с уровнем не ниже нужного
- [ ] Данные в JS и JSON экранируются `CUtil::JSEscape()` / `Web\Json::encode()`
- [ ] В прямом SQL — только плейсхолдеры `SqlExpression` и методы `SqlHelper`
      ([[concept-d7-sql-layer]])
- [ ] Все изменяющие формы и AJAX проверяют токен: `check_bitrix_sessid()` либо штатный фильтр
      `Csrf` ([[recipe-engine-controller-action]])
- [ ] Запросы по адресам от пользователя — с `setPrivateIp(false)` ([[recipe-http-client]])
- [ ] Загрузка файлов проверяет тип: `CFile::CheckImageFile()` для картинок, белый список
      расширений для остального

## 2. Секреты и данные

- [ ] Ни токенов, ни паролей, ни вебхуков в репозитории и в логах
- [ ] Ключ шифрования (`crypto` → `crypto_key`) задан, хранится отдельно от базы
      ([[recipe-encrypt-sensitive-data]])
- [ ] Чувствительные поля зашифрованы (`CryptoField`), пароли — только хеш
- [ ] Логи и дампы лежат вне публичного каталога; `__bx_log.log` в корне отсутствует
      ([[recipe-box-debugging]])
- [ ] На проде `exception_handling` → `debug` выключен ([[entity-settings-php]])

## 3. Настройки портала

- [ ] Включён стандартный уровень проактивной защиты; журнал вторжений просмотрен
      ([[concept-proactive-security]])
- [ ] Для администраторов включена двухэтапная авторизация, резервные коды выданы
- [ ] Сессии: хранилище соответствует нагрузке, `regenerateIdAfterLogin` включён
      ([[concept-d7-session-storage]])
- [ ] Cookie: `HttpOnly`, `Secure` на HTTPS, осознанный `SameSite` ([[entity-web-cookie]])
- [ ] Права доступа выданы по ролям, а не «всем администратор»
- [ ] Резервная копия снята и **развёрнута на стенде хотя бы раз** ([[recipe-box-backup]])

## 4. Инфраструктура

- [ ] Сайт только по HTTPS, сертификат не истекает в ближайший месяц
- [ ] Административный раздел закрыт дополнительно (IP, VPN или второй фактор)
- [ ] Обновления платформы ставятся, версия модулей зафиксирована в документации проекта
- [ ] Доступы к серверу и БД — именные, а не общий `root` на всю команду
      ([[antipattern-cli-php-as-root]])

## 5. Приёмка

- [ ] Пройден монитор качества вендора, «пропущенные» пункты прокомментированы
- [ ] Список включённых мер безопасности передан заказчику письменно
- [ ] Договорились, кто и как отключает 2FA и восстанавливает доступ

## Связанные страницы
- [[concept-web-vulnerabilities-bitrix]] — механика защиты в коде
- [[concept-proactive-security]] — настройки портала
- [[checklist-golive-deployment]] — общий playbook запуска

[← Серверное администрирование](_index-server-admin.md)
