---
title: "Файл .description.php действия БП"
type: entity
module: bizproc
edition: box
status: draft
provenance: mixed
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Модуль Бизнес-процессы / Действия, Создание своего действия, Свои условия; без проверки на стенде"
tags: [bizproc, активити, робот, условие, description-php, метаописание]
sources: ["[[source-devbook-bizproc]]"]
related: ["[[entity-cbp-activity]]", "[[concept-bizproc-engine]]", "[[recipe-bizproc-custom-task-activity]]", "[[entity-bizproc-field-type]]", "[[concept-bizproc-activity-catalog]]", "[[antipattern-bizproc-php-code-activity]]"]
aliases: []
updated: "2026-09-21"
---

# Файл `.description.php` действия БП

**Что это:** файл в каталоге действия, где объявлен массив `$arActivityDescription` — паспорт
действия для движка: название, тип (действие, робот или условие), класс, раздел в дизайнере и в
списке роботов, в каких документах действие доступно и что оно возвращает
([Действия → .description.php](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Dejstvia.html#description-php)).

> **Черновик** из-за противоречия книги о `RETURN` и `ADDITIONAL_RESULT` (раздел ниже). Остальное —
> по книге, кроме пунктов с пометкой «вывод команды».

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | файл-описание действия, робота или условия |
| Модуль | `bizproc` |
| Где лежит | `<каталог действия>/.description.php`, фразы — `lang/<язык>/.description.php` |
| Что объявляет | массив `$arActivityDescription` |
| Edition | box |

## Где движок ищет действия

Каталоги перебираются по порядку до первого совпадения; так же ищутся и условия
([Свои действия → Расположение](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Svoi_dejstvia.html#raspolozenie),
[Свои условия → Расположение](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Svoi_uslovia.html#raspolozenie)):

1. `/local/activities/`
2. `/local/activities/custom/`
3. `/bitrix/activities/custom/`
4. `/bitrix/activities/bitrix/`
5. `/bitrix/modules/bizproc/activities/`

`/bitrix` здесь — значение константы `BX_ROOT` по умолчанию.

- Каждое действие — отдельный каталог. Имя каталога — имя класса без префикса `CBP`, строчными:
  `CBPVendorDealCheckActivity` → `vendordealcheckactivity/`
  ([Действия → Создание своего действия](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Dejstvia.html#sozdanie-svoego-dejstvia)).
- Класс — в файле с тем же именем: `vendordealcheckactivity/vendordealcheckactivity.php`. Рядом —
  `.description.php`, `properties_dialog.php` (настройки в дизайнере), `robot_properties_dialog.php`
  (настройки робота) и `lang/` — см. [[concept-bizproc-engine|структуру каталога]].
- **Вывод команды:** раз берётся первое совпадение, одноимённый каталог выше по списку перекрывает
  штатное действие из `/bitrix/modules/bizproc/activities/`. Так можно подменить штатное действие —
  и так же легко сделать это нечаянно. На стенде не проверялось.

## Ключи `$arActivityDescription`

| Ключ | Тип | Для чего |
|---|---|---|
| `NAME` | string | название в списке действий и в заголовке окна настроек |
| `DESCRIPTION` | string | описание в окне настроек |
| `TYPE` | string или array | `activity`, `robot_activity` или массив из них; у условия — только `condition` |
| `CLASS` | string | имя PHP-класса без `CBP`; совпадает с именем каталога без учёта регистра |
| `JSCLASS` | string | JS-класс отрисовки в редакторе; по умолчанию `BizProcActivity` |
| `CATEGORY` | array | раздел в дизайнере БП (для `activity`) |
| `ROBOT_SETTINGS` | array | группы и порядок в списке роботов (для `robot_activity`) |
| `FILTER` | array | в шаблонах каких документов действие показывать |
| `RETURN` | array | возвращаемые значения |
| `ADDITIONAL_RESULT` | array | коды свойств-карт с результатами, состав которых определяется на ходу |

Регистр `CLASS` и каталога может различаться: в примере книги `HelloWorldActivity` лежит в
`helloworldactivity/`
([Свои действия → .description.php](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Svoi_dejstvia.html#fajl-description-php)).

### `TYPE` — действие, робот или условие
- `activity` — действие дизайнера БП, `robot_activity` — [[entity-robots-triggers|робот]],
  `['activity', 'robot_activity']` — один класс в обеих ролях ([[concept-bizproc-engine]]). Когда
  нужен робот, а когда БП, — [[pattern-robots-vs-bizproc-decision]].
- `condition` — условие для блоков «Условие» и «Цикл»; класс наследует `CBPActivityCondition`
  ([[entity-cbp-activity-condition]]).
- В тексте книги встречаются опечатки `activiy` и `acitivty` — правильно `activity`.

### `CATEGORY` — раздел в дизайнере
- Минимум, чтобы действие появилось в панели дизайнера, — `['ID' => 'other']`, раздел «Прочее» (в
  книге он же «Другое»)
  ([CATEGORY](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Dejstvia.html#category)).
- Свой раздел — ключи `OWN_ID` (символьный код) и `OWN_NAME` (название); в примере книги `ID`
  равен `OWN_ID`.
- Другие встроенные коды разделов книга не перечисляет. Список, собранный командой на практике, и
  симптомы ошибок — в [[recipe-bizproc-custom-task-activity]].

### `ROBOT_SETTINGS` — место в списке роботов
`GROUP` — массив кодов групп, в которых показывается робот; `SORT` — порядок внутри группы
([ROBOT_SETTINGS](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Dejstvia.html#robot-settings)).
Группы на момент написания книги:

| Код | Группа в интерфейсе |
|---|---|
| `clientCommunication` | Коммуникация с клиентом |
| `informingEmployee` | Информирование сотрудников |
| `employeeControl` | Контроль сотрудников |
| `paperwork` | Оформление документов |
| `payment` | Оплата товаров и услуг |
| `delivery` | Управление доставкой |
| `repeatSales` | Повторные продажи |
| `ads` | Запуск рекламы |
| `elementControl` | Управление элементом |
| `clientData` | Данные о клиентах |
| `taskManagement` | Управление задачами |
| `modificationData` | Хранение и изменение данных |
| `digitalWorkplace` | Автоматизация рабочих мест |
| `other` | Другие роботы |

Список может меняться с версиями — сверять на своём портале.

### `FILTER` — в каких документах доступно
`INCLUDE` — типы документов, где действие показывать; `EXCLUDE` — где скрыть. Ключ работает и для
условий
([FILTER](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Dejstvia.html#filter),
[Свои условия → .description.php](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Svoi_uslovia.html#fajl-description-php)).

Тип документа — массив «модуль, класс документа, код типа», например
`['crm', 'CCrmDocumentCompany', 'COMPANY']`
([Работа с окружением → Глобальные хранилища](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Okruzenie.html#global-nye-hranilisa)).
В примерах книги:

```php
'FILTER' => [
    'INCLUDE' => [
        ['crm', 'CCrmDocumentDeal'],                 // только шаблоны сделок
    ],
],

'FILTER' => [
    'EXCLUDE' => [                                   // скрыть в одном смарт-процессе
        ['crm', 'Bitrix\\Crm\\Integration\\BizProc\\Document\\Dynamic', 'DYNAMIC_123'],
    ],
],
```

- **Проверить на стенде:** для сделок тип задан без кода типа — судя по примеру, такой фильтр
  действует на все типы этого класса документа. Прямо книга этого не говорит.
- **Вывод команды:** в `DYNAMIC_<ID>` зашит ID типа [[entity-smart-process|смарт-процесса]]
  конкретного портала; после переноса фильтр укажет на чужой тип или ни на что
  ([[antipattern-bizproc-hardcoded-portal-ids]]).

### `RETURN` — возвращаемые значения
Ключ — имя свойства-результата, значение — `NAME` (подпись) и `TYPE` (тип)
([RETURN](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Dejstvia.html#return)).
Типы — константы `\Bitrix\Bizproc\FieldType`: `bool`, `date`, `datetime`, `double`, `file`, `int`,
`select`, `internalselect`, `string`, `text`, `user`, `time` ([[entity-bizproc-field-type]]).

В классе результат — обычное свойство действия
([Свои действия → файл класса](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Svoi_dejstvia.html#fajl-helloworldactivity-php)):
- объявить в `$arProperties` конструктора и задать тип через `SetPropertiesTypes()`;
- заполнить либо свойством (`$this->CheckResult = …`, стиль `CBPActivity` — тогда в конструкторе
  значение обязательно `null`, иначе действие вернёт его), либо через
  `$this->preparedProperties['CheckResult']` (стиль `BaseActivity`) — подробнее в
  [[entity-cbp-activity]].

В примере книги тип результата указан и в `RETURN`, и в `SetPropertiesTypes()` — одинаковым. Держим
их синхронными (правило команды).

### `ADDITIONAL_RESULT` — результаты, которые определяются на ходу
Список кодов **свойств действия**, в каждом из которых лежит карта «имя результата → описание
типа». Всё из карты дизайнер показывает как результаты и даёт подставить в параметры других
действий через «Вставку значения»
([ADDITIONAL_RESULT](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Dejstvia.html#additional-result)).
Нужен, когда состав результатов заранее не известен — например, зависит от полей, выбранных в
настройках.

Штатный образец — «Получить информацию об элементе списка» (`CBPGetListsDocumentActivity`) и его
свойство `FieldsMap`:
- карта строится при сохранении настроек, в `GetPropertiesDialogValues()`: описание каждого
  выбранного поля документа проходит через `FieldType::normalizeProperty()`;
- при выполнении действие регистрирует типы по карте (`SetPropertiesTypes()`) и заполняет
  одноимённые свойства;
- в `ReInitialize()` эти свойства обнуляются.

Схема для своего действия (своими словами по книге, на стенде не проверялась):

```php
// .description.php
'ADDITIONAL_RESULT' => ['ResultMap'],

// GetPropertiesDialogValues(): карта «код поля → описание типа»
$properties['ResultMap'][$fieldCode] = \Bitrix\Bizproc\FieldType::normalizeProperty($documentField);

// Execute(): объявить типы и заполнить значения
$map = (array)$this->ResultMap;
$this->SetPropertiesTypes($map);
foreach (array_keys($map) as $fieldCode) {
    $this->arProperties[$fieldCode] = $loaded[$fieldCode] ?? null;   // $loaded — данные, которые получило действие
}
```

## `RETURN` и `ADDITIONAL_RESULT`: книга противоречит себе

> **Проверить на стенде.**
> - [RETURN](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Dejstvia.html#return)
>   и [ADDITIONAL_RESULT](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Dejstvia.html#additional-result):
>   результаты из `RETURN`, не перечисленные в `ADDITIONAL_RESULT`, другим действиям недоступны — их
>   нельзя использовать при настройке следующих шагов. В заметке ключ назван `RESULT` — опечатка, по
>   смыслу это `RETURN`.
> - [Свои действия → файл класса](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Svoi_dejstvia.html#fajl-helloworldactivity-php):
>   в `.description.php` примера есть только `RETURN`, и книга утверждает, что результат уже можно
>   подставить в другие действия, например в «Запись в отчёт». Во вступлении пример обещает отдавать
>   сообщение как дополнительный результат, но `ADDITIONAL_RESULT` в итоговом коде нет.
> - У команды третий вариант: [[recipe-bizproc-custom-task-activity]] указывает оба ключа, причём в
>   `ADDITIONAL_RESULT` кладёт сам ключ из `RETURN`, а не свойство-карту, как описывает книга.
>
> До проверки: постоянные результаты объявлять в `RETURN`, динамические — через `ADDITIONAL_RESULT`
> со свойством-картой. После выкладки убедиться, что результат виден во «Вставке значения» и в
> дизайнере БП, и в настройках роботов.

## Условие: минимальное описание
Условию хватает `NAME` и `TYPE => 'condition'`, при необходимости — `FILTER`. `CLASS` в примере книги
нет: имя класса — `CBP` плюс имя каталога, регистр не важен
([Свои условия → .description.php](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Svoi_uslovia.html#fajl-description-php),
[класс условия](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Svoi_uslovia.html#fajl-dicecondition-php)).

```php
$arActivityDescription = [
    'NAME' => Loc::getMessage('VENDOR_WORKDAY_CONDITION_NAME'),
    'TYPE' => 'condition',
];
```

## Пример: действие и робот для сделок

```php
<?php
// /local/activities/custom/vendordealcheckactivity/.description.php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;

$arActivityDescription = [
    'NAME'        => Loc::getMessage('VENDOR_DEALCHECK_NAME'),
    'DESCRIPTION' => Loc::getMessage('VENDOR_DEALCHECK_DESCR'),
    'TYPE'        => ['activity', 'robot_activity'],
    'CLASS'       => 'VendorDealCheckActivity',      // класс CBPVendorDealCheckActivity
    'JSCLASS'     => 'BizProcActivity',
    'CATEGORY'    => ['ID' => 'other'],
    'ROBOT_SETTINGS' => [
        'GROUP' => ['elementControl'],
        'SORT'  => 3000,
    ],
    'FILTER' => [
        'INCLUDE' => [
            ['crm', 'CCrmDocumentDeal'],
        ],
    ],
    'RETURN' => [
        'CheckResult' => [
            'NAME' => Loc::getMessage('VENDOR_DEALCHECK_RESULT'),
            'TYPE' => 'string',                      // FieldType::STRING
        ],
    ],
];
```

Фразы — в `lang/ru/.description.php` того же каталога: `$MESS['VENDOR_DEALCHECK_NAME'] = '…';`.

## Подводные камни
- **Опечатки в примерах книги.** В деревьях файлов каталог записан как `/local/activites/…` —
  правильно `activities`, иначе движок каталог не найдёт. Значения `TYPE` — только из списка выше.
- **Три имени должны сойтись:** каталог — без `CBP` строчными, `CLASS` — без `CBP`, класс — с `CBP`.
- **Занятое имя перекрывает штатное действие** (вывод команды, см. выше) — своим действиям давать
  префикс вендора.
- **`FILTER` с `DYNAMIC_<ID>`** привязывает действие к конкретному порталу (вывод команды).
- **Действие не появилось в дизайнере** — по опыту команды: проверить `CATEGORY['ID']` и сбросить
  кэш ([[recipe-bizproc-custom-task-activity]]).
- **Результат не виден во «Вставке значения»** — см. противоречие про `RETURN` и
  `ADDITIONAL_RESULT`; проверять и в дизайнере, и в роботах.

## Связанное
- [[entity-cbp-activity]] — класс действия, которое описывает файл
- [[entity-cbp-activity-condition]] — класс условия
- [[concept-bizproc-engine]] — типы активити и структура каталога
- [[entity-bizproc-field-type]] — типы для `RETURN` и `normalizeProperty()`
- [[recipe-bizproc-custom-task-activity]] — действие с заданием целиком, встроенные разделы дизайнера
- [[concept-bizproc-activity-catalog]] — как штатные действия выглядят в файле шаблона
- [[antipattern-bizproc-php-code-activity]] — почему своё действие, а не «PHP код»

[← Бизнес-процессы](_index-bizproc.md)
