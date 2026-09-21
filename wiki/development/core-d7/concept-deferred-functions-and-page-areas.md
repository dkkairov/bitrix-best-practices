---
title: "Отложенные функции и зоны страницы"
type: concept
module: core-d7
edition: box
status: draft
provenance: mixed
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Технологии / Отложенные функции; UI / Тулбар, Фильтр; Общие сведения / Шаблон; без проверки на стенде"
tags: [отложенные-функции, буферизация, зоны-страницы, шаблон, тулбар, компоненты, кэш]
sources: ["[[source-devbook-core-d7]]"]
related: ["[[entity-toolbar]]", "[[entity-site-template]]", "[[concept-request-lifecycle]]", "[[concept-ui-subsystem]]", "[[entity-filter-component]]"]
aliases: []
updated: "2026-09-21"
---

# Отложенные функции и зоны страницы

**TL;DR:** компонент в теле страницы задаёт заголовок, стили, кнопку панели или целый HTML-блок, а
результат появляется в другом месте страницы — в шапке, справа, над или под заголовком. Механизм
стоит на буферизации вывода: пустые слоты заполняются в служебной части эпилога. В шаблоне
Bitrix24 для своего вывода есть готовые зоны; три устаревшие зоны у заголовка не трогаем — непустые,
они выключают тулбар.

Источник — [«Отложенные функции»](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Otlozennye_funkcii.html)
«Книги разработчика». Только коробка: это PHP-код шаблонов и компонентов.

## Зачем

Компонент выводится в теле страницы, а менять ему часто нужно то, что шаблон уже вывел выше:
заголовок, навигационную цепочку, стили, кнопки панели управления. Отложенная функция вызывается
«здесь», а её результат встаёт «там»
([Введение](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Otlozennye_funkcii.html#vvedenie)).
Шаблон Bitrix24 пользуется этим активно: панель виджетов и панель мессенджера на главной нельзя
однозначно отнести к шапке, телу или подвалу
([Шаблон → Визуализация](https://bx24devbook.website.yandexcloud.net/Obsie_svedenia/Sablon.html#vizualizacia),
[[entity-site-template]]).

## Как работает

Весь вывод скрипта буферизуется (старт — шаг 13 пайплайна, [[concept-request-lifecycle]]). На
каждом отложенном вызове
([алгоритм](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Otlozennye_funkcii.html#algoritm-raboty-tehnologii)):

1. накопленный буфер сохраняется очередным куском в стек **A**, за ним — пустой слот;
2. функция с параметрами записывается в стек **B** — в порядке появления в коде;
3. буфер очищается, буферизация продолжается.

В служебной части эпилога (шаг 21) функции из B выполняются по очереди, их результаты встают в свои
слоты, куски A склеиваются и уходят в браузер.

**Главное следствие:** функция выполняется в самом конце и видит **последнее** значение, заданное
где угодно на странице. `ShowTitle()` в шапке шаблона выведет заголовок, который компонент задал
через `SetTitle()` уже после вывода шапки.

## Два вида (терминология книги)

Названия книга вводит сама
([Виды](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Otlozennye_funkcii.html#vidy));
в основе обоих — `AddBufferContent`.

| | Общие (BufferContent) | Компонентные (ViewTarget) |
|---|---|---|
| Что откладывается | вызов функции; её результат встаёт в слот | готовый HTML-фрагмент для именованной зоны |
| Как задать | парные `Set*` / `Add*` или свой `AddBufferContent` | в шаблоне компонента `$this->SetViewTarget()` … `$this->EndViewTarget()`; вне компонента — `$APPLICATION->AddViewContent()` |
| Где выводится | в месте вызова `Show*` / `AddBufferContent` | в зоне шаблона; своя зона — `$APPLICATION->ShowViewContent()` |
| В кэшируемых `template.php`, `result_modifier.php` | **нельзя** | можно, но фрагмент кэшируется вместе с компонентом |

### Общие: пары `Show*` / `Set*`

Самые ходовые — методы `CMain`, экземпляр которого лежит в `$APPLICATION`
([примеры](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Otlozennye_funkcii.html#primery-obsih-otlozennyh-funkcij)):

| Выводит | Откладывает | Чем задаётся значение |
|---|---|---|
| `ShowTitle()` | `GetTitle()` | `SetTitle()` |
| `ShowPanel()` | `GetPanel()` | `AddPanelButton()` |
| `ShowCSS()` | `GetCSS()` | `SetTemplateCSS()`, `SetAdditionalCSS()` |
| `ShowProperty()` | `GetProperty()` | `SetPageProperty()`, `SetDirProperty()` |

Своя общая функция — `$APPLICATION->AddBufferContent($callback, ...$params)`: слот появляется в месте
вызова, `$callback` выполнится в эпилоге
([своя общая функция](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Otlozennye_funkcii.html#razrabotka-svoej-obsej-otlozennoj-funkcii)).
Пример — итог над списком, который считает компонент ниже по странице:

```php
// local/php_interface/classes/Vendor/Project/Ui/ListSummary.php
namespace Vendor\Project\Ui;

final class ListSummary
{
    private static string $text = '';

    public static function set(string $text): void
    {
        self::$text = $text;
    }

    // Выполняется в служебном эпилоге. Результат — только через return:
    // echo отсюда окажется до начала шаблона
    public static function render(): string
    {
        return self::$text === ''
            ? ''
            : '<div class="vendor-list-summary">' . htmlspecialcharsbx(self::$text) . '</div>';
    }
}
```

```php
<?php // тело своей страницы
$APPLICATION->AddBufferContent([\Vendor\Project\Ui\ListSummary::class, 'render']); // слот — здесь
$APPLICATION->IncludeComponent('vendor:orders.list', '', []);
// компонент вызывает ListSummary::set('Заказов: 128') вне кэшируемой части — см. «Ограничения»
```

### Компонентные: `ViewTarget` и зоны

В шаблоне компонента (`template.php` или любой подключаемый из него файл) всё, что выведено между
`SetViewTarget()` и `EndViewTarget()`, уходит в указанную зону
([своя компонентная функция](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Otlozennye_funkcii.html#razrabotka-svoej-komponentnoj-otlozennoj-funkcii)):

```php
<?php // local/components/vendor/orders.list/templates/.default/template.php
$this->SetViewTarget('sidebar');
?>
<div class="vendor-orders-help">Как читать список заказов</div>
<?php
$this->EndViewTarget();
```

Вне компонента — из любого кода, который выполняется на странице:

```php
$APPLICATION->AddViewContent('below_pagetitle', '<div class="vendor-notice">Идёт сверка остатков</div>');
```

Своя зона — `$APPLICATION->ShowViewContent('vendor_orders_footer');` в своём шаблоне или на своей
странице, **не внутри кэшируемой области**.

**Кэш.** Фрагмент хранится в локальном буфере компонента, а тот — в кэше компонента: что попало в
зону при создании кэша, показывается до его пересоздания. Содержимое зоны **не динамическое**.
Персональное и часто меняющееся (счётчики, данные текущего пользователя) через `SetViewTarget`
кэшируемого компонента не выводим (вывод команды).

Фильтр `bitrix:main.ui.filter` умеет сам выводиться в зону: `RENDER_FILTER_INTO_VIEW` — код зоны,
`RENDER_FILTER_INTO_VIEW_SORT` — приоритет, по умолчанию 500
([параметры фильтра](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Filtr/Obzor.html#parametry-komponenta),
[[entity-filter-component]]).

## Зоны шаблона `bitrix24`

По [полезным областям шаблона](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Otlozennye_funkcii.html#poleznye-oblasti-sablona-bitrix24)
и [условию показа тулбара](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tulbar/Osnovnoe.html#uslovie-primenenia):

| Зона | Где на странице | Для своего вывода |
|---|---|---|
| `im`, `im-fullscreen` | в начале контентной области | ❌ книга не рекомендует |
| `sidebar`, `sidebar_tools_1`, `sidebar_tools_2` | вертикальный блок справа | ✅ |
| `topblock` | начало содержимого контентной области | ✅ |
| `above_pagetitle` | перед строкой заголовка | ✅ |
| `below_pagetitle` | после строки заголовка, до контентной области | ✅ |
| `pagetitle` | справа от заголовка, до кнопок | ❌ устаревшая: непустая выключает тулбар |
| `inside_pagetitle` | кнопки справа от заголовка | ❌ то же |
| `in_pagetitle` | названа только в условиях тулбара, без описания | ❌ то же |

**Тулбар `bitrix:ui.toolbar` стоит между `above_pagetitle` и `below_pagetitle`**
([Тулбар](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Tulbar/Osnovnoe.html#tulbar)):
сами эти зоны он не занимает, они свободны для своего вывода над ним и под ним. По умолчанию тулбар
показывается, если страница в шаблоне Bitrix24, буферы `pagetitle`, `inside_pagetitle`,
`in_pagetitle` пусты, а буферизация ещё идёт. Вывод хотя бы в одну из этих трёх зон переключает шапку
на устаревший вид; при работающем тулбаре книга считает `pagetitle` и `inside_pagetitle`
неиспользуемыми. Нужно что-то рядом с заголовком — через API тулбара (`addBeforeTitleHtml`,
`addAfterTitleHtml`, `addRightCustomHtml`, `Toolbar::addButton`), см. [[entity-toolbar]] (вывод
команды).

## Ограничения

- **Общие — не в кэшируемых файлах шаблона компонента.** `template.php` и `result_modifier.php`
  кэшируются, общие отложенные функции там запрещены; компонентные — можно
  ([особенности](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Otlozennye_funkcii.html#osobennosti-raboty)).
  Вывод команды, проверить на стенде: при выдаче из кэша эти файлы не выполняются, поэтому и
  `SetTitle()` оттуда теряется.
- **Результат нельзя обработать.** Возвращённое отложенной функцией сразу встаёт в слот — в место
  вызова `AddBufferContent`, получить его в переменную нельзя. Всё, что функция выводит через `echo`,
  окажется до начала шаблона, — отдаём результат через `return`.
- **CSS и JS — вручную.** Буфер собирает только реально выведенный контент: внутри отложенной
  функции можно подключить компонент, но его дополнительные CSS и JS подключаем сами.
- **Компонентные не динамические** — см. «Кэш» выше.
- **Свою зону не ставим внутри кэшируемой области.**
- **Порядок:** отложенные вызовы выполняются в том порядке, в каком встретились в коде.

## Неточности книги

- `AddBufferContent` в разделе «Виды» назван нестатическим методом, а в разделе про свою функцию —
  статическим, с сигнатурой `CMain::AddBufferContent(...)`; пример при этом вызывает его через
  `$APPLICATION`. Здесь — как в примере, через экземпляр; сверить со
  [справкой](https://dev.1c-bitrix.ru/api_help/main/reference/cmain/addbuffercontent.php).
- У `ShowPanel`, `ShowCSS` и `ShowProperty` первая фраза описания повторяет `ShowTitle` (вывод
  названия страницы) — ошибка копирования. Назначение в таблице выше восстановлено по парным
  методам, которые книга приводит для каждой функции.

## Почему важно при внедрении

- Зоны — штатные точки встройки в системный шаблон: свой блок справа или под заголовком без копии
  шаблона (копия — нижняя ступень [[concept-change-invasiveness-hierarchy|иерархии изменений]]).
- «Пропал тулбар» на своей странице — первым делом проверить, не пишет ли кто-то в `pagetitle`,
  `inside_pagetitle` или `in_pagetitle`.
- «Пропал заголовок после включения кэша» — проверить, не вызывается ли `SetTitle()` из
  `template.php` или `result_modifier.php` (вывод команды).

## Открытые вопросы

- Как упорядочиваются несколько `AddViewContent` в одной зоне: у фильтра приоритет есть, а параметр
  порядка у `AddViewContent` книга не описывает — сверить со
  [справкой](https://dev.1c-bitrix.ru/api_help/main/reference/cmain/addviewcontent.php).
- Откуда кэшируемому компоненту безопасно вызывать общие отложенные функции — книга только
  запрещает `template.php` и `result_modifier.php`.
- Что происходит с отложенными функциями, если выполнение страницы прервано до служебного эпилога.

## Связанное
- [[concept-request-lifecycle]] — шаги 13 и 21: старт буферизации и служебный эпилог
- [[entity-toolbar]] — API шапки вместо устаревших зон
- [[concept-ui-subsystem]] — тулбар, фильтр, грид
- [[entity-site-template]] — почему системный шаблон не копируют
- [[entity-filter-component]] — вывод фильтра в зону

[← Ядро D7](_index-core-d7.md)
