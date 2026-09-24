---
title: "Дата и время D7: Date, DateTime, часовые пояса"
type: concept
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / коробка в Docker, main 26.750.0, пояс стенда Asia/Qyzylorda: прогон — isCorrect() пропускает 31.02.2026 и 99.99.9999, tryParse() их «переливает» в следующий месяц, toUserTime() меняет сам объект и возвращает его же, isUserTimeEnabled() не статический, createFromText('завтра') работает; текст — документация фреймворка (docs.1c-bitrix.ru, «Дата и время»)"
tags: [дата, время, часовые-пояса, datetime, валидация]
sources: []
related: ["[[concept-d7-orm-entity]]", "[[recipe-d7-orm-crud]]", "[[concept-localization-lang-files]]", "[[recipe-cli-script-bootstrap]]"]
aliases: []
updated: "2026-09-24"
---

# Дата и время D7: Date, DateTime, часовые пояса

**TL;DR:** в ядре два типа — `Bitrix\Main\Type\Date` (только дата) и `Bitrix\Main\Type\DateTime`
(дата со временем, наследник `Date`). Они умеют читать и писать форматы сайта и переводить время в
пояс пользователя. Главные грабли — не в API, а в валидации: «31 февраля» ядро принимает молча.

## Создание

| Способ | Что делает |
|---|---|
| `new DateTime()` / `new Date()` | текущий момент по поясу сервера |
| `new DateTime($string, $format)` | разбор строки по маске PHP |
| `DateTime::createFromTimestamp($ts)` | из метки времени |
| `DateTime::createFromPhp(\DateTime $d)` | из стандартного объекта PHP |
| `DateTime::createFromUserTime($string)` | строка в поясе пользователя → объект в поясе сервера |
| `DateTime::createFromText($text)` | разбор словами: `createFromText('завтра')` на стенде дал `25.09.2026 18:00:00` |

## Вывод

- `format('d.m.Y H:i')` — маска PHP;
- `toString()` — формат из региональных настроек сайта (`Context\Culture`): на стенде
  `24.09.2026 08:30:00` для `DateTime` и `24.09.2026` для `Date`.

В интерфейсе показываем `toString()` — тогда дата выглядит так, как настроено у сайта и языка
([[concept-localization-lang-files]]).

## Часовые пояса

| Метод | Поведение (проверено) |
|---|---|
| `getTimeZone()`, `setTimeZone()` | пояс объекта; на стенде объекты создаются в `Asia/Qyzylorda` |
| `setDefaultTimeZone()` | вернуть пояс сервера |
| `toUserTime()` | перевести в пояс пользователя |
| `disableUserTime()` / `enableUserTime()` | выключить и включить автоперевод; возвращают сам объект |
| `isUserTimeEnabled()` | **метод объекта, не статический** — статический вызов падает с `Error` |

> **`toUserTime()` меняет объект, а не копию.** На стенде `$d->toUserTime() === $d` — вернулся тот
> же самый объект с изменённым состоянием. Нужен исходный — сначала `clone`, иначе дальше по коду
> поедет уже сдвинутое время.

Поддержка персональных поясов в целом включается классом `CTimeZone` (`CTimeZone::Enabled()` — на
стенде `true`).

## Арифметика и сравнение

```php
$d = new DateTime('2026-01-01 10:00:00', 'Y-m-d H:i:s');
$d->add('+2 days');            // 2026-01-03 10:00:00; принимает и \DateInterval
$diff = $a->getDiff($b);       // \DateInterval, ->days
$a < $b;                       // работает: сравнение наследует поведение \DateTime PHP
```

`setDate($y, $m, $d)`, `setTime($h, $i, $s, $ms)` меняют части значения.

## Ловушка валидации

Проверено на 26.750.0:

| Вход | `isCorrect()` | `tryParse()` |
|---|---|---|
| `31.02.2026` | `true` | `2026-03-03` |
| `32.13.2026` | `true` | `2027-02-01` |
| `99.99.9999` | `true` | `10007-06-07` |
| `abc` | `false` | `null` |

То есть **`isCorrect()` проверяет форму строки, а не существование даты**, а `tryParse()` спокойно
«переливает» лишние дни и месяцы вперёд. Обе функции отсекают только мусор вроде `abc`.

Хотим честную проверку пользовательского ввода — сравниваем разобранное значение с исходным:

```php
$parsed = DateTime::tryParse($input, 'd.m.Y');
$valid = $parsed !== null && $parsed->format('d.m.Y') === $input;
```

## Хранение

- В ORM для колонок используем `DateField` и `DatetimeField` — приведение к формату базы делает
  ядро ([[concept-d7-orm-entity]]).
- В массив полей `add()`/`update()` кладём **объект**, а не строку: строка попадёт в базу как есть и
  разъедется с форматом ([[recipe-d7-orm-crud]]).
- В консоли пояс берётся из настроек PHP контейнера, пользователя нет — личный пояс не применяется
  ([[recipe-cli-script-bootstrap]]).

## Связанные страницы
- [[concept-d7-orm-entity]] — поля даты в сущности
- [[concept-localization-lang-files]] — форматы даты по языку сайта
- [[recipe-d7-orm-crud]] — запись значений

[← Ядро D7](_index-core-d7.md)
