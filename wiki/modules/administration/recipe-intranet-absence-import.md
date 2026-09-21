---
title: "Запись отсутствий из кода (импорт отпусков)"
type: recipe
module: administration
edition: box
status: draft
provenance: mixed
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Модуль Интранет / Отсутствия, Организационная структура; код записи — вывод команды, без проверки на стенде"
tags: [интранет, отсутствия, отпуска, импорт, инфоблоки, график-отсутствий]
sources: ["[[source-devbook-intranet]]"]
related: ["[[entity-user-absence]]", "[[entity-cintranet-utils]]", "[[concept-org-structure]]", "[[entity-config-option]]", "[[recipe-cli-script-bootstrap]]"]
aliases: []
updated: "2026-09-21"
---

# Запись отсутствий из кода (импорт отпусков)

**Результат:** отпуска, командировки и больничные из внешнего источника (кадровая система, файл)
попадают в «График отсутствий» портала, а повторный запуск импорта не создаёт дублей.

> **Черновик.** Устройство графика и API чтения — по книге
> ([Отсутствия](https://bx24devbook.website.yandexcloud.net/Modul_Intranet/Otsutstvia.html)).
> Примера **записи** отсутствия в книге нет: код шагов 3–4 — вывод команды на API инфоблоков, на
> стенде не проверялся.

## Как устроено (по книге)
- Отсутствие — **элемент инфоблока** «График отсутствий»; по умолчанию инфоблок лежит в типе
  «Оргструктура». Хранителем отсутствий в настройках модуля «Интранет» можно назначить любой
  инфоблок с нужными свойствами
  ([Архитектура](https://bx24devbook.website.yandexcloud.net/Modul_Intranet/Otsutstvia.html#arhitektura)).

| Что | Где хранится |
|---|---|
| Сотрудник | свойство `USER` — привязка к пользователю |
| Тип отсутствия | свойство `ABSENCE_TYPE` — список |
| Причина | поле `NAME` элемента |
| Начало и окончание | поля `ACTIVE_FROM` и `ACTIVE_TO` |

- Вторая часть отсутствий — подсистема модуля «Календарь». Рецепт пишет только в график.
- **Свои типы отсутствий книга добавлять не советует:** не все подсистемы интранета учитывают новые
  значения списка — такой тип может не засчитаться ни отпуском, ни отсутствием без уважительной
  причины. Импорт кладём в существующие типы.
- **Своих событий на создание, изменение и удаление отсутствий нет** — работать через API и события
  инфоблоков
  ([События](https://bx24devbook.website.yandexcloud.net/Modul_Intranet/Otsutstvia.html#sobytia)).

## Предусловия
- Коробка, модули `intranet` и `iblock`. `intranet` в публичной части подключён всегда, но в
  консоли подключаем явно
  ([Перед использованием API](https://bx24devbook.website.yandexcloud.net/Modul_Intranet/O_module.html#pered-ispol-zovaniem-api)).
- **Сначала убедиться на своей версии, что график — по-прежнему инфоблок интранета.** Предупреждение
  команды, в книге его нет: в новых версиях структура компании может быть переведена на другой
  модуль. Проверить на стенде: опция `iblock_absence` заполнена, инфоблок существует, у него есть
  свойства `USER` и `ABSENCE_TYPE`, его записи видны в интерфейсе «График отсутствий». Пока это не
  подтверждено — ничего не писать.
- Сотрудники источника сопоставлены с ID пользователей портала; ключ сопоставления согласован
  заранее.
- Скрипт — консольный ([[recipe-cli-script-bootstrap]]), лежит в `local/php_interface/console/`
  ([[pattern-local-solution-structure]]) и запускается не от root
  ([[antipattern-cli-php-as-root]]).
- Выгрузки с отпусками — персональные данные: в git и в вики не кладём (`CLAUDE.md` §4.4).

## Шаги

### 1. Найти инфоблок графика
ID хранится в настройках модуля `intranet`, параметр `iblock_absence`: есть общее значение и
значения по сайтам — у каждого сайта установки инфоблок может быть свой
([API](https://bx24devbook.website.yandexcloud.net/Modul_Intranet/Otsutstvia.html#api)).

```php
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

if (!Loader::includeModule('intranet') || !Loader::includeModule('iblock')) {
    throw new \RuntimeException('Нужны модули intranet и iblock');
}

$siteId   = 's1';                                                    // свой сайт
$iblockId = (int)Option::get('intranet', 'iblock_absence', '-1', $siteId);
if ($iblockId <= 0) {
    throw new \RuntimeException('Инфоблок графика отсутствий не настроен');
}
```

- **По умолчанию — `'-1'`, а не `0`.** В `CIBlockElement::getList` нулевое значение фильтра
  игнорируется, и условие по инфоблоку просто пропадёт; `-1` снижает ущерб при сбое настроек
  (книга; тот же приём — в
  [Оргструктуре](https://bx24devbook.website.yandexcloud.net/Modul_Intranet/Orgstruktura.html#api)).
  Явная проверка `<= 0` с исключением — дополнительная страховка команды.
- `\Bitrix\Intranet\UserAbsence::getIblockId()` — альтернативный способ получить **общий** ID; при
  повреждённых записях настроек он может вернуть `0` ([[entity-user-absence]]).
- Как `Option::get` выбирает между значением сайта и общим — [[entity-config-option]].

### 2. Найти тип отсутствия по коду, а не по ID
`ENUM_ID` значения списка `ABSENCE_TYPE` на разных порталах разный — не зашиваем.
`UserAbsence::getVacationTypes()` отдаёт типы, где `ID` — символьный код (например, `VACATION`),
`ENUM_ID` — ID значения списка, `NAME` — название, `ACTIVE` — признак «это отпуск»
([Типы отсутствий](https://bx24devbook.website.yandexcloud.net/Modul_Intranet/Otsutstvia.html#tipy-otsutstvij)).

```php
use Bitrix\Intranet\UserAbsence;

$types      = UserAbsence::getVacationTypes();                       // ключи — символьные коды
$typeEnumId = (int)($types['VACATION']['ENUM_ID'] ?? 0);
if ($typeEnumId <= 0) {
    throw new \RuntimeException('Тип отсутствия VACATION на портале не найден');
}
```

- Искать можно и по `NAME`, но название правят в интерфейсе — код надёжнее (вывод команды).
- Коды источника сопоставить с символьными кодами портала таблицей в настройках решения, а не в
  коде (вывод команды).
- **Проверить на стенде:** совпадает ли символьный код с `XML_ID` значения списка и берёт ли
  `getVacationTypes()` значения из того же инфоблока, куда пишем, если у сайта инфоблок свой.

### 3. Защититься от дублей
Ключ «сотрудник + период» кладём в `XML_ID` элемента и перед записью ищем по нему (вывод команды).
Если у записи в источнике есть свой ID, ключ лучше строить из него — тогда перенос отпуска станет
обновлением, а не второй записью. Пересечения с отсутствиями, внесёнными вручную, такой ключ не
ловит.

### 4. Записать элемент через API инфоблока
**Вывод команды — в книге примера нет, на стенде не проверялось.**

```php
// local/php_interface/classes/Vendor/Project/Absence/AbsenceImporter.php
namespace Vendor\Project\Absence;

final class AbsenceImporter
{
    private int $iblockId;
    private int $typeEnumId;

    public function __construct(int $iblockId, int $typeEnumId)
    {
        $this->iblockId   = $iblockId;
        $this->typeEnumId = $typeEnumId;
    }

    /** Даты — 'Y-m-d'. Возвращает ID нового элемента или null, если запись уже есть. */
    public function import(int $userId, string $dateFrom, string $dateTo, string $reason): ?int
    {
        $key = sprintf('absence-import:%d:%s:%s', $userId, $dateFrom, $dateTo);

        $exists = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => $this->iblockId, '=XML_ID' => $key],
            false,
            ['nTopCount' => 1],
            ['ID']
        )->Fetch();
        if ($exists) {
            return null;
        }

        $from = new \DateTimeImmutable($dateFrom . ' 00:00:00');
        $to   = new \DateTimeImmutable($dateTo . ' 23:59:59');

        $element = new \CIBlockElement();
        $id = $element->Add([
            'IBLOCK_ID'       => $this->iblockId,
            'NAME'            => $reason,
            'ACTIVE_FROM'     => $from->format('d.m.Y H:i:s'),           // формат сайта, здесь — российский
            'ACTIVE_TO'       => $to->format('d.m.Y H:i:s'),
            'XML_ID'          => $key,
            'PROPERTY_VALUES' => [
                'USER'         => $userId,
                'ABSENCE_TYPE' => $this->typeEnumId,
            ],
        ]);
        if (!$id) {
            throw new \RuntimeException('Отсутствие не записано: ' . $element->LAST_ERROR);
        }

        return (int)$id;
    }
}
```

```php
$importer = new \Vendor\Project\Absence\AbsenceImporter($iblockId, $typeEnumId);
$importer->import(128, '2026-07-01', '2026-07-14', 'Ежегодный отпуск');
```

- Даты — строкой в формате сайта; для России книга приводит `d.m.Y H:i:s` (в разделе про
  `GetAbsenceData`). На портале с другим форматом поправить.
- Запись идёт через API инфоблока — значит, сработают обработчики событий инфоблоков, если они есть
  на портале (вывод команды).

### 5. Проверить
```php
$absences = \CIntranetUtils::GetAbsenceData(
    [
        'DATE_START'  => '01.07.2026 00:00:00',
        'DATE_FINISH' => '14.07.2026 23:59:59',
        'USERS'       => [128],
    ],
    BX_INTRANET_ABSENCE_HR                   // только график отсутствий, без календаря
);
```

- По умолчанию режим `BX_INTRANET_ABSENCE_ALL` — график вместе с календарём; для сверки импорта
  нужен `BX_INTRANET_ABSENCE_HR`
  ([Проверка отсутствия](https://bx24devbook.website.yandexcloud.net/Modul_Intranet/Otsutstvia.html#proverka-otsutstvia)).
  Параметры: `DATE_START`, `DATE_FINISH` (формат сайта), `USERS` (пусто — все сотрудники),
  `PER_USER` (группировать по сотруднику). Формат ответа книга не приводит — посмотреть на стенде.
- В дни отсутствия `CIntranetUtils::IsUserAbsent(128)` должен вернуть `true`
  ([[entity-cintranet-utils]]).
- Запись видна в интерфейсе «График отсутствий».
- `UserAbsence::getCurrentMonth()` кэшируется — свежая запись может появиться там не сразу
  ([[entity-user-absence]]).

## События начала и окончания
Подсистема выбрасывает `OnStartAbsence` и `OnEndAbsence` — начало и окончание отпуска
([Начало и Окончание](https://bx24devbook.website.yandexcloud.net/Modul_Intranet/Otsutstvia.html#nacalo-i-okoncanie)).

| Параметр | Значение |
|---|---|
| `USER_ID` | ID сотрудника |
| `ABSENCE_TYPE` | тип (число) |
| `START`, `END` | даты начала и окончания (строки) |
| `DURATION` | длительность в секундах |

Возвращаемое значение не обрабатывается.

**Проверить на стенде:** в примере книги обработчик подписан на модуль `crm` — для события
интранета это подозрительно. Модуль-источник найти поиском `OnStartAbsence` по `/bitrix/modules/`
([[concept-platform-reverse-engineering]]). Пример книги — обработчик нового ядра (объект
`\Bitrix\Main\Event`); если событие окажется событием старого ядра, нужен
`addEventHandlerCompatible` ([[entity-event-manager]]).

## Откат и проблемы
- **Откат:** импортированные элементы отличаются префиксом `XML_ID` (`absence-import:`) — найти по
  нему и удалить средствами API инфоблока. Записи, внесённые вручную, не затрагиваются (вывод
  команды).

| Симптом | Причина | Что делать |
|---|---|---|
| Исключение на шаге 1 | опция `iblock_absence` пуста или настройки повреждены | проверить настройки модуля «Интранет»; `0` не подставлять |
| Тип `VACATION` не найден | на портале другие символьные коды | посмотреть `getVacationTypes()`, дополнить таблицу соответствия; свои типы не заводить |
| Импортированный отпуск не считается отпуском | запись в тип, добавленный в список вручную | использовать штатные типы — новые учитываются не всеми подсистемами |
| После переноса отпуска в источнике — две записи | ключ «сотрудник + период» | строить ключ из ID записи источника и обновлять элемент |
| Обработчик `OnStartAbsence` молчит | подписка на модуль из примера книги (`crm`) | найти реальный модуль-источник (см. выше) |

## Источники и связанное
- Книга: [Отсутствия](https://bx24devbook.website.yandexcloud.net/Modul_Intranet/Otsutstvia.html),
  [Организационная структура → API](https://bx24devbook.website.yandexcloud.net/Modul_Intranet/Orgstruktura.html#api)
- [[entity-user-absence]] — чтение отсутствий, D7
- [[entity-cintranet-utils]] — `IsUserAbsent()` и `GetAbsenceData()`
- [[concept-org-structure]] — оргструктура на инфоблоках интранета и приём с `'-1'`
- [[entity-config-option]] — чтение опций модулей

[← Администрирование портала](_index-administration.md)
