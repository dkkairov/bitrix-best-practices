---
title: "Таймлайн CRM на клиенте: догрузить через BX API и фильтровать классом на корне"
type: pattern
module: crm
edition: box
status: verified
provenance: empirical
verified: "2026-06-11 / коробка, таймлайн карточки смарт-процесса"
tags: [crm, timeline, javascript, производительность, mutation-observer, css]
sources: []
related: ["[[recipe-crm-card-editor-js-access]]", "[[recipe-crm-hide-card-block-js]]", "[[concept-platform-reverse-engineering]]", "[[concept-change-invasiveness-hierarchy]]"]
aliases: ["timeline-force-load-via-bx-api", "timeline-filter-css-class-toggle"]
updated: "2026-09-18"
---

# Таймлайн CRM на клиенте

## Проблема и контекст

Таймлайн карточки грузит историю лениво, по странице на скролл. Любая клиентская
фильтрация или аналитика видит только первую страницу. А когда событий тысячи, наивная фильтрация
(пробежать все узлы и выставить `style.display`) дёргает layout на каждый клик и заново
классифицирует одно и то же.

## Решение

Два приёма, которые работают в паре: **догрузить через внутренний менеджер**, затем
**классифицировать каждый узел один раз и прятать классом на корне**.

## Часть 1. Догрузка: дёргать менеджер, а не имитировать скролл

```js
function getHist() {
    try {
        if (!window.BX || !BX.CrmTimelineManager
            || typeof BX.CrmTimelineManager.getDefault !== 'function') return null;
        var tm = BX.CrmTimelineManager.getDefault();
        if (!tm || typeof tm.getHistory !== 'function') return null;
        var h = tm.getHistory();
        return (h && typeof h.loadItems === 'function') ? h : null;
    } catch (e) { return null; }
}

// поля приватные → убеждаемся, что версия совместима
function histApiUsable(h) {
    return h && ('_navigation' in h) && ('_enableLoading' in h) && ('_isRequestRunning' in h);
}

var LOAD_INTERVAL = 150, DEADLINE = 90000, STUCK_LIMIT = 4;
var lastNav = null, stuckCount = 0, startedAt = Date.now(), loop;

loop = setInterval(function () {
    if (Date.now() - startedAt > DEADLINE) { clearInterval(loop); return; }
    var h = getHist();
    if (!h || !histApiUsable(h)) { clearInterval(loop); return; }
    if (!h._enableLoading)       { clearInterval(loop); return; }  // история исчерпана
    if (h._isRequestRunning) return;                               // запрос идёт — ждём

    var nav = JSON.stringify(h._navigation);
    if (nav === lastNav) {                                         // курсор не сдвинулся
        if (++stuckCount > STUCK_LIMIT) { stuckCount = 0; h.loadItems(); }  // «толчок»
        return;
    }
    stuckCount = 0; lastNav = nav;
    h.loadItems();
}, LOAD_INTERVAL);
```

`loadItems()` — родной метод подгрузки следующей страницы; `_enableLoading` гаснет, когда история
исчерпана (условие выхода); `_isRequestRunning` не даёт слать параллельные запросы; `_navigation` —
курсор пагинации.

## Часть 2. Фильтрация: один класс на корне

```js
function processNewSections() {                       // классифицируем ровно один раз
    var nodes = document.querySelectorAll('.crm-entity-stream-section-history:not([data-tlf])');
    for (var i = 0; i < nodes.length; i++) {
        nodes[i].setAttribute('data-tlcat', classify(nodes[i]));  // tasks | comments | tech | …
        nodes[i].setAttribute('data-tlf', '1');                   // больше не трогаем
    }
}

function applyFilterClasses() {                       // переключение = один toggle
    CATEGORIES.forEach(function (c) {
        root.classList.toggle('tl-hide-' + c.key, !filterState[c.key]);
    });
}
```

```css
.crm-entity-stream-section-history[data-tlcat="tech"] { display: none !important; }
.tl-hide-tasks    .crm-entity-stream-section-history[data-tlcat="tasks"]    { display: none !important; }
.tl-hide-comments .crm-entity-stream-section-history[data-tlcat="comments"] { display: none !important; }
```

Новые узлы из ленивой подгрузки ловит `MutationObserver` и классифицирует только их
(`:not([data-tlf])`). Одно изменение класса на корне дешевле тысячи `style.display` из JS.

## Когда применять
- Нужна клиентская аналитика или фильтрация по всей истории элемента.
- Событий много (сотни и тысячи), а решение должно оставаться «мягким».

## Когда НЕ применять
- Нужна серверная выборка или отчёт: приватный JS-API для этого не годится, берите данные на
  сервере.
- Портал обновляется часто, а сопровождать приватный API некому — см. риск ниже.

## Последствия
- **Плюсы:** работает на штатной карточке, без правки `/bitrix/`, без headless-браузера.
- **Минусы и риски:**
  - **Приватный API.** `_navigation`, `_enableLoading`, `_isRequestRunning`, поведение
    `loadItems()` — внутренности, не контракт: при обновлении CRM могут переименоваться. Поэтому
    обязательны `typeof`-проверки и `histApiUsable()`: структура не та → сворачиваемся, а не
    «толкаем» неизвестный объект. Версию, на которой проверено, фиксировать.
  - **Без дедлайна `setInterval` молотит минутами** при сетевых сбоях или залипшем курсоре. Нужен
    глобальный стоп по времени и/или потолок итераций.
  - `getDefault()` возвращает менеджер «текущего» таймлайна — на странице с несколькими стримами
    убедиться, что это нужный.

## Подводные камни фильтрации
- **Заголовки-даты, под которыми не осталось видимых событий,** CSS сам не скроет (нет
  родительского селектора по детям). Нужен отдельный проход справа налево по плоскому списку,
  лучше в `requestAnimationFrame`, а не на каждый клик.
- **Классификация по тексту заголовка хрупка к локализации.** Опираться на CSS-классы иконок,
  а не на видимый текст.
- **`MutationObserver` должен отсеивать собственные мутации**, иначе зациклится на своей же панели.
- **`!important` в правилах скрытия** нужен, чтобы перебить инлайн-стили и темы CRM.

## Альтернативы

| Подход | Почему отказались |
|---|---|
| Эмуляция скролла контейнера | хрупко: скролл-хост и тайминги плавают, легко недогрузить |
| Клон событий в невидимый контейнер + `IntersectionObserver` | не триггерит ленивую подгрузку родного стрима — клон от него оторван |
| REST `crm.timeline.*` своим кодом | дублирует логику CRM и теряет рендер карточек; оправдано только для серверной аналитики |
| `style.display` на каждом узле | reflow и повторная классификация на каждый клик |
| CSS `:has()` для пустых дат | поддержка неровная, логика «до следующей метки» через `:has()` не выражается |

## Открытые вопросы
- Есть ли публичное событие завершения загрузки таймлайна вместо опроса `_navigation`.
- При какой глубине истории дедлайна в 90 секунд перестаёт хватать.

## Связанное
- [[recipe-crm-card-editor-js-access]] — соседняя работа с карточкой из JS
- [[concept-platform-reverse-engineering]] — как нашли `CrmTimelineManager`

[← CRM](_index-crm.md)
