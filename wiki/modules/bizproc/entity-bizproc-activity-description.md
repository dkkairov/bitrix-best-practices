---
title: "Файл .description.php действия БП"
type: entity
module: bizproc
edition: box
status: verified
provenance: mixed
verified: "2026-09-22 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21); курс 57 dev.1c-bitrix.ru (уроки 23034, 12409, 3771); код стенда (коробка, bizproc 26.1075.0): ActivitySearcher\\Searcher, ActivityFilterChecker, PropertiesDialog, компонент bizproc.workflow.edit. Видимость результатов в интерфейсе роботов не проверялась"
tags: [bizproc, активити, робот, условие, description-php, метаописание]
sources: ["[[source-devbook-bizproc]]", "[[source-course57-developer]]", "[[source-course57-templates-designer]]", "[[source-course57-actions-core]]"]
related: ["[[entity-cbp-activity]]", "[[concept-bizproc-engine]]", "[[recipe-bizproc-custom-task-activity]]", "[[entity-bizproc-field-type]]", "[[concept-bizproc-activity-catalog]]", "[[antipattern-bizproc-php-code-activity]]"]
aliases: []
updated: "2026-09-22"
---

# Файл `.description.php` действия БП

**Что это:** файл в каталоге действия, где объявлен массив `$arActivityDescription` — паспорт
действия для движка: название, тип (действие, робот или условие), класс, раздел в дизайнере и в
списке роботов, в каких документах действие доступно и что оно возвращает
([Действия → .description.php](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Dejstvia.html#description-php)).

> **Изменено 2026-09-22: из черновика — в проверенные.** Страница была черновиком из-за противоречия
> книги о `RETURN` и `ADDITIONAL_RESULT`. Курс 57 и код ядра его сняли для дизайнера (раздел ниже);
> порядок поиска, «первое совпадение», `FILTER` и разделы `CATEGORY` подтверждены кодом ядра. Открыт
> только вопрос, как результаты видны в интерфейсе роботов.

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
- **Первое совпадение перекрывает штатное действие** — подтверждено кодом ядра
  (`Bitrix\Bizproc\Runtime\ActivitySearcher\Searcher`: уже найденное имя папки дальше пропускается).
  Штатные действия на стенде лежат в `/bitrix/activities/bitrix/`. Так можно подменить штатное
  действие — и так же легко сделать это нечаянно: примеры курса `logactivity` и `task2activity`
  ([урок 2906](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=2906),
  [урок 2904](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=2904)) совпадают со
  штатными «Запись в отчет» и «Поставить задачу».
- Курс кладёт свои действия в `/bitrix/activities/custom/`
  ([урок 23034](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=23034)) — это
  устаревший совет: `/local/activities/…` ищется раньше и не затрагивается обновлениями.

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

В ядре 26.x встречаются ещё `EXCLUDED` (скрыть действие по условию), `PRESETS` (варианты одного
действия с заданными свойствами — так «…элемента смарт-процесса» стало вариантом «…элемента CRM»),
`SORT`, а описание всё чаще собирают классом `Bitrix\Bizproc\Activity\ActivityDescription`. Курс
называет `ADDITIONAL_RESULT` доступным с bizproc 17.0.3.

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
- Встроенные разделы в ядре (bizproc 26.1075.0, компонент `bizproc.workflow.edit`): `document`
  «Обработка документа», `task` «Задания», `logic` «Конструкции», `interaction` «Уведомления», `rest`
  «Действия приложений», `other` «Прочее»; к ним добавляются разделы из `OWN_ID`/`OWN_NAME` действий.
  Книга называет только `other` и свой раздел; курс — `OWN_ID`/`OWN_NAME` и предупреждает, что правка
  `.description.php` системных действий затрётся обновлением
  ([урок 12409](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=12409)). Симптомы
  ошибок — в [[recipe-bizproc-custom-task-activity]].

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

- **Неполный тип — фильтр по префиксу** (код ядра `Bitrix\Bizproc\Activity\Mixins\ActivityFilterChecker`):
  правило сравнивается с типом документа по порядку элементов и заканчивается там, где заканчивается
  правило. `['crm', 'CCrmDocumentDeal']` действует на все сделки, `['crm']` — на всю CRM. Правило-строка
  сравнивается с редакцией продукта, ключ `MIN_API_VERSION` скрывает действие на старом API.
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

## `RETURN` и `ADDITIONAL_RESULT`: противоречие книги снято

> **Решено 2026-09-22 (курс 57 и код ядра).** Книга противоречила себе: заметка в
> [RETURN](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Dejstvia.html#return)
> говорила, что результаты без `ADDITIONAL_RESULT` другим действиям недоступны, а пример
> [helloworldactivity](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Dejstvia.html#additional-result)
> обходится одним `RETURN`. Прав пример:
> - курс: результаты штатных заданий доступны во «Вставке значения → Дополнительные результаты» сразу
>   после добавления действия ([урок 3771](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=3771)),
>   а в их `.description.php` только `RETURN`;
> - ядро: список результатов для дизайнера строится из `RETURN`; `ADDITIONAL_RESULT` читается, только
>   если `RETURN` пуст, и перечисляет **свойства-карты** (`PropertiesDialog::extractChildProperties`).
>   Для ссылок и роботов `CBPRuntime::getActivityReturnProperties()` объединяет `RETURN` и карты из
>   `ADDITIONAL_RESULT`. Штатные образцы карт — `EntityFields` («Выбор данных crm»),
>   `DynamicEntityFields` («Получить информацию об элементе CRM»), `FieldsMap` (элемент списка);
> - практика команды «указывать в `ADDITIONAL_RESULT` ключ из `RETURN`» ничего не даёт — убрана из
>   [[recipe-bizproc-custom-task-activity]].
>
> Правило: постоянные результаты — в `RETURN` (+ `SetPropertiesTypes()`), динамические — свойство-карта
> в `ADDITIONAL_RESULT`, и тогда без `RETURN`. Как результаты видны в интерфейсе роботов — проверить
> на своём действии.

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
- **Результат не виден во «Вставке значения»** — проверить, что ключ есть в `RETURN`; если у действия
  есть и `RETURN`, и `ADDITIONAL_RESULT`, дизайнер покажет только `RETURN`.

## Связанное
- [[entity-cbp-activity]] — класс действия, которое описывает файл
- [[entity-cbp-activity-condition]] — класс условия
- [[concept-bizproc-engine]] — типы активити и структура каталога
- [[entity-bizproc-field-type]] — типы для `RETURN` и `normalizeProperty()`
- [[recipe-bizproc-custom-task-activity]] — действие с заданием целиком, встроенные разделы дизайнера
- [[concept-bizproc-activity-catalog]] — как штатные действия выглядят в файле шаблона
- [[antipattern-bizproc-php-code-activity]] — почему своё действие, а не «PHP код»

[← Бизнес-процессы](_index-bizproc.md)
