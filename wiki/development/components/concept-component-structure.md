---
title: "Компонент 2.0: структура, кэш, AJAX"
type: concept
module: components
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / коробка в Docker, main 26.750.0: у CBitrixComponent есть executeComponent, onPrepareComponentParams, startResultCache, endResultCache, abortResultCache, clearResultCache, includeComponentTemplate, SetResultCacheKeys, getTemplateName; интерфейс Engine\\Contract\\Controllerable есть; каталог /local/components на стенде отсутствует; текст — документация фреймворка (docs.1c-bitrix.ru, «Компоненты»)"
tags: [компоненты, шаблоны, кэш, ajax, local]
sources: []
related: ["[[concept-change-invasiveness-hierarchy]]", "[[concept-box-caching]]", "[[recipe-engine-controller-action]]", "[[concept-js-extensions]]", "[[pattern-local-solution-structure]]"]
aliases: []
updated: "2026-09-24"
---

# Компонент 2.0: структура, кэш, AJAX

**TL;DR:** компонент — это папка с `class.php` (логика), `.parameters.php` (входные параметры),
`.description.php` (как называется в визуальном редакторе) и `templates/` (как выглядит). Свои
компоненты живут в `/local/components/<вендор>/<компонент>/`.

**Когда писать свой:** штатный компонент не подходит по данным или логике, а «допилить» его
шаблоном нельзя. Прежде чем заводить свой — сверяемся с
[[concept-change-invasiveness-hierarchy|иерархией способов изменения]]: часто хватает шаблона и
`result_modifier.php`.

## Файлы компонента

| Файл | Что делает |
|---|---|
| `class.php` | класс-наследник `CBitrixComponent` |
| `component.php` | старый способ — логика прямо в файле, без класса |
| `.parameters.php` | описание входных параметров для редактора |
| `.description.php` | имя и раздел в списке компонентов |
| `lang/` | переводы ([[concept-localization-lang-files]]) |
| `templates/.default/template.php` | разметка |
| `templates/.default/result_modifier.php` | правка `$arResult` **до** шаблона, попадает в кэш |
| `templates/.default/component_epilog.php` | выполняется **после** шаблона и **вне кэша** |

## Класс компонента

```php
class VendorOrdersComponent extends CBitrixComponent
{
    public function onPrepareComponentParams($arParams): array
    {
        $arParams['COUNT'] = (int)($arParams['COUNT'] ?? 20);
        return $arParams;
    }

    public function executeComponent(): void
    {
        if ($this->startResultCache())
        {
            $this->arResult = ['items' => /* выборка */];
            $this->SetResultCacheKeys(['items']);      // что пробросить в epilog
            $this->includeComponentTemplate();
        }
    }
}
```

Методы на стенде (26.750.0): `executeComponent`, `onPrepareComponentParams`, `startResultCache`,
`endResultCache`, `abortResultCache`, `clearResultCache`, `includeComponentTemplate`,
`SetResultCacheKeys`, `getTemplateName`.

**`$arParams` приходит закодированным** через `htmlspecialcharsEx`; исходное значение — в ключе с
тильдой (`$arParams['~NAME']`). Это частый источник «почему в шаблоне `&quot;`».

## Кэш

Ключ кэша компонента складывается из сайта, имени компонента и шаблона, параметров и прав
пользователя. Отсюда правила:

- всё, от чего зависит результат, должно быть **в параметрах**, иначе разные данные схлопнутся в
  один кэш;
- персональные данные в кэш не кладём — они утекут другому пользователю с тем же набором прав;
- `component_epilog.php` — место для того, что нельзя кэшировать (счётчики просмотров, текущий
  пользователь), а `SetResultCacheKeys()` определяет, что из `$arResult` туда доедет;
- ошибка внутри сборки — `abortResultCache()`, иначе закэшируется пустой результат
  ([[concept-box-caching]]).

## AJAX

Два пути:

1. класс компонента реализует `\Bitrix\Main\Engine\Contract\Controllerable` — тогда у него
   появляются действия-методы с суффиксом `Action`;
2. отдельный `ajax.php` с классом-наследником `\Bitrix\Main\Engine\Controller`
   ([[recipe-engine-controller-action]]).

Второй путь чище: логика действий не смешивается с выводом, и её видно из маршрутов
([[concept-routing]]).

## Где размещать

- штатные — `/bitrix/components/bitrix/` (**не трогаем**);
- свои — `/local/components/<вендор>/<компонент>/`; подпапка вендора и есть пространство имён
  компонента. На чистом стенде `/local/components` нет — каталог заводится под первый компонент
  ([[pattern-local-solution-structure]]).

Копия штатного компонента в `/local/` — последняя ступень иерархии изменений: она перестаёт
обновляться вместе с продуктом.

## Связанные страницы
- [[concept-change-invasiveness-hierarchy]] — прежде чем писать свой компонент
- [[concept-box-caching]] — как устроен кэш под ним
- [[recipe-engine-controller-action]] — действия и AJAX
- [[concept-js-extensions]] — JS и CSS компонента

[← Компоненты](_index-components.md)
