---
title: "Скрыть блок в карточке смарт-процесса: MutationObserver + entityTypeId из URL"
type: recipe
module: crm
edition: box
status: verified
provenance: empirical
verified: "2026-06-02 / коробка, карточка смарт-процесса в слайдере CRM"
tags: [crm, smart-process, javascript, mutation-observer, слайдер, ui]
sources: []
related: ["[[recipe-crm-card-editor-js-access]]", "[[concept-change-invasiveness-hierarchy]]", "[[entity-smart-process]]"]
aliases: ["hide-comments-in-spa"]
updated: "2026-09-18"
---

# Скрыть блок в карточке смарт-процесса

**Результат:** блок карточки (например, комментарии) скрыт во всех смарт-процессах, кроме одного —
без правки системного шаблона и без подмены компонента карточки.

## Предусловия
- Штатного переключателя нет: блок монтируется JS-ом на лету, опции «выключить для этого СП»
  в настройках не существует.
- Осознанный выбор уровня 3 «мягких» изменений — [[concept-change-invasiveness-hierarchy]].
  Это UI-косметика, а не ограничение доступа: данные остаются доступны через API.

## Шаги

Подключить скрипт на странице — через `local/php_interface/init.php` + свой компонент с
`addScript`, либо напрямую в своём шаблоне.

```js
(function () {
    'use strict';
    var ALLOWED_ENTITY_TYPE_ID = 1172;   // где блок показываем

    function getEntityTypeIdFromUrl() {
        // карточка смарт-процесса: /crm/type/<TYPE_ID>/details/<ID>/
        var m = location.pathname.match(/\/crm\/type\/(\d+)\//);
        return m ? parseInt(m[1], 10) : null;
    }

    function applyHideRule() {
        var typeId = getEntityTypeIdFromUrl();
        if (typeId === null) return;                    // не карточка смарт-процесса
        if (typeId === ALLOWED_ENTITY_TYPE_ID) return;  // здесь показываем

        var block = document.querySelector('.crm-entity-stream-section-comment');
        if (block) block.style.display = 'none';
    }

    applyHideRule();                                    // 1. сразу при загрузке

    new MutationObserver(applyHideRule)                 // 2. карточка дорисовывается AJAX-ом
        .observe(document.body, { childList: true, subtree: true });

    BX.addCustomEvent('SidePanel.Slider:onLoad', applyHideRule);   // 3. карточки в слайдере
    BX.addCustomEvent('SidePanel.Slider:onClose', applyHideRule);
})();
```

## Проверка результата
- Открыть карточку «запрещённого» типа напрямую по ссылке и через слайдер из списка — блока нет.
- Открыть карточку разрешённого типа — блок на месте.
- Переключиться слайдером с одной карточки на другую — правило применяется заново.

## Подводные камни

- **`entityTypeId` меняется в URL** при переключении карточек слайдером. Без
  `SidePanel.Slider:onLoad` правило применится один раз и «забудется» на следующей карточке.
- **`MutationObserver` без `subtree: true`** упустит вложенные перерисовки блока.
- **CSS-селектор не задокументирован** и может измениться при обновлении CRM. Найден
  [[concept-platform-reverse-engineering|приёмом 3]] (DevTools + поиск по DOM). Зафиксируйте версию
  модуля `crm`, на которой проверено, и перепроверяйте после мажоров.
- **Смарт-процесс в своём разделе** открывается по адресу `/page/<раздел>/…/type/<id>/details/<id>/`,
  а не `/crm/type/…` — регулярка его не поймает. Учитывать оба вида адресов, если такой раздел
  заведён ([[recipe-custom-left-menu-section]]).
- **CSS-only-вариант не работает:** Bitrix не проставляет `entityTypeId` атрибутом на `body`.
- При множестве открытых слайдеров observer срабатывает для всех — некритично, но при заметной
  нагрузке имеет смысл дебаунс.

## Альтернативы

| Подход | Почему отказались |
|---|---|
| Подмена компонента карточки в `/local/` | огромный компонент, тяжело поддерживать при обновлениях |
| `Operation\Action` | действия работают на сервере, а UI здесь живёт на клиенте |
| Событие `OnEpilog` + `<style>` | сработает один раз на хит, слайдеры не отловит |
| Свой шаблон CRM-карточки | копия системного шаблона — нижний уровень иерархии, только как крайний случай |

## Связанное
- [[recipe-crm-card-editor-js-access]] — как из того же JS читать значения полей

[← CRM](_index-crm.md)
