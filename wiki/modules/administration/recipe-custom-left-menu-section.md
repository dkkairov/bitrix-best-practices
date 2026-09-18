---
title: "Свой раздел в левом меню портала через Intranet\\CustomSection"
type: recipe
module: administration
edition: box
status: verified
provenance: empirical
verified: "2026-07-16 / коробка: main 26.600.0, дизайн Air"
tags: [интранет, левое-меню, custom-section, счётчики, роутинг, модуль]
sources: []
related: ["[[recipe-module-structure-and-install]]", "[[concept-change-invasiveness-hierarchy]]", "[[recipe-crm-hide-card-block-js]]"]
aliases: ["bitrix24-custom-section-left-menu"]
updated: "2026-09-18"
---

# Свой раздел в левом меню портала

**Результат:** модуль добавляет в левое меню свой раздел со страницами и счётчиками — без единой
правки файлов продукта.

## Предусловия
- Коробка, дизайн Air (на старом дизайне не проверялось).
- Свой модуль — [[recipe-module-structure-and-install]].

## Проблема

Править `/.top.menu_ext.php` нельзя: файл поставляется продуктом
([[concept-change-invasiveness-hierarchy|список «никогда»]]). Механика `.left.menu_ext.php` в
коробке Bitrix24 не работает: левое меню — это меню типа `top` корня сайта, а не классическое
left-меню.

## Решение

Штатный API кастомных разделов интранета `Bitrix\Intranet\CustomSection` — тот самый, которым
пользуются CRM и приложения маркетплейса. Три части.

### 1. Провайдер — класс в модуле

```php
class SectionProvider extends \Bitrix\Intranet\CustomSection\Provider
{
    public function isAvailable(string $pageSettings, int $userId): bool { /* … */ }

    public function getCounterId(string $pageSettings): ?string   // код CUserCounter
    {
        return 'my_counter_' . $pageSettings;
    }

    public function resolveComponent(string $pageSettings, Uri $url): ?Component
    {
        return (new Component())
            ->setComponentName('vendor:my.component')
            ->setComponentTemplate('')
            ->setComponentParams(['SLUG' => $pageSettings]);
    }
}
```

### 2. Регистрация провайдера — `.settings.php` в корне модуля

```php
return [
    'intranet.customSection' => [
        'value'    => ['provider' => \Vendor\Module\SectionProvider::class],
        'readonly' => true,
    ],
];
```

### 3. Раздел и страницы — записи в штатных ORM-таблицах

Создавать в `DoInstall` либо синхронизировать с настройками модуля:

```php
$sectionId = CustomSectionTable::add([
    'CODE' => 'my_section', 'TITLE' => 'Мой раздел', 'MODULE_ID' => 'vendor.module',
])->getId();

CustomSectionPageTable::add([
    'CUSTOM_SECTION_ID' => $sectionId, 'MODULE_ID' => 'vendor.module',
    'CODE' => 'page1', 'TITLE' => 'Страница', 'SORT' => 100,
    'SETTINGS' => 'произвольная строка — придёт в провайдер как $pageSettings',
]);
```

## Почему работает

Ядро само вызывает `intranet.customSection.manager->appendSuperLeftMenuSections()` в конце штатного
`.top.menu_ext.php`: раздел (уровень 1) и страницы (уровень 2) попадают в меню без правок файлов.
Адреса страниц `/page/<код-раздела>/<код-страницы>/` уже роутятся правилом `#^/page/#` на компонент
`bitrix:intranet.customsection`, который спрашивает у провайдера `resolveComponent()`. Счётчики
страниц берутся из `getCounterId()` (значения кладём в `CUserCounter`, сайт `'**'`), агрегат на
разделе ядро строит само.

## Проверка результата
- Раздел и страницы видны в левом меню, адреса открываются напрямую.
- Счётчик отображается и обновляется.
- После удаления модуля раздел исчезает.

## Подводные камни
- **Дефис в `CODE` страницы недопустим:** адрес матчится регулярным выражением `[\w]+` — только
  `a-z0-9_`, иначе страница отдаёт 404.
- После изменения набора страниц **сбрасывать кэш меню**:
  `\Bitrix\Intranet\Composite\CacheProvider::deleteUserCache(false)` — меню кэшируется по
  пользователю на 7 суток.
- `isAvailable()` вызывается при **каждой** сборке меню для **каждой** страницы: должен быть
  дешёвым (кэшировать) и **никогда не бросать исключений** — иначе ляжет всё меню. Здесь уместен
  [[pattern-module-self-disabling-guard|сторож]].
- `Provider\Registry` помечен как внутренний: сам механизм конфигурации может измениться при
  обновлениях — перепроверять после мажоров.
- **Карточки CRM в своём разделе открываются по другому адресу** —
  `/page/<раздел>/<страница>/type/<id>/details/<элемент>/`, а не `/crm/type/…`. Любой код, который
  определяет «это страница карточки» по подстроке `/crm/type/`, здесь не сработает: см.
  [[recipe-crm-hide-card-block-js]] и [[recipe-crm-card-editor-js-access]].

## Альтернативы

| Подход | Почему отказались |
|---|---|
| Правка `/.top.menu_ext.php` | файл поставляется продуктом |
| Свой `/.superleft.menu_ext.php` | легально, но недокументировано и забирает на себя **всё** меню |
| REST `intranet.customSection.*` | тот же механизм, но для тиражных приложений и облака |

## Связанное
- [[recipe-module-structure-and-install]] — куда класть `.settings.php` и что делать в `DoInstall`

[← Администрирование портала](_index-administration.md)
