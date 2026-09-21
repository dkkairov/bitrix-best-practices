---
title: "События старого ядра CRM: лид, контакт, компания, сделка"
type: entity
module: crm
edition: box
status: verified
provenance: mixed
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Модуль CRM / Лид, Контакт, Компания, Сделка — События и Методы; Разработка / Технологии / События; Универсальное API / Кастомизация / Как работает; без проверки на стенде"
tags: [crm, события, старое-ядро, compatible, отмена, лид, контакт, компания, сделка]
sources: ["[[source-devbook-crm]]"]
related: ["[[pattern-events-over-core-modification]]", "[[pattern-crm-action-vs-event]]", "[[entity-event-manager]]", "[[recipe-crm-legacy-entity-crud]]", "[[entity-crm-settings]]"]
aliases: []
updated: "2026-09-21"
---

# События старого ядра CRM: лид, контакт, компания, сделка

**Что это:** события модуля `crm`, которые вызывают методы старого API `CCrmLead`, `CCrmContact`,
`CCrmCompany`, `CCrmDeal` при создании, изменении и удалении элемента, а у лида и сделки — ещё и
при сохранении товарных позиций. Это основная точка расширения старого ядра CRM. Если сущность
работает через Universal API, сначала рассмотрите [[pattern-crm-action-vs-event|Operation\Action]].

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | события старого ядра, модуль-источник `crm` |
| Подписка | `addEventHandlerCompatible` (на хит) или `registerEventHandlerCompatible` (при установке модуля) — [[entity-event-manager]] |
| Аргументы | Add / Update — `&$arFields` по ссылке; Delete — `$id`; товары — `$id` и массив строк |
| Отмена | только в `OnBefore…`: `return false` |
| Edition | box |
| Источник | книга: события [лида](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Lid/Cobytia.html), [контакта](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Kontakt/Cobytia.html), [компании](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Kompania/Cobytia.html), [сделки](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Sdelka/Cobytia.html) |

Методы без `Compatible` (`addEventHandler`, `registerEventHandler`) рассчитаны на события нового
ядра с одним объектом `\Bitrix\Main\Event` — для этого семейства их не используют
([События → как подписаться](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Sobytia.html#kak-podpisat-sa-na-sobytia)).

## Имена событий

| Сущность | Создание | Изменение | Удаление | Товарные позиции |
|---|---|---|---|---|
| Лид | `OnBeforeCrmLeadAdd`, `OnAfterCrmLeadAdd`, `OnAfterExternalCrmLeadAdd` | `OnBeforeCrmLeadUpdate`, `OnAfterCrmLeadUpdate` | `OnBeforeCrmLeadDelete`, `OnAfterCrmLeadDelete` | `OnAfterCrmLeadProductRowsSave` |
| Контакт | `OnBeforeCrmContactAdd`, `OnAfterCrmContactAdd`, `OnAfterExternalCrmContactAdd` | `OnBeforeCrmContactUpdate`, `OnAfterCrmContactUpdate` | `OnBeforeCrmContactDelete`, `OnAfterCrmContactDelete` | в книге нет |
| Компания | `OnBeforeCrmCompanyAdd`, `OnAfterCrmCompanyAdd`, `OnAfterExternalCrmCompanyAdd` | `OnBeforeCrmCompanyUpdate`, `OnAfterCrmCompanyUpdate` | `OnBeforeCrmCompanyDelete`, `OnAfterCrmCompanyDelete` | в книге нет |
| Сделка | `OnBeforeCrmDealAdd`, `OnAfterCrmDealAdd`, `OnAfterExternalCrmDealAdd` | `OnBeforeCrmDealUpdate`, `OnAfterCrmDealUpdate` | `OnBeforeCrmDealDelete`, `OnAfterCrmDealDelete` | `OnAfterCrmDealProductRowsSave` |

## Поведение

`<E>` — `Lead`, `Contact`, `Company` или `Deal`. Порядок шагов внутри `Add` / `Update` / `Delete` —
на страницах «Методы» книги (например, [сделка](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Sdelka/Metody.html#sozdanie-sdelki)).

| Событие | Когда | Параметры | Отменить? | Как |
|---|---|---|---|---|
| `OnBeforeCrm<E>Add` | в `Add` после проверки прав и обязательных полей, до записи | `&$arFields` — ключи можно дописать, изменить, убрать | да | `$arFields['RESULT_MESSAGE'] = '…'; return false;` |
| `OnAfterCrm<E>Add` | после записи, расчёта прав и сообщения в ленту | `&$arFields` | нет | возврат не обрабатывается |
| `OnAfterExternalCrm<E>Add` | сразу за `OnAfterCrm<E>Add` и **только если задан `ORIGIN_ID`** | как у `OnAfterCrm<E>Add` | нет | — |
| `OnBeforeCrm<E>Update` | в `Update` после проверки обязательных полей и прав, до записи | `&$arFields` — можно менять | да | `$arFields['RESULT_MESSAGE'] = '…'; return false;` |
| `OnAfterCrm<E>Update` | после записи, истории, поискового индекса и ленты | `&$arFields` | нет | возврат не обрабатывается |
| `OnBeforeCrm<E>Delete` | в `Delete` **и при переносе в корзину**, до удаления | `$id` | да | `$APPLICATION->ThrowException('…'); return false;` |
| `OnAfterCrm<E>Delete` | после удаления или переноса в корзину | `$id` | нет | элемент уже не прочитать списочными методами |
| `OnAfterCrmLeadProductRowsSave` | после `CCrmLead::SaveProductRows` | `$id`, `$arRows` — новый набор строк | нет | — |
| `OnAfterCrmDealProductRowsSave` | после правки товаров в интерфейсе или `CCrmDeal::SaveProductRows` | `$id`, `$rows` | нет | вернуть ошибку нельзя |

## Отмена операции

- Отменить можно только в `OnBefore…`. `false` означает ошибку: остальные обработчики этого события
  уже не вызываются.
- **Add / Update:** текст ошибки кладут в `$arFields['RESULT_MESSAGE']`. Вызывающий код видит его в
  своём массиве полей или в `LAST_ERROR` объекта — [[recipe-crm-legacy-entity-crud]].
- **Delete:** массива полей нет — `global $APPLICATION; $APPLICATION->ThrowException('…'); return false;`.
- **`OnAfter…`:** возврат игнорируется. Правка `$arFields` на сохранённые данные уже не влияет, но
  следующие обработчики получат изменённый массив — он передаётся по ссылке.

## Что приходит в `$arFields`

- **Не вся сущность.** Приходят поля из запроса на изменение плюс те, что добавили ядро и другие
  обработчики ([События → как подписаться](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Sobytia.html#kak-podpisat-sa-na-sobytia)).
  Пример книги: если в карточке сделки поменять только название, в `OnAfterCrmDealUpdate` придут
  `TITLE`, `~DATE_MODIFY`, `MODIFY_BY_ID` ([Как работает](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Kastomizacia/Kak_rabotaet.html#preimusestva)).
- **Проверяйте ключ** (`array_key_exists`). Нет ключа — берите значение по умолчанию или читайте из
  базы.
- **«Поле пришло» ≠ «поле изменилось»:** в запросе может прийти то же значение. Чтобы поймать именно
  изменение, в `OnBefore…` сравните с сохранённым значением и поставьте флаг, а в `OnAfter…` проверяйте
  флаг ([нет проверки аргументов](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Sobytia.html#net-proverki-nalicia-argumentov)).
  Ловушка общего статического состояния в такой паре — [[pattern-crm-action-vs-event]].
- **ID элемента.** Пример книги для `OnBeforeCrmDealUpdate` берёт его из `$arFields['ID']`, но в
  перечне ключей при правке названия `ID` не назван. **Проверить на стенде**, всегда ли `ID` есть в
  событиях Add и Update.

## Пример

Подписки — в `local/php_interface/events.php`, код — в классе
([[pattern-events-over-core-modification|Расширение через события]],
[[pattern-local-solution-structure|структура /local/php_interface]]):

```php
// local/php_interface/events.php — события старого ядра подписываем только …Compatible
$em = \Bitrix\Main\EventManager::getInstance();
$em->addEventHandlerCompatible('crm', 'OnBeforeCrmDealUpdate', [\Vendor\Crm\DealGuard::class, 'onBeforeUpdate']);
$em->addEventHandlerCompatible('crm', 'OnBeforeCrmDealDelete', [\Vendor\Crm\DealGuard::class, 'onBeforeDelete']);
```

```php
namespace Vendor\Crm;

class DealGuard
{
    /** crm::OnBeforeCrmDealUpdate — массив можно править; false отменяет обновление */
    public static function onBeforeUpdate(array &$fields): bool
    {
        if (array_key_exists('TITLE', $fields)) {             // ключа может и не быть
            $fields['TITLE'] = trim((string)$fields['TITLE']);
        }
        if (array_key_exists('OPPORTUNITY', $fields) && (float)$fields['OPPORTUNITY'] < 0) {
            $fields['RESULT_MESSAGE'] = 'Сумма сделки не может быть отрицательной';
            return false;
        }
        return true;
    }

    /** crm::OnBeforeCrmDealDelete — вызывается и при переносе в корзину */
    public static function onBeforeDelete($id): bool
    {
        // элемент ещё существует: читаем здесь, в OnAfter…Delete будет поздно
        $deal = \CCrmDeal::GetListEx(
            [],
            ['ID' => (int)$id, 'CHECK_PERMISSIONS' => 'N'],
            false,
            false,
            ['ID', 'CLOSED']
        )->fetch();

        if ($deal && $deal['CLOSED'] === 'Y') {
            global $APPLICATION;
            $APPLICATION->ThrowException('Закрытую сделку удалять нельзя');
            return false;
        }
        return true;
    }
}
```

Для модуля та же пара регистрируется при установке через `registerEventHandlerCompatible` —
[[recipe-module-structure-and-install]].

## Подводные камни

- **Нет защиты от зацикливания.** `Update` той же сущности из её же обработчика даёт бесконечный
  цикл. Средства из книги: дописать поля в `$arFields` в `OnBefore…` вместо повторного `Update`,
  идемпотентный код, статический lock-флаг
  ([зацикливание](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Sobytia.html#zaciklirovanie-obrabotcikov),
  [[pattern-events-over-core-modification]]).
- **Удаление — не всегда окончательное.** Delete-события срабатывают и при переносе в корзину, а
  оттуда элемент можно вернуть. Необратимые действия во внешних системах по `OnAfter…Delete` делайте
  с этой оглядкой (вывод команды).
- **Данные удаляемого элемента читайте в `OnBefore…Delete`.** В `OnAfter…Delete` приходит только ID, а
  сам элемент уже не прочитать (книга). Передать прочитанное дальше — через флаг/хранилище в классе,
  как в паре before/after (вывод команды).
- **События можно выключить снаружи.** Опция `ENABLE_SYSTEM_EVENTS => false` в `Update` лида и сделки
  подавляет события обновления ([лид](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Lid/Metody.html#obnovlenie-lida),
  [сделка](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Sdelka/Metody.html#obnovlenie-sdelki)) —
  такие изменения ваш обработчик не увидит. Для контакта и компании книга эту опцию не описывает —
  **проверить на стенде**.
- **`OnAfterExternal…Add` — не общее «после создания».** Он вызывается только при заданном
  `ORIGIN_ID`, а это поле заполняется лишь при создании из внешних систем.
- **Режим Universal API.** Включить UA для лида, сделки, контакта или компании может любой пользователь
  с доступом в CRM ([[entity-crm-settings]]). По книге существующий код должен работать без
  изменений, а о несовместимостях просят сообщать в поддержку
  ([Как включить](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Kak_vklucit.html#rabota-mehanizma)).
  После переключения перепроверяйте обработчики на стенде.
- **Примеры книги не копировать как есть.** Пример для `OnAfterCrmLeadDelete` подписывается на
  `OnAfterCrmLeadUpdate`, пример отмены `OnBeforeCrmDealAdd` — на `OnBeforeCrmCompanyAdd`, а тексты
  ошибок в примерах лида и контакта говорят о сделках.

## Расхождение внутри книги

Про `OnAfterExternalCrmLeadAdd` страница лида пишет, что он повторяет поведение
`OnBeforeCrmLeadAdd`. Страницы контакта, компании и сделки для своих External-событий называют
аналогом `OnAfterCrm…Add`, и во всех четырёх описаниях `Add` это событие стоит после
`OnAfterCrm…Add`. Здесь оно описано как событие «после» без отмены; формулировка на странице лида,
вероятно, опечатка — **проверить на стенде**
([лид](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Lid/Cobytia.html#onafterexternalcrmleadadd),
[контакт](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Kontakt/Cobytia.html#onafterexternalcrmcontactadd)).

## Связанное
- [[pattern-events-over-core-modification]] — где подписываться, поколения событий, обработчики
- [[pattern-crm-action-vs-event]] — когда вместо события брать `Operation\Action`
- [[entity-event-manager]] — методы подписки и отписки
- [[recipe-crm-legacy-entity-crud]] — методы `CCrm*`, которые вызывают эти события, и их опции
- [[entity-crm-settings]] — включён ли Universal API для сущности

[← CRM](_index-crm.md)
