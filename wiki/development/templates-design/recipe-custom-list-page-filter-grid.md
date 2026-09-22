---
title: "Своя страница-список: фильтр, грид, тулбар"
type: recipe
module: templates-design
edition: box
status: verified
provenance: mixed
verified: "2026-09-22 / коробка в Docker, main 26.750.0: серверная часть прогнана из консоли и сверена с кодом ядра (фильтр и getValue(), описание полей, навигация, настройки грида, панель действий, событие Grid::beforeRequest); интерфейс в браузере не проверялся. Текст — «Книга разработчика Bitrix24» (снимок 2026-09-21): UI — Тулбар, Кнопки, Фильтр, Таблицы; Технологии — Отложенные функции"
tags: [ui, фильтр, грид, тулбар, список, компонент, панель-действий, производительность]
sources: ["[[source-devbook-ui]]"]
related: ["[[entity-custom-filter]]", "[[entity-filter-component]]", "[[entity-grid-component]]", "[[entity-grid-options]]", "[[entity-toolbar]]", "[[concept-ui-subsystem]]", "[[concept-deferred-functions-and-page-areas]]"]
aliases: []
updated: "2026-09-22"
---

# Своя страница-список: фильтр, грид, тулбар

> **Проверено на стенде** (коробка в Docker, `main` 26.750.0, 2026-09-22): серверная часть —
> фильтр (`getValue()`, описание полей), навигация, настройки грида, панель действий — прогнана из
> консоли и сверена с кодом ядра; итоги в разделе «Что проверено и что исправлено». Вёрстку и
> поведение в браузере (перерисовка по AJAX, меню строки, групповое действие) на стенде не
> смотрели — это осталось в «Проверить в браузере».

**Результат:** своя страница со списком записей, которая выглядит и ведёт себя как списки продукта:
фильтр в шапке, грид с сортировкой, настройкой колонок, пагинацией, меню строки и групповым
действием. Всё — на штатных компонентах ([[concept-ui-subsystem|UI-подсистема]]).

**Когда применять:** нужна страница-список для своей сущности (своя ORM-таблица, данные внешней
системы, отчёт), а штатного списка для неё нет. **Только коробка.**

## Предусловия
- Решён вопрос «модулем или нет?» (`CLAUDE.md` §9). Ниже — вариант «решение»: классы в
  `local/php_interface/classes/` ([[pattern-local-solution-structure]]), компонент в
  `local/components/`. Для модуля те же классы кладутся в его `lib/`
  ([[recipe-module-structure-and-install]]).
- Есть ORM-сущность списка — в примере синтетическая `Vendor\Project\Item\ItemTable` (наследник
  `DataManager`) с полями `ID`, `TITLE`, `TYPE`, `CREATED_AT`, `AUTHOR_ID`.
- Страница открывается в шаблоне Bitrix24; в другом шаблоне тулбар вызывают явно (см. «Подводные
  камни»).

## Шаг 1. Свой фильтр: провайдер данных и наследник `Filter`

Фильтр — обёртка над провайдерами данных: провайдер описывает поля, обёртка отдаёт их компоненту и
превращает выбор пользователя в фильтр для ORM
([Свой фильтр → Архитектура](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Filtr/Svoj_filtr.html#arhitektura-fil-tra)).
Справочник классов — [[entity-custom-filter]], типы полей — [[entity-filter-field-adapter|FieldAdapter]].

- **Наследник `\Bitrix\Main\Filter\Filter` заводим всегда, даже пустой.** Совет автора книги: так
  проще контролировать аргументы там, где код зависит от фильтра, и есть где переопределить
  `prepareFilterValue`, если штатная обработка диапазонов не устроит
  ([Получение фильтра](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Filtr/Svoj_filtr.html#polucenie-fil-tra)).
- **Провайдер** обязан реализовать `getSettings()`, `prepareFields()` (поля — через
  `$this->createField()`) и `prepareFieldData($fieldID)` (мета-описание поля или `null`). Наследник
  `EntityDataProvider` сам подставляет подписи вместо кодов полей, но требует ещё
  `getFieldName($fieldID)`
  ([Провайдеры данных](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Filtr/Svoj_filtr.html#provajdery-dannyh)).
- **Даты: метод обязателен.** `Filter::getValue()` в конце вычищает все ключи с постфиксами
  (`_datesel`, `_from`, `_to`, `_month`, `_quarter`, `_year`, `_days`, `_numsel`, `_isEmpty`,
  `_hasAnyValue`, `_label`). Если провайдер не переложил границы диапазона в `>=` / `<=` в
  `prepareListFilterParam()`, **условие по дате просто исчезнет** — выборка вернёт всё (стенд).
- **`partial: true` — не косметика.** `prepareFieldData($fieldID)` вызывается **только** для полей
  с этим ключом и только когда поле реально понадобилось (`Field::assemble()`). Без `partial`
  поле уходит в компонент голым — `id`, `name`, `type`, — то есть **список останется без
  вариантов, а селектор без настроек** (стенд). Смысл ключа — не дёргать тяжёлые выборки
  (справочники, пользователи) для полей, которые пользователь не открыл.
- **«Показывать по умолчанию» — `'default' => true`** в параметрах `createField()`: поле попадает
  в `getDefaultFieldIDs()` и в описание поля ключом `default` (стенд).
- Чтобы фильтр создавала фабрика `Factory::createEntityFilter()`, нужен свой обработчик события
  `main:OnBuildFilterFactoryMethods`
  ([Получение фильтра](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Filtr/Svoj_filtr.html#polucenie-fil-tra)).
  Для одной своей страницы хватает `new` (вывод команды).

```php
<?php
// local/php_interface/classes/Vendor/Project/Item/ItemDataProvider.php
namespace Vendor\Project\Item;

use Bitrix\Main\Filter\EntityDataProvider;
use Bitrix\Main\Filter\Settings;
use Bitrix\Main\UI\Filter\DateType;
use Bitrix\Main\UI\Filter\FieldAdapter;

class ItemDataProvider extends EntityDataProvider
{
    public const TYPES = ['NEW' => 'Новая', 'DONE' => 'Завершена'];
    private const CAPTIONS = [                       // в проекте — Loc::getMessage()
        'TITLE'      => 'Название',
        'TYPE'       => 'Тип',
        'CREATED_AT' => 'Создана',
        'AUTHOR_ID'  => 'Автор',
    ];

    /** @var Settings */
    protected $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function getSettings(): Settings
    {
        return $this->settings;
    }

    public function prepareFields(): array
    {
        // partial — как в примере книги; смысл ключа книга не объясняет
        return [
            'TITLE'      => $this->createField('TITLE', ['type' => FieldAdapter::STRING, 'partial' => true]),
            'TYPE'       => $this->createField('TYPE', ['type' => FieldAdapter::LIST, 'partial' => true]),
            'CREATED_AT' => $this->createField('CREATED_AT', ['type' => FieldAdapter::DATE]),
            'AUTHOR_ID'  => $this->createField('AUTHOR_ID', [
                'type'    => FieldAdapter::ENTITY_SELECTOR,
                'partial' => true,
            ]),
        ];
    }

    public function prepareFieldData($fieldID): ?array
    {
        switch ($fieldID) {
            case 'TYPE':
                return ['params' => ['multiple' => 'Y'], 'items' => self::TYPES];
            case 'AUTHOR_ID':
                return ['params' => [
                    'multiple'      => 'Y',
                    'dialogOptions' => [
                        'context'  => $this->getSettings()->getID(),
                        'entities' => [['id' => 'user', 'options' => ['intranetUsersOnly' => true]]],
                    ],
                ]];
            case 'TITLE':
            case 'CREATED_AT':
                return ['params' => ['multiple' => 'N']];
        }
        return null;
    }

    protected function getFieldName($fieldID): string
    {
        return self::CAPTIONS[$fieldID] ?? (string)$fieldID;
    }

    // Диапазон дат → условия ORM. Идея из примера книги; проверить на стенде
    public function prepareListFilterParam(array &$filter, $fieldID)
    {
        $postfix = DateType::getPostfix();
        if (substr($fieldID, -strlen($postfix)) !== $postfix) {
            return;
        }
        $code = substr($fieldID, 0, -strlen($postfix));
        if (!empty($filter[$code . '_from'])) {
            $filter['>=' . $code] = $filter[$code . '_from'];
        }
        if (!empty($filter[$code . '_to'])) {
            $filter['<=' . $code] = $filter[$code . '_to'];
        }
    }
}
```

```php
<?php
// local/php_interface/classes/Vendor/Project/Item/ItemFilter.php
namespace Vendor\Project\Item;

class ItemFilter extends \Bitrix\Main\Filter\Filter
{
    // пустой наследник — совет книги; сюда же переопределение prepareFilterValue при нужде
}
```

## Шаг 2. Фильтр и кнопка в тулбаре

`Toolbar::addFilter()` принимает параметры компонента `bitrix:main.ui.filter`
([Тулбар → Добавление фильтра](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tulbar/Osnovnoe.html#dobavlenie-fil-tra));
поля отдаёт `$filter->getFieldArrays()`
([Получение полей](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Filtr/Svoj_filtr.html#polucenie-dostupnyh-dla-fil-tracii-polej)).
`GRID_ID` связывает фильтр с гридом: после применения фильтра грид обновится сам
([Обзор фильтра → Параметры](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Filtr/Obzor.html#parametry-komponenta)).
Параметры компонента — [[entity-filter-component]], фасад тулбара — [[entity-toolbar]], кнопки —
[[entity-ui-button]].

> **Противоречие книги разрешено: поля передаются в `FILTER`.** Компонент `bitrix:main.ui.filter`
> собирает поля из `$arParams["FILTER"]`; `FIELDS` — это ключ **результата** (`$arResult["FIELDS"]`),
> куда компонент кладёт уже подготовленные поля, а не входной параметр (код `class.php` компонента,
> коробка 26.750.0). Вступление главы «Свой фильтр», называющее обязательным `FIELDS`, ошибается —
> прав её же пример.

```php
<?php
// local/components/vendor/item.list/class.php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Filter\Settings;
use Bitrix\Main\Grid\Options as GridOptions;
use Bitrix\Main\Grid\Panel\Actions;
use Bitrix\Main\Grid\Panel\Types;
use Bitrix\Main\Loader;
use Bitrix\Main\UI\PageNavigation;
use Bitrix\UI\Buttons\Icon;
use Bitrix\UI\Toolbar\Facade\Toolbar;
use Vendor\Project\Item\ItemDataProvider;
use Vendor\Project\Item\ItemFilter;
use Vendor\Project\Item\ItemTable;

class VendorItemListComponent extends CBitrixComponent
{
    // ID не меняем: к ним привязаны сохранённые настройки пользователей
    private const FILTER_ID = 'VENDOR_ITEM_FILTER';
    private const GRID_ID   = 'VENDOR_ITEM_LIST';
    private const NAV_ID    = 'vendor-item-nav';
    private const SORTABLE  = ['ID', 'TITLE', 'CREATED_AT'];
    private const COLUMNS   = [
        ['id' => 'ID',         'name' => 'ID',       'sort' => 'ID',         'default' => true],
        ['id' => 'TITLE',      'name' => 'Название', 'sort' => 'TITLE',      'default' => true],
        ['id' => 'TYPE',       'name' => 'Тип',                              'default' => true],
        ['id' => 'CREATED_AT', 'name' => 'Создана',  'sort' => 'CREATED_AT', 'default' => true],
        ['id' => 'LINKED',     'name' => 'Связанные'], // без default: скрыта, пока не включат
    ];

    public function executeComponent()
    {
        Loader::includeModule('ui');

        $filter = new ItemFilter(
            self::FILTER_ID,
            new ItemDataProvider(new Settings(['ID' => self::FILTER_ID]))
        );
        Toolbar::addFilter([
            'FILTER_ID'      => $filter->getID(),
            'GRID_ID'        => self::GRID_ID,
            'FILTER'         => $filter->getFieldArrays(), // именно FILTER — см. выше
            'ENABLE_LABEL'   => true,
            'DISABLE_SEARCH' => false,
        ]);
        Toolbar::addButton(['link' => '/items/new/', 'text' => 'Добавить', 'icon' => Icon::ADD]);

        // … шаг 3
```

## Шаг 3. Выборка: фильтр, сортировка, страница, видимые колонки

- **Фильтр:** `$filter->getValue()` — массив, готовый для `filter` в `DataManager::getList()`; без
  аргументов значения берутся из сохранённого фильтра пользователя, но можно передать массив явно
  ([Получение значения](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Filtr/Svoj_filtr.html#polucenie-znacenia-fil-tra)).
  Что метод выбрасывает (стенд): поля, которых нет в фильтре; служебные `FILTER_ID`,
  `FILTER_APPLIED`, `PRESET_ID` и **строку поиска `FIND`**; все ключи с постфиксами диапазонов.
  **Поиск по строке сам не работает** — если он нужен, обрабатывайте `FIND` до вызова `getValue()`
  или в наследнике `Filter`.
- **Сортировка:** `Grid\Options::GetSorting($default)` возвращает структуру как у аргумента —
  `sort` и `vars`. В 26.750.0 метод **не смотрит в запрос**: он отдаёт либо сохранённую сортировку
  пользователя (`last_sort_by` / `last_sort_order` из `b_user_option`), либо переданное умолчание
  (стенд). Клик по заголовку уходит в собственный AJAX грида (`settings.ajax.php`,
  `GRID_SET_SORT`), который сохраняет присланные `by` и `order` **как есть, без проверки**
  (`CGridOptions::SetSorting`). Значит `sort` — это пользовательский ввод: в `order` пропускаем
  только поля из белого списка, иначе получим ORM-ошибку на несуществующем поле.
  `vars` компонент грида не использует — они пригодятся, только если вы сами строите ссылки
  сортировки; тогда разным гридам и правда нужны разные имена
  ([Просчитать сортировку](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tablicy/Personalnye_nastrojki.html#proscitat-sortirovku)).
- **Страница:** `GetNavParams()` отдаёт `nPageSize`, по умолчанию 20
  ([Размер страницы](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tablicy/Personalnye_nastrojki.html#razmer-postranicnoj-navigacii));
  объект `PageNavigation` потом уходит в грид как `NAV_OBJECT`
  ([Параметры навигации](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tablicy/Obzor.html#parametry-navigacii)).
- **Видимые колонки:** `getUsedColumns($default)` возвращает колонки из пресета пользователя, а без
  пресета — переданный набор; `GetVisibleColumns()` без пресета вернёт пустой массив. Кейс автора
  книги: две вычисляемые колонки занимали до 60 % времени построения грида, а нужны были меньше чем
  1 % сотрудников
  ([Отображаемые колонки](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tablicy/Personalnye_nastrojki.html#polucenie-otobrazaemyh-kolonok)).
  Дорогое считаем только для видимых колонок — [[entity-grid-options]].
- **Не из книги, но работает:** `PageNavigation::getOffset()`, `getLimit()`, `setRecordCount()` и
  ключ `count_total` проверены на стенде (страница 2 по 2 записи → `offset=2`, `limit=2`,
  `getPageCount()` считает по общему числу). `setPageSizes()` принимает **плоский список чисел**
  (в ядре — `range(1, 50)`) и служит белым списком для размера страницы из URL; массив
  `NAME` / `VALUE`, как в книге, ему не подходит — тот формат нужен гриду в `PAGE_SIZES`.

```php
        // … продолжение executeComponent()
        $gridOptions = new GridOptions(self::GRID_ID);
        $sort = $gridOptions->GetSorting([
            'sort' => ['ID' => 'DESC'],
            'vars' => ['by' => 'by', 'order' => 'order'], // второй грид на странице — свои имена
        ]);
        $order = array_intersect_key($sort['sort'], array_flip(self::SORTABLE)) ?: ['ID' => 'DESC'];

        $navParams = $gridOptions->GetNavParams(['nPageSize' => 20]);
        $nav = new PageNavigation(self::NAV_ID);
        $nav->allowAllRecords(false)->setPageSize($navParams['nPageSize']);
        $nav->initFromUri();

        $visible = $gridOptions->getUsedColumns(array_column(
            array_filter(self::COLUMNS, static fn (array $c): bool => !empty($c['default'])),
            'id'
        ));

        $result = ItemTable::getList([
            'select'      => ['ID', 'TITLE', 'TYPE', 'CREATED_AT'],
            'filter'      => $filter->getValue(),
            'order'       => $order,
            'offset'      => $nav->getOffset(),  // не из книги — сверить
            'limit'       => $nav->getLimit(),   // не из книги — сверить
            'count_total' => true,               // не из книги — сверить
        ]);
        $total = $result->getCount();
        $nav->setRecordCount($total);           // не из книги — сверить

        $items = [];
        while ($item = $result->fetch()) {
            $items[(int)$item['ID']] = $item;
        }
        $linked = in_array('LINKED', $visible, true) ? $this->loadLinked(array_keys($items)) : [];

        // … шаг 4
```

## Шаг 4. Грид

- Обязательны `GRID_ID`, `COLUMNS` (`HEADERS` — устаревшее имя) и `ROWS`
  ([Параметры грида](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tablicy/Obzor.html#parametry-komponenta)).
  Колонка без `default => true` по умолчанию скрыта
  ([Описание столбцов](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tablicy/Obzor.html#opisanie-stolbcov-kolonok)).
- Строка: `columns` — что показать, `data` — исходные значения (нужны для inline-редактирования),
  **`actions` — меню строки**
  ([Описание строк](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tablicy/Obzor.html#opisanie-strok)).
  Шаблон грида берёт `columns[<id>]`, а если ключа нет — **подставляет `data[<id>]`**; поэтому
  полный пример книги с одним `data` тоже работает (код шаблона, 26.750.0). Значение выводится
  **как HTML, без экранирования** — свои данные пропускаем через `htmlspecialcharsbx()`, иначе XSS
  через название записи.
- **Групповые действия — `ACTION_PANEL`** → `GROUPS` → `ITEMS`. Кнопка с `ONCHANGE` и действием
  `Actions::CALLBACK` вызывает глобальную JS-функцию, по желанию — после подтверждения. Скрипты из
  `DATA` выполняются цепочкой: упавший прерывает остальные
  ([Функция-обработчик](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tablicy/Panel_dejstvij.html#funkcia-obrabotcik)).
  Типовые кнопки даёт `Grid\Panel\Snippet`
  ([Сниппеты](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tablicy/Panel_dejstvij.html#snippety)),
  но как обработать на сервере их запросы (например, `delete`), книга не показывает.
- **`VALUE` — не для кнопок.** Штатная кнопка панели (`Snippet\Button::toArray()`) состоит из
  `TYPE`, `ID`, `NAME`, `CLASS`, `TEXT`, `TITLE`, `ONCHANGE`; `VALUE` есть у **пунктов
  выпадающего списка** (`Types::DROPDOWN`) — там пара `NAME` / `VALUE` (код ядра, 26.750.0).
- **Не путать:** `ACTIONS_LIST` / `ACTIONS` книга упоминает только в таблице параметров (со ссылкой
  на главу о панели действий), а сама глава разбирает лишь `ACTION_PANEL`. Для меню строки они не
  нужны — нужен `actions` в каждой строке.
- AJAX — как в полном примере книги: `AJAX_MODE`, `AJAX_ID`, `AJAX_OPTION_JUMP` и
  `AJAX_OPTION_HISTORY` = `N`
  ([Пример компонента](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tablicy/Obzor.html#primer-komponenta)).
- Если подсчёт всех строк дорогой, вместо `TOTAL_ROWS_COUNT` можно отдать свой HTML в
  `TOTAL_ROWS_COUNT_HTML`, который считает в фоне или по запросу пользователя
  ([Количество строк](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tablicy/Obzor.html#kolicestvo-strok)).
- **`ENABLE_COLLAPSIBLE_ROWS` не включаем:** по опыту автора книги вложенные строки создают большие
  сложности с постраничной навигацией, он советует избегать этой возможности
  ([Сворачивание строк](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tablicy/Obzor.html#svoracivanie-strok)).

Параметры — [[entity-grid-component]], JS-API — [[entity-bx-main-grid]].

```php
        // … продолжение executeComponent()
        $rows = [];
        foreach ($items as $id => $item) {
            $rows[] = [
                'id'      => (string)$id,
                'data'    => $item,
                'columns' => [
                    'ID'         => $id,
                    'TITLE'      => htmlspecialcharsbx((string)$item['TITLE']),
                    'TYPE'       => htmlspecialcharsbx(
                        ItemDataProvider::TYPES[$item['TYPE']] ?? (string)$item['TYPE']
                    ),
                    'CREATED_AT' => (string)$item['CREATED_AT'],
                    'LINKED'     => $linked[$id] ?? '',
                ],
                'actions' => [
                    ['text' => 'Открыть', 'onclick' => "document.location.href='/items/{$id}/'"],
                    ['delimiter' => true],
                    ['text' => 'Изменить', 'onclick' => "document.location.href='/items/{$id}/edit/'"],
                ],
            ];
        }

        $this->arResult['GRID'] = [
            'GRID_ID'             => self::GRID_ID,
            'COLUMNS'             => self::COLUMNS,
            'ROWS'                => $rows,
            'NAV_OBJECT'          => $nav,
            'PAGE_SIZES'          => [
                ['NAME' => '20', 'VALUE' => '20'],
                ['NAME' => '50', 'VALUE' => '50'],
            ],
            'TOTAL_ROWS_COUNT'    => $total,
            'AJAX_MODE'           => 'Y',
            'AJAX_ID'             => \CAjax::getComponentID('bitrix:main.ui.grid', '.default', ''),
            'AJAX_OPTION_JUMP'    => 'N',
            'AJAX_OPTION_HISTORY' => 'N',
            'SHOW_ROW_CHECKBOXES'       => true,
            'SHOW_CHECK_ALL_CHECKBOXES' => true,
            'SHOW_ROW_ACTIONS_MENU'     => true,
            'SHOW_GRID_SETTINGS_MENU'   => true,
            'SHOW_NAVIGATION_PANEL'     => true,
            'SHOW_PAGINATION'           => true,
            'SHOW_PAGESIZE'             => true,
            'SHOW_TOTAL_COUNTER'        => true,
            'ALLOW_SORT'                => true,
            'ALLOW_COLUMNS_SORT'        => true,
            'SHOW_ACTION_PANEL'         => true,
            'ACTION_PANEL' => ['GROUPS' => [[
                'CLASS' => 'vendor-item-actions',
                'ITEMS' => [[
                    'ID'       => 'vendor-item-archive',
                    'TYPE'     => Types::BUTTON,
                    'TEXT'     => 'В архив',
                    'ONCHANGE' => [[
                        'ACTION'          => Actions::CALLBACK,
                        'CONFIRM'         => true,
                        'CONFIRM_MESSAGE' => 'Перенести отмеченные записи в архив?',
                        'DATA'            => [['JS' => 'VendorItemList.archiveSelected()']],
                    ]],
                ]],
            ]]],
        ];
        $this->includeComponentTemplate();
    }

    private function loadLinked(array $ids): array
    {
        return []; // дорогой расчёт; вызывается, только если колонка LINKED видна
    }
}
```

Шаблон, скрипт группового действия и страница:

```php
<?php
// local/components/vendor/item.list/templates/.default/template.php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}
global $APPLICATION;
$APPLICATION->IncludeComponent('bitrix:main.ui.grid', '', $arResult['GRID']);
```

```js
// local/components/vendor/item.list/templates/.default/script.js
window.VendorItemList = {
    archiveSelected: function () {
        var grid = BX.Main.gridManager.getInstanceById('VENDOR_ITEM_LIST');
        var ids = grid ? grid.getRows().getSelectedIds() : [];
        if (!ids.length) {
            return;
        }
        // отправить ids своему контроллеру, после ответа перечитать грид:
        // BX.Main.gridManager.reload('VENDOR_ITEM_LIST');
    }
};
```

```php
<?php
// items/index.php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';
$APPLICATION->SetTitle('Записи');
$APPLICATION->IncludeComponent('vendor:item.list', '', []);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
```

## Проверка результата
- В шапке — заголовок, строка фильтра и кнопка «Добавить». Нет тулбара — см. «Подводные камни».
- Применение фильтра перерисовывает грид без перезагрузки страницы, выборка сужается.
- Что фильтр отдаёт в `getList`, видно на явном массиве — книга разрешает передать его в
  `getValue()`. Например, в [[entity-admin-php-console|командной PHP-строке]]:
  ```php
  $id = 'VENDOR_ITEM_FILTER';
  $filter = new \Vendor\Project\Item\ItemFilter(
      $id,
      new \Vendor\Project\Item\ItemDataProvider(new \Bitrix\Main\Filter\Settings(['ID' => $id]))
  );
  var_dump($filter->getValue([
      'TYPE'               => ['NEW'],
      'CREATED_AT_datesel' => \Bitrix\Main\UI\Filter\DateType::RANGE,
      'CREATED_AT_from'    => '01.09.2026',
      'CREATED_AT_to'      => '30.09.2026',
  ]));
  ```
  В результате не должно остаться ключей, которых нет в сущности (`CREATED_AT_from`, `_to`,
  `_datesel`, `FIND`).
- Клик по заголовку колонки меняет сортировку; адрес с `?by=NO_SUCH_FIELD&order=asc` не роняет
  страницу.
- Пагинация работает; выбранный размер страницы сохраняется после перезагрузки.
- Колонка «Связанные» включена в настройках грида — `loadLinked()` вызывается; выключена — нет.
- В консоли браузера `BX.Main.gridManager.getInstanceById('VENDOR_ITEM_LIST')` и
  `BX.Main.filterManager.getById('VENDOR_ITEM_FILTER')` возвращают объекты
  ([[entity-bx-main-filter]]); «В архив» после подтверждения получает ID отмеченных строк.

## Что проверено и что исправлено

Прогон из консоли и чтение кода ядра (коробка `main` 26.750.0, 2026-09-22). Десять вопросов, которые
оставались после книги:

| Вопрос | Ответ |
|---|---|
| `FILTER` или `FIELDS` для полей фильтра | **`FILTER`**; `FIELDS` — ключ `arResult` компонента |
| Нужен ли свой `prepareListFilterParam()` для дат | **да**: без него границы диапазона вычищаются и условие теряется |
| Что делает `getValue()` со строкой поиска | **выбрасывает `FIND`** — поиск обрабатываем сами |
| Смысл `partial` | ленивая догрузка данных поля; без него `prepareFieldData()` не вызовется вовсе |
| Как пометить поле «по умолчанию» | `'default' => true` в `createField()` → `getDefaultFieldIDs()` |
| `getOffset()` / `getLimit()` / `count_total` | работают, проверено выборкой по страницам |
| Формат `PageNavigation::setPageSizes()` | плоский список чисел (белый список размеров), **не** `NAME` / `VALUE` |
| `columns` и `data` в строке | `columns` приоритетнее, при отсутствии ключа берётся `data`; экранирование — на нас |
| Обязателен ли `VALUE` у элемента панели | только у пунктов `DROPDOWN`; у кнопки его нет |
| Откуда грид берёт сортировку | из настроек пользователя (свой AJAX `GRID_SET_SORT`), а не из `by` / `order` в URL |

Ещё два уточнения:

- **`AJAX_ID`** попадает в атрибут `data-ajaxid` контейнера и добавляется к запросам грида как
  `bxajaxid`. Обе формы вызова `CAjax::getComponentID()` (с шаблоном и без) дают валидный хеш —
  важно, чтобы значение было **постоянным** между отрисовками страницы.
- **Тулбар вне шаблона Bitrix24** вызывается как обычный компонент:
  `$APPLICATION->includeComponent('bitrix:ui.toolbar', '', [])`; единственный параметр — `TOOLBAR_ID`
  (по умолчанию `Toolbar::DEFAULT_ID`). Компонент сам регистрируется через `addBufferContent()`,
  поэтому вызывать его нужно, пока буферизация ещё идёт.

## Проверить в браузере

Серверная часть проверена, интерфейс — нет. На своём стенде пройдите:

1. фильтр рисуется, поля на местах, применение сужает выборку без перезагрузки;
2. клик по заголовку колонки меняет сортировку и она переживает перезагрузку;
3. пагинация и размер страницы; включение скрытой колонки в настройках грида;
4. меню строки и групповое действие (подтверждение, список отмеченных ID);
5. обработчик `Grid::beforeRequest` на странице, куда грид попадает через AJAX.

## Подводные камни
- **Грид, вставленный через AJAX (вкладка, слайдер), теряет пагинацию, настройки и размер
  страницы:** его запросы уходят не на тот адрес. Лечится обработчиком JS-события
  `Grid::beforeRequest`: проверить `gridId` и подменить `url`; `cancelRequest = true` отменяет
  запрос
  ([Перед выполнением запроса](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tablicy/Publicnaa_cast.html#pered-vypolneniem-zaprosa)).
  Аргументы события (код грида, 26.750.0): `BX.onCustomEvent(window, 'Grid::beforeRequest',
  [data, eventArgs])`, где `data` — объект данных грида (`BX.Grid.Data`, сам грид — `data.parent`),
  а `eventArgs` = `{gridId, url, method, data}`.
  ```js
  // на странице, куда грид подгружается через AJAX
  BX.addCustomEvent('Grid::beforeRequest', function (gridData, request) {
      if (request && request.gridId === 'VENDOR_ITEM_LIST') {
          request.url = '/items/'; // адрес, по которому грид отрисовывается сам
          // request.cancelRequest = true; — отменить запрос совсем
      }
  });
  ```
- **Тулбар.** На коробках до оформления AIR он пропадал, если что-то выведено в буферы
  `pagetitle`, `inside_pagetitle` или `in_pagetitle`; в шаблоне 26.750.0 этих зон уже нет, а
  тулбар подключается безусловно ([[concept-deferred-functions-and-page-areas]]). Вне шаблона
  Bitrix24 компонент `bitrix:ui.toolbar` вызывают явно. Страница внутри
  `bitrix:ui.sidepanel.wrapper` получит тулбар только с `'USE_UI_TOOLBAR' => 'Y'`
  ([Тулбар → Условие применения](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tulbar/Osnovnoe.html#uslovie-primenenia),
  [Полезные области шаблона](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Otlozennye_funkcii.html#poleznye-oblasti-sablona-bitrix24),
  [[concept-deferred-functions-and-page-areas]]).
- **Значения фильтра — через `Filter::getValue()`, а не `\Bitrix\Main\UI\Filter\Options::getFilter()`
  / `getFilterLogic()`:** этот путь автор книги оставляет для демонстрации и устаревших систем
  ([Фильтры пользователя → Получение фильтра](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Filtr/Filtry_polzovatela.html#polucenie-fil-tra),
  [[entity-filter-options]]).
- **`FILTER_ID` и `GRID_ID` — постоянные:** конфигурация фильтра пользователя хранится в
  `b_user_option` под идентификатором фильтра
  ([Конфигурация](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Filtr/Filtry_polzovatela.html#konfiguracia)),
  настройки грида читаются по его ID. Смена ID обнуляет пресеты пользователей.
- **Примеры книги не копировать как есть:** в описаниях полей фильтра ключи взяты в обратные кавычки
  (`` `id` ``) — в PHP это выполнение shell-команды, а не строка; в нескольких примерах пропущены
  запятые между элементами массивов.

## Откат
Удалить страницу, компонент и классы из `classes/`. Сохранённые пользователями конфигурации фильтра
останутся в `b_user_option` (категория `main.ui.filter`).

## Связанное
- [[concept-ui-subsystem]] — что даёт продукт вместо своей вёрстки
- [[entity-custom-filter]], [[entity-filter-component]], [[entity-filter-field-adapter]] — фильтр
- [[entity-grid-component]], [[entity-grid-options]], [[entity-bx-main-grid]] — грид
- [[entity-toolbar]], [[entity-ui-button]] — шапка страницы

[← Шаблоны и вёрстка](_index-templates-design.md)
