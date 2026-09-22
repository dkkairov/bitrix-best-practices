---
title: "Запись отсутствий из кода (импорт отпусков)"
type: recipe
module: administration
edition: box
status: verified
provenance: mixed
verified: "2026-09-22 / коробка в Docker: график отсутствий (инфоблок, свойства, типы), запись элемента и реакция интранета (кэш, агенты), чтение GetAbsenceData и IsUserAbsent прогнаны на тестовой записи; событие в календаре и срабатывание OnStartAbsence по агенту не проверяли. Текст — «Книга разработчика Bitrix24» (снимок 2026-09-21): Модуль Интранет / Отсутствия"
tags: [интранет, отсутствия, отпуска, импорт, инфоблоки, график-отсутствий]
sources: ["[[source-devbook-intranet]]"]
related: ["[[entity-user-absence]]", "[[entity-cintranet-utils]]", "[[concept-org-structure]]", "[[entity-config-option]]", "[[recipe-cli-script-bootstrap]]"]
aliases: []
updated: "2026-09-22"
---

# Запись отсутствий из кода (импорт отпусков)

**Результат:** отпуска, командировки и больничные из внешнего источника (кадровая система, файл)
попадают в «График отсутствий» портала, а повторный запуск импорта не создаёт дублей.

> **Проверено на стенде** (коробка в Docker, 2026-09-22): график отсутствий — по-прежнему инфоблок
> интранета, импорт через `CIBlockElement::Add` работает и подхватывается подсистемой
> автоматически. Примера **записи** в книге нет — код шагов 3–4 наш, но теперь прогнан.

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
- **Сначала убедиться на своей версии, что график — по-прежнему инфоблок интранета.** На стенде
  2026-09-22 это так: опция `iblock_absence` заполнена (и общая, и по сайту), инфоблок «График
  отсутствий» лежит в типе `structure`, свойства — `USER` (строковое, хранит ID пользователя),
  `ABSENCE_TYPE` (список), плюс служебные `STATE` и `FINISH_STATE`. На своей версии повторите ту же
  проверку перед первой записью: структура компании может переехать в другой модуль.
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
- Что вернулось на стенде: ключи — `VACATION`, `ASSIGNMENT`, `LEAVESICK`, `LEAVEMATERINITY`,
  `LEAVEUNPAYED`, `UNKNOWN`, `OTHER`, `PERSONAL`; у каждого `ENUM_ID`, `NAME` и `ACTIVE`. **`ACTIVE`
  — это «считается отпуском»**, а не «включён»: у командировки, прогула и «другого» он `false`.
  Ключа `XML_ID` в выдаче нет, хотя события отсутствий отдают именно `XML_ID` значения списка
  (см. ниже) — сопоставляйте по `ENUM_ID`.
- `getVacationTypes()` берёт значения **общего** инфоблока (`UserAbsence::getIblockId()`); если у
  сайта свой инфоблок, ENUM_ID оттуда не подойдёт — читайте значения свойства своего инфоблока.

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
- **Интранет подписан на события инфоблоков и сам всё доделывает.** `Absence\Event::onAfterIblockElementAdd`
  проверяет, что элемент попал в инфоблок графика, и тогда: сбрасывает кэш отсутствий
  (`UserAbsence::cleanCache()`), ставит агенты `\Bitrix\Intranet\Absence\Agent::start(<ID>)` и
  `::end(<ID>)` и заводит событие в календаре. На стенде после `Add()` оба агента появились в
  `b_agent`, а после удаления элемента исчезли. Вывод: писать надо именно через API инфоблока —
  прямая запись в таблицы всё это потеряет.

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
  `PER_USER` (группировать по сотруднику).
- **Формат ответа** (стенд): массив, сгруппированный по ID сотрудника; в каждой записи — поля
  элемента (`ID`, `NAME`, `DATE_ACTIVE_FROM`, `DATE_ACTIVE_TO`) и свойства в виде
  `PROPERTY_USER_VALUE`, `PROPERTY_ABSENCE_TYPE_VALUE` (название типа, не код).
- `CIntranetUtils::IsUserAbsent(128)` отвечает про **текущий момент**, а не про переданный период:
  для проверки импорта прошлых или будущих дат он не годится ([[entity-cintranet-utils]]).
- Запись видна в интерфейсе «График отсутствий».
- `UserAbsence::getCurrentMonth()` кэшируется, но при записи через API инфоблока кэш сбрасывает сам
  интранет ([[entity-user-absence]]).

## События начала и окончания
Подсистема выбрасывает `OnStartAbsence` и `OnEndAbsence` — начало и окончание отпуска
([Начало и Окончание](https://bx24devbook.website.yandexcloud.net/Modul_Intranet/Otsutstvia.html#nacalo-i-okoncanie)).

| Параметр | Значение (по коду `Intranet\Absence\Agent`, стенд) |
|---|---|
| `USER_ID` | значение свойства `USER` элемента |
| `ABSENCE_TYPE` | **`XML_ID` значения списка**, а не число |
| `START`, `END` | `\Bitrix\Main\Type\DateTime`, а не строки |
| `DURATION` | длительность в секундах (`END` − `START`) |

Возвращаемое значение не обрабатывается.

**Модуль события — `intranet`, а не `crm`, как в примере книги** (`new Event('intranet',
'OnStartAbsence', $data)` в `Bitrix\Intranet\Absence\Agent`). Событие нового ядра, подписка —
`addEventHandler('intranet', 'OnStartAbsence', …)` ([[entity-event-manager]]).

**Событие приходит от агента, а не от записи.** При добавлении элемента интранет ставит агенты
`Agent::start(<ID>)` и `Agent::end(<ID>)`; сработают они, когда дойдёт очередь агентов. Импорт
задним числом события «начала» вовремя не даст — рассчитывать на них как на триггер импорта нельзя.

## Что прогнали на стенде

Коробка в Docker, 2026-09-22: тестовое отсутствие записано и удалено.

| Проверка | Результат |
|---|---|
| График — инфоблок интранета | да: инфоблок в типе `structure`, опция `iblock_absence` заполнена |
| Свойства инфоблока | `USER` (строковое), `ABSENCE_TYPE` (список), `STATE`, `FINISH_STATE` |
| `getVacationTypes()` | восемь типов, ключи — символьные коды, `ACTIVE` = «считается отпуском» |
| Запись `CIBlockElement::Add` | элемент создан, ошибок нет |
| Реакция интранета | сброшен кэш, заведены агенты `Absence\Agent::start/end` |
| `GetAbsenceData` | вернул запись, сгруппированную по ID сотрудника |
| `IsUserAbsent()` | отвечает про текущий момент — для прошлых дат `false` |
| Удаление элемента | элемент и его агенты исчезли |

Не проверяли: событие в календаре, которое интранет заводит вместе с записью, и срабатывание
`OnStartAbsence` по агенту.

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
| Обработчик `OnStartAbsence` молчит | подписка на `crm` из примера книги или агенты не отработали | подписываться на модуль `intranet`; проверить очередь агентов |

## Источники и связанное
- Книга: [Отсутствия](https://bx24devbook.website.yandexcloud.net/Modul_Intranet/Otsutstvia.html),
  [Организационная структура → API](https://bx24devbook.website.yandexcloud.net/Modul_Intranet/Orgstruktura.html#api)
- [[entity-user-absence]] — чтение отсутствий, D7
- [[entity-cintranet-utils]] — `IsUserAbsent()` и `GetAbsenceData()`
- [[concept-org-structure]] — оргструктура на инфоблоках интранета и приём с `'-1'`
- [[entity-config-option]] — чтение опций модулей

[← Администрирование портала](_index-administration.md)
