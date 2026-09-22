---
title: "Отложенные функции и зоны страницы"
type: concept
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-22 / коробка в Docker, main 26.750.0, шаблон bitrix24 (AIR): прогон отложенных функций, зон и кэша тестового компонента в консоли + код CMain и шаблона; текст — «Книга разработчика Bitrix24» (снимок 2026-09-21)"
tags: [отложенные-функции, буферизация, зоны-страницы, шаблон, тулбар, компоненты, кэш]
sources: ["[[source-devbook-core-d7]]"]
related: ["[[entity-toolbar]]", "[[entity-site-template]]", "[[concept-request-lifecycle]]", "[[concept-ui-subsystem]]", "[[entity-filter-component]]", "[[recipe-cli-script-bootstrap]]"]
aliases: []
updated: "2026-09-22"
---

# Отложенные функции и зоны страницы

**TL;DR:** компонент в теле страницы задаёт заголовок, стили, кнопку панели или целый HTML-блок, а
результат появляется в другом месте страницы — в шапке, справа, над или под заголовком. Механизм
стоит на буферизации вывода: пустые слоты заполняются в служебной части эпилога. В шаблоне
Bitrix24 для своего вывода есть готовые зоны; три устаревшие зоны у заголовка не трогаем — непустые,
они выключают тулбар.

Источник — [«Отложенные функции»](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Otlozennye_funkcii.html)
«Книги разработчика». Только коробка: это PHP-код шаблонов и компонентов.

> **Проверено на стенде** (коробка в Docker, `main` 26.750.0, 2026-09-22): механизм подтвердился
> целиком. Но **список зон шаблона `bitrix24` устарел**: в текущем шаблоне (AIR) из зон книги
> выводятся только `above_pagetitle`, `below_pagetitle` и три `sidebar*`, зато появилась
> `page_menu`, а тулбар подключается безусловно — подробности в разделе «Зоны шаблона».

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

Прогон (стенд) подтвердил и детали:

- в слот встаёт **последнее** значение — `SetTitle()` после `ShowTitle()` всё равно побеждает;
- функции выполняются **в порядке появления** в коде, параметры передаются как есть;
- `echo` внутри отложенной функции печатается **в самом начале ответа**, до шаблона: результат
  отдаём только через `return`;
- **без буферизации механизма нет:** если `BX_BUFFER_USED` не `true`, `AddBufferContent()`
  выполняет колбэк сразу и печатает результат на месте (ядро `CMain`);
- **обрыв страницы не теряет отложенное:** при `die()` до эпилога буферы сбрасываются на
  завершении процесса, слоты заполняются. Теряется не вывод, а то, что делает эпилог (события,
  агенты);
- в консольном скрипте с прологом ([[recipe-cli-script-bootstrap]]) буферизация тоже работает —
  отложенные функции удобно проверять без браузера.

## Два вида (терминология книги)

Названия книга вводит сама
([Виды](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Otlozennye_funkcii.html#vidy));
в основе обоих — `AddBufferContent`.

| | Общие (BufferContent) | Компонентные (ViewTarget) |
|---|---|---|
| Что откладывается | вызов функции; её результат встаёт в слот | готовый HTML-фрагмент для именованной зоны |
| Как задать | парные `Set*` / `Add*` или свой `AddBufferContent` | в шаблоне компонента `$this->SetViewTarget()` … `$this->EndViewTarget()`; вне компонента — `$APPLICATION->AddViewContent()` |
| Где выводится | в месте вызова `Show*` / `AddBufferContent` | в зоне шаблона; своя зона — `$APPLICATION->ShowViewContent()` |
| Порядок | в порядке вызовов | по параметру `$pos` (см. ниже) |
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
странице, **не внутри кэшируемой области**. Зона, в которую никто не писал, выводит пустую строку —
не ошибку.

**Порядок внутри зоны задаётся явно** (книга об этом не пишет, в ядре есть):
`AddViewContent($view, $content, $pos = 500)` и `SetViewTarget($target, $pos = 500)`. Фрагменты
сортируются по возрастанию `$pos`, при равенстве сохраняется порядок добавления; на стенде
`100` → по умолчанию `500` → `900`. Отсюда и `RENDER_FILTER_INTO_VIEW_SORT` у фильтра: это тот же
`$pos`.

**Кэш.** Фрагмент хранится в локальном буфере компонента, а тот — в кэше компонента: что попало в
зону при создании кэша, показывается до его пересоздания. Содержимое зоны **не динамическое**.
Персональное и часто меняющееся (счётчики, данные текущего пользователя) через `SetViewTarget`
кэшируемого компонента не выводим (вывод команды).

Фильтр `bitrix:main.ui.filter` умеет сам выводиться в зону: `RENDER_FILTER_INTO_VIEW` — код зоны,
`RENDER_FILTER_INTO_VIEW_SORT` — приоритет, по умолчанию 500
([параметры фильтра](https://bx24devbook.website.yandexcloud.net/Razrabotka/UI/Filtr/Obzor.html#parametry-komponenta),
[[entity-filter-component]]).

## Зоны шаблона `bitrix24`

Книга описывает прежний шаблон. На стенде (коробка `main` 26.750.0, шаблон `bitrix24` в оформлении
AIR) зоны выводятся так — по коду `templates/bitrix24/header.php` и `footer.php`:

| Зона | Где на странице | Для своего вывода |
|---|---|---|
| `above_pagetitle` | в меню шапки портала, перед верхним меню | ✅ |
| `page_menu` | строка меню страницы, слева от тулбара (в книге не названа) | ✅ |
| `below_pagetitle` | блок действий справа от тулбара | ✅ |
| `sidebar`, `sidebar_tools_1`, `sidebar_tools_2` | вертикальный блок справа (выводятся в подвале) | ✅ |
| `inline-scripts` | служебная, для встроенных скриптов шапки | ❌ служебная |
| `im`, `im-fullscreen`, `topblock` | в текущем шаблоне **не выводятся** | ❌ нет такой зоны |
| `pagetitle` | в шаблоне не выводится; остался в компоненте `intranet.pageslider.wrapper` (слайдер) | ❌ |
| `inside_pagetitle`, `in_pagetitle` | в шаблоне и модулях `ui`/`intranet` не встречаются | ❌ |

**Тулбар `bitrix:ui.toolbar` подключён в шапке безусловно** — между `page_menu` и `below_pagetitle`
(`header.php`). Прежнего условия «непустая зона `pagetitle`/`inside_pagetitle`/`in_pagetitle`
выключает тулбар» в этой версии в коде нет: выключать нечем, самих зон не осталось. Нужно что-то
рядом с заголовком — через API тулбара (`addBeforeTitleHtml`, `addAfterTitleHtml`,
`addRightCustomHtml`, `Toolbar::addButton`), см. [[entity-toolbar]].

> **Уточнено 2026-09-22 по стенду.** Раньше здесь была таблица из книги (`im`, `topblock`,
> `pagetitle`, `inside_pagetitle`, `in_pagetitle` и условие показа тулбара) — она описывает шаблон до
> перехода на AIR. Если работаете на коробке с прежним оформлением, **проверяйте зоны по своему
> `header.php`**: зона живёт в шаблоне, а не в ядре, и от версии к версии меняется.
> Универсальный способ проверки — `grep -rn "ShowViewContent" /путь/к/templates/<шаблон>/`.

## Ограничения

- **Общие — не в кэшируемых файлах шаблона компонента.** `template.php` и `result_modifier.php`
  кэшируются, общие отложенные функции там запрещены; компонентные — можно
  ([особенности](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Otlozennye_funkcii.html#osobennosti-raboty)).
  Механика проверена на стенде: при отдаче из кэша `template.php` **не выполняется**, поэтому
  `SetTitle()` оттуда теряется — заголовок остаётся тем, что задала страница. А вот фрагмент,
  отданный через `SetViewTarget()`, **переживает кэш**: он лежит в кэше компонента и попадает в
  зону и на закэшированном хите.
- **Где вызывать общие отложенные функции у кэшируемого компонента:** в `component.php` —
  он выполняется на каждом хите, и `SetTitle()` оттуда работает и при отдаче из кэша (стенд).
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
  статическим, с сигнатурой `CMain::AddBufferContent(...)`. По коду ядра (26.750.0) метод
  **нестатический**: `public function AddBufferContent($callback)`, дополнительные параметры
  забираются через `func_get_args()`. Правильный вызов — через `$APPLICATION`.
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

- Останется ли набор зон AIR-шаблона стабильным: он живёт в `header.php`/`footer.php` и меняется
  с обновлениями — перед использованием зоны сверяйте её по своему шаблону.

Закрыты прогоном на стенде 2026-09-22 (`main` 26.750.0): порядок в зоне задаётся третьим
параметром `$pos` (по умолчанию 500, по возрастанию); общие отложенные функции кэшируемый
компонент вызывает из `component.php`; при обрыве страницы через `die()` отложенное всё равно
выполняется на сбросе буферов.

## Связанное
- [[concept-request-lifecycle]] — шаги 13 и 21: старт буферизации и служебный эпилог
- [[recipe-cli-script-bootstrap]] — как прогнать отложенные функции из консоли
- [[entity-toolbar]] — API шапки вместо устаревших зон
- [[concept-ui-subsystem]] — тулбар, фильтр, грид
- [[entity-site-template]] — почему системный шаблон не копируют
- [[entity-filter-component]] — вывод фильтра в зону

[← Ядро D7](_index-core-d7.md)
