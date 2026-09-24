---
title: "Шифровать чувствительные данные: Cipher, CryptoField, ключи"
type: recipe
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / коробка в Docker, main 26.750.0: у Security\\Cipher только __construct, encrypt, decrypt (getRandomBytes нет — байты даёт Security\\Random::getBytes); прогон encrypt/decrypt на ключе 32 байта прошёл, шифротекст 60 байт для строки «секрет»; секция crypto в .settings.php на стенде есть и ключ задан; текст — документация фреймворка (docs.1c-bitrix.ru, «Шифрование данных», «Криптографические поля в ORM»)"
tags: [шифрование, cipher, cryptofield, секреты, ключи, персданные]
sources: []
related: ["[[concept-web-vulnerabilities-bitrix]]", "[[entity-settings-php]]", "[[concept-d7-orm-entity]]", "[[entity-web-cookie]]", "[[concept-coding-standards]]"]
aliases: []
updated: "2026-09-24"
---

# Шифровать чувствительные данные: Cipher, CryptoField, ключи

**Результат:** токен интеграции или персональные данные лежат в базе зашифрованными, а код остаётся
читаемым.

**Когда применять:** хранение чужих учётных данных (ключи API, токены), данные, которые нельзя
показывать даже администратору базы. Пароли **не шифруем** — их хешируют, и этим занимается ядро.

## Явное шифрование

```php
use Bitrix\Main\Security\Cipher;
use Bitrix\Main\Security\Random;

$cipher = new Cipher();               // по умолчанию aes-256-ctr + sha256 для контроля целостности
$key = Random::getBytes(32);          // ключ храним отдельно от данных

$encrypted = $cipher->encrypt('секрет', $key);      // бинарная строка
$plain     = $cipher->decrypt($encrypted, $key);
```

- В базу кладём `base64_encode($encrypted)`, обратно — `base64_decode()` перед `decrypt()`.
- Проверено на стенде: у `Cipher` ровно три публичных метода — `__construct`, `encrypt`, `decrypt`;
  случайные байты берём у `Security\Random` (`getBytes`, `getString`, `getInt`,
  `getStringByAlphabet` и другие).
- Шифротекст длиннее исходника: для строки «секрет» вышло 60 байт — колонку берём с запасом.

## Поле сущности целиком

Если шифровать нужно всё поле, проще не трогать код записи:

```php
(new Fields\CryptoField('ACCESS_TOKEN')),
```

`CryptoField` сам шифрует при записи и расшифровывает при чтении ([[concept-d7-orm-entity]]).
Ключ берётся из параметра поля `crypto_key` либо из секции `crypto` → `crypto_key` в
`.settings.php` ([[entity-settings-php]]); на стенде секция есть и ключ задан.

**Без ключа поле молча работает как обычный текст** — поэтому наличие ключа проверяем при
развёртывании, а не после инцидента. Наследник `SecretField` ещё и генерирует значение
(`configureSecretLength()`).

Для данных, которые уезжают в браузер, есть `Web\CryptoCookie` ([[entity-web-cookie]]).

## Где хранить ключ

- ключ **не лежит рядом с данными** и не попадает в git;
- боевой ключ — в переменных окружения или менеджере паролей, в репозитории только заглушка
  ([[concept-coding-standards]]);
- смена ключа означает перешифровку данных: пишем миграцию заранее, иначе старые значения
  превратятся в мусор;
- копия боевой базы на стенде без ключа читаться не будет — это скорее плюс, но о нём надо помнить.

## Чего не делать

- **Не шифруем то, что нужно искать.** По зашифрованной колонке нет ни `LIKE`, ни индекса; нужен
  поиск — храним отдельный хеш или нормализуем схему.
- **Не изобретаем свой алгоритм** и не берём `base64` за шифрование: это кодирование.
- **Не пишем ключ в лог** и не выводим в отладке ([[recipe-box-debugging]]).
- **Не шифруем пароли пользователей** — для них односторонний хеш, и это делает ядро.

## Связанные страницы
- [[concept-web-vulnerabilities-bitrix]] — остальные механизмы защиты
- [[entity-settings-php]] — секция `crypto`
- [[concept-d7-orm-entity]] — `CryptoField` и `SecretField`

[← Ядро D7](_index-core-d7.md)
