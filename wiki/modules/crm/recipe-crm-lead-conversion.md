---
title: "Конвертация лида из кода"
type: recipe
module: crm
edition: box
status: draft
provenance: mixed
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Модуль CRM / Лид / Конвертация — глава с оговорками о моменте написания и о версии 20; без проверки на стенде"
tags: [crm, лид, конвертация, сделка, контакт, компания, пользовательские-поля, автоматизация, legacy]
sources: ["[[source-devbook-crm]]"]
related: ["[[recipe-crm-legacy-entity-crud]]", "[[entity-crm-legacy-events]]", "[[concept-crm-universal-api]]", "[[entity-ccrm-owner-type]]", "[[checklist-crm-launch]]"]
aliases: []
updated: "2026-09-21"
---

# Конвертация лида из кода

**Результат:** лид программно превращается в сделку нужного направления и/или в контакт и
компанию. Второй вариант — уже существующие элементы привязываются к лиду без создания новых.

> **Черновик.** Глава книги описывает состояние на момент её написания и делает оговорку о версии 20.
> В её примерах есть ошибки, живой проверки не было. Путь через Universal API (`Operation\Conversion`)
> в главе не описан — он остаётся открытым вопросом в [[concept-crm-universal-api]].

## Суть: что во что конвертируется

Конвертация, она же создание на основании, — это создание элементов-результатов по конвертируемому
элементу. Классы лежат в `\Bitrix\Crm\Conversion`
([конвертируемые элементы](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Lid/Konvertacia.html#konvertiruemye-elementy)).
На момент написания главы конвертировать можно было три сущности:

| Из | Во что |
|---|---|
| Лид | контакт, компания, сделка |
| Сделка | счёт, предложение |
| Предложение | счёт, сделка |

Поверх этой таблицы действуют ограничения:
- **повторный лид** конвертируется только в сделку;
- **ролевая карта:** результатом может быть только сущность, которую пользователь вправе создавать.
  Нет права на сделку — лид уйдёт только в контакт или компанию.

Отдельно книга выделяет **псевдоконвертацию** — привязку уже существующих элементов к лиду через
`LEAD_ID` (ниже).

## Когда применять

- Лид нужно конвертировать без человека: из интеграции, агента, своего интерфейса, пакетно
  (вывод команды).
- Нужно связать с лидом сделку, контакт или компанию, созданные раньше и отдельно.

## Где живут правила: схемы конвертации

- Схемы лежат в `b_crm_conv_map` (`\Bitrix\Crm\Conversion\Entity\EntityConversionMapTable`). Работать с
  таблицей напрямую книга не рекомендует
  ([схемы](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Lid/Konvertacia.html#konvertacionnye-shemy)).
- Колонки схемы:
  - `SRC_TYPE_ID` / `DST_TYPE_ID` — типы по [[entity-ccrm-owner-type|\CCrmOwnerType]];
  - `LAST_UPDATED` — дата обновления схемы;
  - `DATA` — сериализованная карта полей;
  - `RELATION_TYPE` — `\Bitrix\Crm\Relation\RelationType::CONVERSION` или `::BINDING`; для связи
    (`BINDING`) карта не нужна;
  - `IS_CHILDREN_LIST_ENABLED` — показывать ли список связанных элементов в карточке исходного.
- Инструкция карты по полю: `srcField`, `dstField`, `altSrcFields` (откуда ещё взять значение, если
  `srcField` пуст), `isLocked`, `isRequired`. **Последние два флага не обрабатываются**: `true` и
  `false` дают одинаковый результат.
- Стандартные поля переносятся по жёсткой схеме ядра. Пользовательские поля — через синхронизацию:
  UF лида **создаются во всех целевых сущностях**. Совпадение ищется по коду один к одному: если поле
  с тем же кодом в цели уже есть, новое не создаётся, а ставится связь. До версии 20 включительно так
  обрабатывались все UF, позже — только с префиксом `UF_CRM_`. Имеется в виду версия продукта или
  модуля `crm`, книга не уточняет — сверьте со своей версией.

Отсюда следствие: поле темы обращения, заведённое в лиде (пример книги), после конвертации в сделку и
контакт появится в обеих сущностях, даже если там оно не нужно. Если какой-то цели поля лида не
нужны, выключите для неё синхронизацию в пути 2 (вывод команды).

## Путь 1 — простой: `Automation\Converter`

Самый простой способ по книге — средства автоматизации из `\Bitrix\Crm\Automation\Converter`
([простой способ](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Lid/Konvertacia.html#prostoj-sposob-konvertacii)):

```php
use Bitrix\Crm\Automation;
use Bitrix\Main\Loader;

Loader::requireModule('crm');

$leadId     = 128;
$userId     = 1030;   // от чьего имени конвертация: права на целевые сущности — его
$categoryId = 1;      // направление сделки

try {
    $converter = Automation\Converter\Factory::create(\CCrmOwnerType::Lead, $leadId);
    $converter->enableActivityCompletion(true);              // закрыть дела после конвертации
    $converter->setTargetItem(\CCrmOwnerType::Deal, ['categoryId' => $categoryId]);
    $converter->setTargetItem(\CCrmOwnerType::Contact, []);

    $result = $converter->execute(['USER_ID' => $userId]);

    // регистрация связей конвертации; в книге — до проверки успеха
    Automation\Factory::registerConversionResult(\CCrmOwnerType::Lead, $leadId, $result);

    if (!$result->isSuccess()) {
        $errors = $result->getErrorMessages();
    }
} catch (\Throwable $e) {
    // залогировать; книга оборачивает весь блок в try/catch
}
```

Регистрация результата у книги идёт до проверки `isSuccess()`. Нужна ли она при неуспехе —
**проверить на стенде**.

## Путь 2 — явный: конфигурация и мастер

Процесс из четырёх шагов: конфигурация (наследник `EntityConversionConfig`) → мастер (наследник
`EntityConversionWizard`) → параметры → запуск. Для лида это `LeadConversionConfig` и
`LeadConversionWizard`
([конвертация лида](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Lid/Konvertacia.html#konvertacia-lida)).

```php
use Bitrix\Crm\Conversion;
use Bitrix\Crm\Synchronization\UserFieldSynchronizer;
use Bitrix\Main\Loader;

Loader::requireModule('crm');

$leadId     = 128;
$userId     = 1030;
$categoryId = 1;

$config = new Conversion\LeadConversionConfig();

$deal = $config->getItem(\CCrmOwnerType::Deal);
if ($deal) {
    $deal->setActive(true);
    $deal->enableSynchronization(true);                     // UF лида — в сделку
    $deal->setInitData(['categoryId' => $categoryId]);      // для сделки книга называет только categoryId
}

$contact = $config->getItem(\CCrmOwnerType::Contact);
if ($contact) {
    $contact->setActive(true);
    $contact->enableSynchronization(false);                 // в контакт поля лида не тащим
    $contact->setInitData(['defaultName' => 'Без имени']);  // для контакта — только defaultName
}

// синхронизация UF — до запуска мастера
foreach ($config->getItems() as $item) {
    $dst = (int)$item->getEntityTypeID();
    if (!UserFieldSynchronizer::needForSynchronization(\CCrmOwnerType::Lead, $dst)) {
        continue;
    }
    if ($item->isSynchronizationEnabled()) {
        UserFieldSynchronizer::synchronize(\CCrmOwnerType::Lead, $dst);
    } else {
        UserFieldSynchronizer::markAsSynchronized(\CCrmOwnerType::Lead, $dst);
    }
}

$wizard = new Conversion\LeadConversionWizard($leadId, $config);
$wizard->enableBizProcCheck(false);        // иначе БП с автозапуском и параметрами даст ошибку мастера
$wizard->setSkipBizProcAutoStart(true);    // не запускать БП лида с автозапуском при изменении
$wizard->enableActivityCompletion(true);   // завершить дела после конвертации

if ($wizard->execute(['USER_ID' => $userId])) {
    $data = $wizard->getResultData();      // формат результата книга не раскрывает
} else {
    $error = $wizard->getErrorText();
}
```

| Вызов | Что делает (по книге) |
|---|---|
| `$config->getItem($typeId)` → `setActive(true)` | включить цель конвертации |
| `$item->enableSynchronization(bool)` | переносить ли UF лида в цель |
| `$item->setInitData([...])` | начальные данные: у контакта только `defaultName`, у сделки только `categoryId` |
| `UserFieldSynchronizer::needForSynchronization / synchronize / markAsSynchronized` | проверить, нужна ли синхронизация пары лид → цель; синхронизировать или, если у цели синхронизация выключена, пометить пару синхронизированной |
| `$wizard->enableBizProcCheck(false)` | не проверять БП; при БП с автозапуском и параметрами мастер иначе вернёт ошибку |
| `$wizard->setSkipBizProcAutoStart(true)` | не запускать БП лида, настроенные на автозапуск при изменении |
| `$wizard->enableActivityCompletion(true)` | завершить дела после конвертации |
| `$wizard->execute(['USER_ID' => …])` | запуск; `false` — текст в `getErrorText()`, успех — данные в `getResultData()` |
| `$config->enablePermissionCheck(false)` | по имени — отключить проверку прав; комментарий в примере книги говорит о проверке пользовательских полей — **проверить на стенде** |
| `$wizard->enableUserFieldCheck(false)` | по имени — отключить проверку UF; комментарий в примере книги говорит о синхронизации UF — **проверить на стенде** |

Последние два вызова в пример выше не включены: их точный эффект книга описывает противоречиво.

## Привязка без создания («псевдоконвертация»)

`LEAD_ID` — общее поле сделки, контакта и компании, но обрабатывается оно в каждой сущности
по-своему. Поэтому проставляют его **только через старое API** (`Update`), никогда SQL-запросом
([конвертационные связи](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Lid/Konvertacia.html#konvertacionnye-svazi)):

```php
$dealId = 256;                                  // существующая сделка
$fields = ['LEAD_ID' => $leadId];
$dealObject = new \CCrmDeal(false);
if (!$dealObject->Update($dealId, $fields, true, true, ['CURRENT_USER' => $userId])) {
    $error = $fields['RESULT_MESSAGE'] ?? $dealObject->LAST_ERROR;
}

// только если LEAD_ID уже проставили прямым SQL (так делать нельзя) — пересчитать статистику лида
\Bitrix\Crm\Statistics\LeadConversionStatisticsEntry::processBindingsChange($leadId);
```

**Противоречие в книге:** в таблицах полей контакта, компании и сделки `LEAD_ID` помечен `RO`, а эта
глава велит заполнять его через `Update`. Примет ли `Update` поле на вашей версии — **проверить на
стенде**. Методы и опции `Update` — [[recipe-crm-legacy-entity-crud]].

## Проверка результата

- Путь 1: `$result->isSuccess()` вернул `true`. Путь 2: `execute()` вернул `true`.
- Созданные элементы находятся через `GetListEx` с фильтром `LEAD_ID` = ID лида (вывод команды: в книге
  это поле связи с лидом).
- В карточке лида виден список связанных элементов, если в схеме включён `IS_CHILDREN_LIST_ENABLED`.
- Дела лида закрыты, если это включали. БП не стартовали, если выставлен `setSkipBizProcAutoStart(true)`.
- Проверьте, в каких целях появились поля лида. Ожидаем — только там, где синхронизация включена
  (вывод команды).

## Откат и проблемы

- **Не править `b_crm_conv_map` руками** и не рассчитывать на `isLocked` / `isRequired` в карте.
- **Не проставлять `LEAD_ID` SQL-запросом.** Если уже проставили — `processBindingsChange($leadId)`.
- **Повторный лид** в контакт или компанию не сконвертировать: только в сделку.
- **Пользователь без прав на целевую сущность** — ролевая карта её отсечёт. Для фоновой конвертации
  передавайте `USER_ID` пользователя с нужными правами (вывод команды).
- **Мусорные UF в целях.** Синхронизация создаёт поля лида в каждой цели, поэтому коды UF планируйте
  заранее.
- **БП с автозапуском и параметрами** без `enableBizProcCheck(false)` роняют мастер с ошибкой.
- **Не копировать пример книги как есть:** в явном пути комментарии к `enablePermissionCheck` и
  `enableUserFieldCheck` не совпадают с именами методов, а у блока сделки стоит комментарий про
  контакт.
- Отката конвертации книга не описывает. Сначала — на тестовом стенде (вывод команды).

## Источники и связанное
- Книга: [Лид → Конвертация](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Lid/Konvertacia.html).
- [[recipe-crm-legacy-entity-crud]] — `Update` через `CCrm*`, опции, поле `LEAD_ID`
- [[entity-crm-legacy-events]] — события старого ядра CRM
- [[concept-crm-universal-api]] — UA и открытый вопрос про `Operation\Conversion`
- [[checklist-crm-launch]] — решение «работаем с лидами или без» на запуске CRM

[← CRM](_index-crm.md)
