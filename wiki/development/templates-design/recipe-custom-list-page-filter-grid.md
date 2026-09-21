---
title: "Своя страница-список: фильтр, грид, тулбар"
type: recipe
module: templates-design
edition: box
status: draft
provenance: mixed
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Разработка → UI — Тулбар, Кнопки, Фильтр (Обзор, Фильтры пользователя, Публичная часть, Свой фильтр), Таблицы (Обзор, Персональные настройки, Публичная часть, Панель действий); Технологии → Отложенные функции; без проверки на стенде"
tags: [ui, фильтр, грид, тулбар, список, компонент, панель-действий, производительность]
sources: ["[[source-devbook-ui]]"]
related: ["[[entity-custom-filter]]", "[[entity-filter-component]]", "[[entity-grid-component]]", "[[entity-grid-options]]", "[[entity-toolbar]]", "[[concept-ui-subsystem]]"]
aliases: []
updated: "2026-09-21"
---

# Своя страница-список: фильтр, грид, тулбар

> **Черновик.** Связка целиком на стенде не прогонялась. Код ниже написан нами на именах классов и
> методов из книги; её собственные примеры содержат ошибки, а в нескольких местах книга противоречит
> сама себе. Всё непроверенное собрано в разделе «Проверить на стенде».

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
- **Даты.** В примере книги провайдер переопределяет `prepareListFilterParam()` и сам превращает
  пару `<поле>_from` / `<поле>_to` поля-даты в условия `>=` / `<=`. Ниже — та же идея нашим кодом;
  нужен ли метод в вашей версии, проверить на стенде.
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

> **Книга противоречит сама себе: поля передавать в `FILTER` или в `FIELDS`?** Таблица параметров
> компонента описывает оба ключа: `FIELDS` — как набор полей, `FILTER` — как набор их конфигураций
> ([Обзор → Параметры](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Filtr/Obzor.html#parametry-komponenta)).
> Вступление главы «Свой фильтр» называет обязательными `FIELDS` и `FILTER_ID`, а пример той же главы
> передаёт `getFieldArrays()` в `FILTER`
> ([Свой фильтр → Примеры](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Filtr/Svoj_filtr.html#primery)).
> В коде ниже — `FILTER`, как в этом примере. **Проверить на стенде / по офф. документации
> компонента**, какой ключ он читает. Если поля не появились в фильтре — смотреть сюда первым делом.

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
            'FILTER'         => $filter->getFieldArrays(), // или FIELDS — см. выше
            'ENABLE_LABEL'   => true,
            'DISABLE_SEARCH' => false,
        ]);
        Toolbar::addButton(['link' => '/items/new/', 'text' => 'Добавить', 'icon' => Icon::ADD]);

        // … шаг 3
```

## Шаг 3. Выборка: фильтр, сортировка, страница, видимые колонки

- **Фильтр:** `$filter->getValue()` — массив, готовый для `filter` в `DataManager::getList()`; без
  аргументов значения берутся из запроса, но можно передать массив явно
  ([Получение значения](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Filtr/Svoj_filtr.html#polucenie-znacenia-fil-tra)).
- **Сортировка:** `Grid\Options::GetSorting()` учитывает параметры вызова, запрос, сессию и
  настройки пользователя и возвращает структуру как у аргумента — `sort` и `vars`. `vars` — имена
  параметров запроса с полем и направлением сортировки: если гридов на странице несколько (или
  `by` / `order` заняты под другое), каждому нужны свои, иначе грид может получить сортировку по
  несуществующему полю, вплоть до фатальной ошибки
  ([Просчитать сортировку](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tablicy/Personalnye_nastrojki.html#proscitat-sortirovku)).
  Поэтому в `order` дополнительно пропускаем только поля из белого списка (вывод команды).
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
- **Не из книги:** `PageNavigation::getOffset()`, `getLimit()`, `setRecordCount()` и ключ
  `count_total` — пример книги обрывается на `initFromUri()`. Сверить с офф. документацией D7.

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
  В примере книги в `columns` лежит готовая HTML-ссылка, поэтому свои данные экранируем
  `htmlspecialcharsbx`, иначе XSS через название записи (вывод команды).
- **Групповые действия — `ACTION_PANEL`** → `GROUPS` → `ITEMS`. Кнопка с `ONCHANGE` и действием
  `Actions::CALLBACK` вызывает глобальную JS-функцию, по желанию — после подтверждения. Скрипты из
  `DATA` выполняются цепочкой: упавший прерывает остальные
  ([Функция-обработчик](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tablicy/Panel_dejstvij.html#funkcia-obrabotcik)).
  Типовые кнопки даёт `Grid\Panel\Snippet`
  ([Сниппеты](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tablicy/Panel_dejstvij.html#snippety)),
  но как обработать на сервере их запросы (например, `delete`), книга не показывает.
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

## Проверить на стенде
1. **Ключ полей фильтра — `FILTER` или `FIELDS`** (противоречие книги, шаг 2).
2. **Что возвращает `getValue()`:** как обрабатываются даты (нужен ли свой
   `prepareListFilterParam()`, что в него приходит, не остаются ли сырые `_from` / `_to` /
   `_datesel`) и строка поиска `FIND` — книга этого не показывает. Если поиск не сужает выборку,
   обработать его самим, например в наследнике `Filter`.
3. **Ключи `createField()`:** смысл `partial` книга не объясняет; как пометить поле «показывать по
   умолчанию», не показывает (метод `getDefaultFieldIDs()` есть).
4. **Постраничная выборка:** `getOffset()` / `getLimit()` / `setRecordCount()` и `count_total` — не
   из книги. В примере книги `PageNavigation::setPageSizes()` получает тот же массив `NAME` / `VALUE`,
   что и `PAGE_SIZES` грида; формат аргумента не описан, у нас вызова нет.
5. **Строки грида:** таблица книги описывает `columns` для вывода и `data` для исходных значений, а
   полный пример передаёт только `data` с ключами вида `~AMOUNT`. Мы передаём оба ключа.
6. **Панель действий:** `VALUE` назван обязательным полем элемента, но во всех примерах кнопок его
   нет — у нас, как в примерах, без него.
7. **`AJAX_ID`:** в таблице параметров — `CAjax::GetComponentID('bitrix:main.ui.grid', '', '')`, в
   примере — с шаблоном `.default`. У нас — как в примере.
8. **Свои имена в `vars`:** книга советует разводить гриды, но не показывает, как грид узнаёт
   нестандартные имена параметров. Прежде чем менять `by` / `order`, проверить сортировку кликом
   по заголовку.
9. **Обработчик `Grid::beforeRequest`:** книга называет два объекта — грид и настройки запроса, — но
   кода обработчика не приводит; порядок аргументов в примере ниже — наше предположение.
10. **Тулбар вне шаблона Bitrix24:** параметров явного вызова компонента книга не приводит.

## Подводные камни
- **Грид, вставленный через AJAX (вкладка, слайдер), теряет пагинацию, настройки и размер
  страницы:** его запросы уходят не на тот адрес. Лечится обработчиком JS-события
  `Grid::beforeRequest`: проверить `gridId` и подменить `url`; `cancelRequest = true` отменяет
  запрос
  ([Перед выполнением запроса](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tablicy/Publicnaa_cast.html#pered-vypolneniem-zaprosa)).
  ```js
  // на странице, куда грид подгружается через AJAX; порядок аргументов — проверить на стенде
  BX.addCustomEvent('Grid::beforeRequest', function (grid, request) {
      if (request && request.gridId === 'VENDOR_ITEM_LIST') {
          request.url = '/items/'; // адрес, по которому грид отрисовывается сам
      }
  });
  ```
- **Тулбар не появится**, если что-то выведено в буферы `pagetitle`, `inside_pagetitle` или
  `in_pagetitle`. Вне шаблона Bitrix24 компонент тулбара вызывают явно (в книге он назван
  `bitrix:ui.toolbar`). Страница внутри `bitrix:ui.sidepanel.wrapper` получит тулбар только с
  `'USE_UI_TOOLBAR' => 'Y'`
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
