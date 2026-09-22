---
title: "Лид, контакт, компания, сделка через старое API CCrm*"
type: recipe
module: crm
edition: box
status: verified
provenance: mixed
verified: "2026-09-22 / коробка в Docker, crm 26.800.0: CRUD через CCrm* с мультиполями, GetListEx (LIMIT/OFFSET, __CONDITIONS), LEAD_ID и поиск по телефону прогнаны на тестовых элементах; на этой версии Universal API включён для всех четырёх сущностей. Текст — «Книга разработчика Bitrix24» (снимок 2026-09-21): Модуль CRM — Лид, Контакт, Компания, Сделка; Универсальное API"
tags: [crm, старое-api, ccrmlead, ccrmcontact, ccrmcompany, ccrmdeal, getlistex, мультиполя, права, legacy]
sources: ["[[source-devbook-crm]]"]
related: ["[[entity-crm-legacy-events]]", "[[recipe-crm-lead-conversion]]", "[[entity-crm-settings]]", "[[concept-crm-universal-api]]", "[[entity-ccrm-field-multi]]", "[[concept-bitrix-naming-conventions]]"]
aliases: []
updated: "2026-09-22"
---

# Лид, контакт, компания, сделка через старое API `CCrm*`

**Результат:** лиды, контакты, компании и сделки читаются, создаются, меняются и удаляются
классами `CCrmLead`, `CCrmContact`, `CCrmCompany`, `CCrmDeal`. Права проверяются либо
отключаются осознанно, мультиполя пишутся правильно, события и автоматизация не ломаются.

> **Проверено на стенде** (коробка в Docker, `crm` 26.800.0, 2026-09-22): CRUD, мультиполя,
> `GetListEx`, `LEAD_ID` и поиск по телефону прогнаны на тестовых элементах (созданы и удалены).
>
> **Главное, что изменилось с момента написания книги:** на этой версии **Universal API уже
> включён** для всех четырёх сущностей — `LeadSettings`, `ContactSettings`, `CompanySettings`,
> `DealSettings` отдают `isFactoryEnabled() === true`, `(new \CCrmDeal())->isUseOperation()` — тоже
> `true`. Книга описывает состояние, когда поддержка была экспериментальной
> ([Концепция UA](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Koncepcia.html#novoe-universal-noe-api)).
> При этом обещание совместимости держится: все методы `CCrm*` из рецепта на включённом UA
> отработали штатно. Вывод: **новый код пишем на [[concept-crm-universal-api|UA]]**, а этот рецепт —
> для сопровождения существующего кода и для коробок, где фабрики ещё выключены.

## Когда применять

- Сущность живёт на старом API: фабрика выключена (проверка — в предусловиях). На коробке
  26.800.0 фабрики включены по умолчанию, поэтому для **нового** кода берите
  [[concept-crm-universal-api|Container → Factory → Item + Operation]].
- Сопровождение существующего кода на `CCrm*`. По книге после включения UA он должен работать без
  изменений, а о несовместимостях просят сообщать в поддержку
  ([Как включить](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Kak_vklucit.html#rabota-mehanizma)).

Старые `C`-классы и ORM-классы `*Table` — два поколения API одной сущности:
[[concept-bitrix-naming-conventions]].

## Предусловия

- Коробка, подключён модуль `crm`. Код вне контекста CRM должен переживать отключённый модуль:
  `Loader::includeModule('crm')` с проверкой результата
  ([О модуле](https://bx24devbook.website.yandexcloud.net/Modul_CRM/O_module.html#pered-ispol-zovaniem-api)).
- Известен режим сущности ([[entity-crm-settings]]):

  ```php
  use Bitrix\Crm\Settings\DealSettings;

  // то же самое: (new \CCrmDeal())->isUseOperation(); для остальных — Lead/Contact/CompanySettings
  $uaEnabled = DealSettings::getCurrent()->isFactoryEnabled();
  ```

  Переключить режим может любой пользователь с доступом в CRM
  ([Как включить](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Kak_vklucit.html#kak-vklucit)),
  поэтому флаг проверяют в рантайме, а не один раз (вывод команды).
- Решено, от чьего имени идёт операция: ID пользователя для опции `CURRENT_USER`. В агенте и CLI
  текущего пользователя может не быть — ID передают явно (вывод команды).

## Шаги

### 1. Узнать состав полей

Лид, контакт и компания состоят из основных полей, мультиполей (`PHONE`, `EMAIL`, `WEB`, `IM`) и
пользовательских полей. Для сделки книга мультиполей не перечисляет: основные поля, регулярность и
пользовательские поля. Полный список собирается так:

```php
use Bitrix\Main\Loader;

Loader::requireModule('crm');
global $USER_FIELD_MANAGER;

$info = \CCrmContact::GetFieldsInfo();                                   // основные поля
foreach (array_keys(\CCrmFieldMulti::GetEntityTypeInfos()) as $type) {    // мультиполя
    $info[$type] = ['TYPE' => 'crm_multifield', 'ATTRIBUTES' => [\CCrmFieldInfoAttr::Multiple]];
}
foreach (array_keys($info) as $code) {
    $info[$code]['CAPTION'] = \CCrmContact::GetFieldCaption($code);
}
$userType = new \CCrmUserType($USER_FIELD_MANAGER, \CCrmContact::$sUFEntityID);
$userType->PrepareFieldsInfo($info);                                     // + UF_CRM_*
```

У ORM-классов (`\Bitrix\Crm\LeadTable`, `ContactTable`, `CompanyTable`, `DealTable`) свой набор
полей — `getMap()`. Он может не совпадать со старым API
([лид → методы](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Lid/Metody.html#polucenie-spiska-polej)).
Таблицы полей с флагами `RO`, `REQ`, `DEP`, `MUL` приведены в книге:
[лид](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Lid/Opisanie.html#pola-lidov),
[контакт](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Kontakt/Opisanie.html#pola-kontakta),
[компания](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Kompania/Opisanie.html#pola),
[сделка](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Sdelka/Opisanie.html#pola).
Коды справочников (`SOURCE_ID`, `STATUS_ID`, `STAGE_ID`) — [[concept-crm-dictionaries]].

### 2. Прочитать список

| | `CCrm<E>::GetListEx` | `\Bitrix\Crm\<E>Table::getList` |
|---|---|---|
| Права текущего пользователя | проверяет по умолчанию; отключить — `'CHECK_PERMISSIONS' => 'N'` в фильтре | не проверяет |
| Фильтр | как в `GetList` инфоблоков; сырой SQL — `__JOINS`, `__CONDITIONS` | ORM, подзапросы |
| Скорость | — | быстрее (трансляция в SQL) |
| Поля | `GetFieldsInfo()` + UF | `getMap()` |

Сигнатура: `GetListEx($arOrder = [], $arFilter = [], $arGroupBy = false, $arNavStartParams = false,
$arSelectFields = [], $arOptions = [])`
([старое API](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Lid/Metody.html#ispol-zua-staroe-api)).

```php
// старое API: права проверяются, пока в фильтре нет CHECK_PERMISSIONS => N
$res = \CCrmDeal::GetListEx(
    ['ID' => 'DESC'],
    ['STAGE_ID' => 'NEW', 'CHECK_PERMISSIONS' => 'N'],
    false,
    false,
    ['ID', 'TITLE', 'ASSIGNED_BY_ID'],
    ['QUERY_OPTIONS' => ['LIMIT' => 50, 'OFFSET' => 0]]   // прямые LIMIT и OFFSET
);
while ($deal = $res->fetch()) {
    // $deal['ID'], $deal['TITLE']
}

// DataManager: быстрее и гибче, но права не проверяет
$rows = \Bitrix\Crm\DealTable::getList([
    'select' => ['ID', 'TITLE'],
    'filter' => ['><DATE_CREATE' => [
        new \Bitrix\Main\Type\DateTime('01.01.2026 00:00:00'),
        new \Bitrix\Main\Type\DateTime('31.01.2026 23:59:59'),
    ]],
    'order'  => ['ID' => 'DESC'],
]);
foreach ($rows as $row) {
    // пользователю не показывать без своей проверки прав
}
```

- `QUERY_OPTIONS` удобен для двухшаговой навигации: сначала выбрать ID, потом данные по ним
  ([LIMIT и OFFSET](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Lid/Metody.html#pramoe-ukazanie-limit-i-offset)).
- Ключи фильтра `__JOINS` (список массивов с `TYPE` и `SQL`) и `__CONDITIONS` (список массивов с
  `SQL`) вставляют строку в SQL как есть и **открыты для SQL-инъекций**
  ([внешние таблицы](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Lid/Metody.html#zaprosy-s-ucastiem-vnesnih-tablic-v-fil-tre)).
- **Псевдоним основной таблицы — `L` у всех четырёх сущностей** (`const TABLE_ALIAS = 'L'` в
  `CCrmLead`, `CCrmContact`, `CCrmCompany`, `CCrmDeal`, коробка 26.800.0). Книжные `CO` для
  компании и `D` для сделки в этой версии не работают; на стенде `__CONDITIONS` с `C.ID` для
  контакта падает с `Unknown column 'C.ID' in 'where clause'`, а с `L.ID` отрабатывает. Перед
  использованием на своей версии — тот же однострочный тест.
- Запись через `*Table` книга не показывает: DataManager у неё только для выборки.

### 3. Создать

```php
$userId = 1030;                                   // от чьего имени действуем

$fields = [
    'NAME'           => 'Иван',
    'LAST_NAME'      => 'Тестов',
    'COMPANY_IDS'    => [128],                    // не устаревший COMPANY_ID
    'OPENED'         => 'Y',
    'ASSIGNED_BY_ID' => $userId,
    'FM' => [
        'PHONE' => ['n0' => ['VALUE' => '+70000000000', 'VALUE_TYPE' => 'WORK']],
        'EMAIL' => ['n0' => ['VALUE' => 'client@example.com', 'VALUE_TYPE' => 'WORK']],
    ],
];

$contact = new \CCrmContact(true);                // true — проверять права пользователя из CURRENT_USER
$id = $contact->Add($fields, true, ['CURRENT_USER' => $userId]);
if (!$id) {
    $error = $fields['RESULT_MESSAGE'] ?? $contact->LAST_ERROR;
}
```

- Конструктор: `new \CCrm<E>(true)` проверяет права пользователя из `CURRENT_USER`, `false` не
  проверяет.
- `Add(array &$arFields, $bUpdateSearch = true, $options = [])` вернёт ID или `false`. Второй аргумент
  отвечает за пересчёт поискового индекса.
- Ошибка — в `$arFields['RESULT_MESSAGE']` или `->LAST_ERROR`. Туда же попадает текст отмены из
  обработчика `OnBefore…Add` ([[entity-crm-legacy-events]]).
- Внутри `Add` — проверка прав и обязательных полей, `OnBefore…Add`, запись, расчёт прав, лента,
  `OnAfter…Add`. Полный порядок — на страницах книги: [лид](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Lid/Metody.html#sozdanie-lida),
  [контакт](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Kontakt/Metody.html#sozdanie-kontakta),
  [компания](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Kompania/Metody.html#sozdanie-kompanii),
  [сделка](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Sdelka/Metody.html#sozdanie-sdelki).

### 4. Изменить

```php
$fields = ['STAGE_ID' => 'WON'];                  // только то, что меняем
$deal = new \CCrmDeal(false);                     // техническая операция, без проверки прав
$ok = $deal->Update(256, $fields, true, true, [
    'CURRENT_USER'         => $userId,
    'ENABLE_SYSTEM_EVENTS' => true,               // false — OnBefore/OnAfterCrmDealUpdate не вызовутся
    'REGISTER_SONET_EVENT' => true,
]);
if (!$ok) {
    $error = $fields['RESULT_MESSAGE'] ?? $deal->LAST_ERROR;
}
```

`Update($ID, array &$arFields, $bCompare = true, $bUpdateSearch = true, $options = [])` вернёт
`bool`. `$bCompare` сравнивает с прежними значениями, чтобы записать события изменения. Массив полей
передаётся по ссылке, поэтому нужна переменная.

**Мультиполя (`FM`).** В `Add` ключи `n0`, `n1`… — новые значения. В `Update` ключ — ID существующей
строки: непустой `VALUE` меняет её, пустой удаляет:

```php
$fields = ['FM' => ['PHONE' => [
    '3567' => ['VALUE' => '+70000000001', 'VALUE_TYPE' => 'WORK'],   // изменить строку 3567
    '1234' => ['VALUE' => '', 'VALUE_TYPE' => 'HOME'],               // удалить строку 1234
]]];
(new \CCrmContact(false))->Update(128, $fields, true, true, ['CURRENT_USER' => $userId]);
```

Хранение и пакетная запись мультиполей (там `n…`, ID и пустое значение смешиваются в одном вызове) —
[[entity-ccrm-field-multi]].

### 5. Удалить

```php
$lead = new \CCrmLead(false);
if (!$lead->Delete(128, ['CURRENT_USER' => $userId])) {   // CURRENT_USER — чьё имя попадёт в историю
    $error = $lead->LAST_ERROR;
}
```

`Delete($ID, $arOptions = []): bool` — только по ID. Для большинства случаев книга считает
достаточным один `CURRENT_USER`, остальные опции ниже. Delete-события срабатывают и при переносе в
корзину — [[entity-crm-legacy-events]].

### 6. Выбрать опции

Список собран по примерам книги; у каждой сущности свой набор, поэтому колонка «Где» важна.

| Опция | Где (по книге) | Что делает |
|---|---|---|
| `CURRENT_USER` | Add, Update, Delete — все четыре | от чьего имени действие и проверка прав; в `Delete` по умолчанию — текущий пользователь |
| `IS_RESTORATION` | Add — все четыре | **только для восстановления**: разрешает заполнить `DATE_CREATE`, `DATE_MODIFY` |
| `DISABLE_USER_FIELD_CHECK` | Add лида и сделки; Update всех четырёх | не проверять обязательность и валидацию пользовательских полей (у лида — «обязательные со стадии») |
| `DISABLE_REQUIRED_USER_FIELD_CHECK` | там же | не проверять только обязательность, валидация остаётся; при `DISABLE_USER_FIELD_CHECK` игнорируется |
| `IS_SYSTEM_ACTION` | Update — все четыре | не записывать изменившего пользователя и не менять дату изменения |
| `ENABLE_SYSTEM_EVENTS` | Update лида и сделки | `false` — события обновления не вызываются |
| `REGISTER_SONET_EVENT` | Update — все четыре; Add сделки | сообщение в ленту |
| `DISABLE_TIMELINE_CREATION` | Add и Update сделки | строка `'Y'`: не создавать запись в таймлайне |
| `ENABLE_CLOSE_DATE_SYNC` | Add и Update сделки | при переходе в финальную стадию обновить «Дату завершения» |
| `SYNCHRONIZE_STATUS_SEMANTICS` / `SYNCHRONIZE_STAGE_SEMANTICS` | Update лида / сделки | пересчёт семантики статуса или стадии; для лида книга советует оставлять значение по умолчанию |
| `ENABLE_ACTIVITY_COMPLETION` | Update лида | по настройке CRM завершать дела при закрытии лида |
| `ENABLE_DUP_INDEX_INVALIDATION` | Update контакта и компании; Delete лида, контакта, компании | пометить кеш дубликатов неактуальным |
| `PROCESS_BIZPROC` | Delete — все четыре | удалить связанные бизнес-процессы (по умолчанию `true`) |
| `ENABLE_DEFERRED_MODE` | Delete — все четыре | отложенная очистка; по умолчанию — `<Type>Settings::getCurrent()->isDeferredCleaningEnabled()` |
| `REGISTER_STATISTICS` | Delete сделки | убрать данные сделки из статистики (по умолчанию `true`) |

### Поля: устаревшие и особые

- **Сделка:** `CONTACT_ID` устарел, вместо него `CONTACT_IDS` (массив). У лида `CONTACT_ID` тоже
  помечен устаревшим, замену книга не называет.
- **Контакт:** `COMPANY_ID` устарел; рядом множественное `COMPANY_IDS`, его и использует пример книги.
  Адресные поля `ADDRESS*` помечены устаревшими (кроме `ADDRESS_LOC_ADDR_ID`).
- **Компания:** `CONTACT_ID` — множественная привязка (массив ID контактов), устаревшей не помечена.
  На адреса (`ADDRESS*`, `REG_ADDRESS*`) и `BANKING_DETAILS` не опираться: эти данные хранят в
  реквизитах ([поля компании](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Kompania/Opisanie.html#pola)).
- **`ORIGINATOR_ID`, `ORIGIN_ID`** (у контакта и компании ещё `ORIGIN_VERSION`) заполняются только при
  создании из внешней системы; для контакта, компании и сделки книга добавляет, что при обновлении
  они меняться не должны. Ключом синхронизации их не используют; свой внешний ключ держат в своём
  UF-поле (вывод команды). Заданный `ORIGIN_ID` ещё и вызывает `OnAfterExternalCrm…Add`.
- **`LEAD_ID`** у контакта, компании и сделки в таблицах полей помечен `RO`, но **пишется** через
  старое API: на стенде поле проставилось и при `CCrmDeal::Add()`, и при `CCrmContact::Update()`
  (2026-09-22). Права глава о конвертации, а не таблица полей — [[recipe-crm-lead-conversion]].
- **`CONTACT_ID` у сделки заполняется сам:** после `Add` с `CONTACT_IDS => [8]` в выборке
  `CONTACT_ID = 8` (стенд). Читать можно любой, писать — только `CONTACT_IDS`.
- **`HAS_PHONE`, `HAS_EMAIL`, `HAS_IMOL`** — вычисляемые флаги `Y`/`N`. По ним фильтруют «есть
  телефон», не обращаясь к таблице мультиполей
  ([пример](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Kontakt/Primery.html#najti-vse-kontakty-u-kotoryh-est-telefon)).
- **«Моя компания»** хранится в той же таблице с `IS_MY_COMPANY = Y`. Выбирая клиентов, её отсекают
  (вывод команды).
- **Сделка:** `IS_NEW = Y` держится до первого изменения. `IS_REPEATED_APPROACH` и
  `IS_RETURN_CUSTOMER` ядро выставляет само, и они взаимоисключающие. Если у сделки указана компания,
  повторность считается по сделкам компании, контакт не проверяется
  ([описание сделки](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Sdelka/Opisanie.html#pola)).
- **Дни рождения** (лид, контакт) ищут по `BIRTHDAY_SORT`. Число для даты считает
  `\Bitrix\Crm\BirthdayReminder::prepareSorting($date)`, ближайшие даты — `getNearestEntities(...)`
  ([пример](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Kontakt/Primery.html#data-rozdenia)).

### Поиск по телефону

`CCrmFieldMulti::GetListEx` для этого не годится: значения хранятся в том виде, в каком их ввели, и
`8 (800)…` и `+7 800…` для базы — разные строки. Книга ищет через индекс дубликатов. Он должен быть
построен и актуален, а номер в нём хранится только цифрами
([поиск по номеру](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Kontakt/Primery.html#najti-kontakt-po-nomeru-telefona)):

```php
$rawPhone = '+7 (000) 000-00-00';
$digits = preg_replace('/\D+/', '', $rawPhone);   // в индексе номер — только цифры

$adapter = \Bitrix\Crm\EntityAdapterFactory::create(
    ['FM' => ['PHONE' => [
        ['VALUE' => $digits],
    ]]],
    \CCrmOwnerType::Contact
);
$duplicates = (new \Bitrix\Crm\Integrity\ContactDuplicateChecker())
    ->findDuplicates($adapter, new \Bitrix\Crm\Integrity\DuplicateSearchParams(['FM.PHONE']));

$contactIds = [];
foreach ($duplicates as $dup) {
    if (!$dup instanceof \Bitrix\Crm\Integrity\Duplicate) {
        continue;
    }
    foreach ((array)$dup->getEntities() as $entity) {       // по книге — до 50 элементов каждого типа
        if ($entity instanceof \Bitrix\Crm\Integrity\DuplicateEntity
            && (int)$entity->getEntityTypeID() === \CCrmOwnerType::Contact) {
            $contactIds[] = (int)$entity->getEntityID();
        }
    }
}
```

Для каждой сущности свой класс — в ядре есть все три: `ContactDuplicateChecker`,
`LeadDuplicateChecker`, `CompanyDuplicateChecker` (`crm/lib/integrity/`, 26.800.0); фабрика —
`DuplicateCheckerFactory`. Пример книги с `ContactDuplicateChecker` для лида берёт чужой класс.
Индекс дубликатов при записи через `CCrm*` обновляется сразу: на стенде контакт нашёлся по номеру
в том же скрипте, что его создал. Чтение мультиполей элемента —
`\CCrmFieldMulti::GetListEx([], ['ENTITY_ID' => \CCrmOwnerType::ContactName, 'ELEMENT_ID' => $id,
'TYPE_ID' => \CCrmFieldMulti::PHONE])`, подробности — [[entity-ccrm-field-multi]].

## Проверка результата

- `Add` вернул ID, `Update` и `Delete` вернули `true`.
- Элемент находится через `GetListEx` с `['ID' => $id, 'CHECK_PERMISSIONS' => 'N']` и открывается в
  карточке.
- Отработали ваши и чужие обработчики [[entity-crm-legacy-events|событий]]. Если передан
  `ENABLE_SYSTEM_EVENTS => false`, события обновления не вызываются.

## Что прогнали на стенде

Коробка в Docker, `crm` 26.800.0, 2026-09-22; тестовые контакт, лид и сделка созданы и удалены в
одном скрипте.

| Проверка | Результат |
|---|---|
| Режим сущностей | `isFactoryEnabled()` = `true` у всех четырёх; старое API при этом работает |
| `CCrmContact::Add()` с `FM` | ID вернулся, телефон и почта записаны строками мультиполей |
| `Update()` мультиполей по ID строки | непустой `VALUE` изменил строку, пустой — удалил её |
| `GetListEx` + `QUERY_OPTIONS` | `LIMIT` / `OFFSET` отрабатывают |
| `__CONDITIONS` | псевдоним `L` — работает, `C` — SQL-ошибка |
| `LEAD_ID` | пишется и в `Add` сделки, и в `Update` контакта |
| Поиск по телефону | `ContactDuplicateChecker` нашёл только что созданный контакт по цифрам номера |
| `Delete()` | `true` по всем трём сущностям, элементы исчезли |

Не проверяли поштучно таблицу опций (раздел 6) — она собрана по книге; перед использованием
незнакомой опции смотрите её в коде своей версии.

## Откат и проблемы

- **Не копировать примеры книги как есть.** В них пропущены запятые (обновление лида и сделки, полные
  примеры удаления), ключ опции в обратных кавычках (обновление компании), в удалении лида указана не
  та переменная.
- **`CHECK_PERMISSIONS => N` и `*Table::getList` в коде, который показывает данные пользователю,** —
  утечка чужих лидов и сделок (вывод команды).
- **`__JOINS` / `__CONDITIONS` с пользовательским вводом** — SQL-инъекция.
- **Запись в обход `CCrm*`** (через `*Table` или SQL) пропустит шаги `Add` / `Update`: права,
  дубликаты, поиск, ленту, события (вывод команды по спискам шагов в книге). Для `LEAD_ID` книга
  запрещает SQL прямо.
- **`IS_RESTORATION` — не способ импортировать «задним числом»:** книга разрешает его только при
  восстановлении.
- **`DISABLE_USER_FIELD_CHECK`, `IS_SYSTEM_ACTION`, `ENABLE_SYSTEM_EVENTS => false`** выключают
  проверки, аудит и чужую автоматику. Их берут только для технических операций и осознанно (вывод
  команды).
- **Зацикливание:** `Update` сущности из её собственного обработчика даёт бесконечный цикл —
  [[entity-crm-legacy-events]].
- **Отката у операций нет.** Массовые правки сначала гоняют на тестовом стенде и сохраняют исходные
  значения до `Update` (вывод команды).

## Источники и связанное
- Книга: методы [лида](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Lid/Metody.html),
  [контакта](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Kontakt/Metody.html),
  [компании](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Kompania/Metody.html),
  [сделки](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Sdelka/Metody.html);
  [структуры данных, коммуникационные поля](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Slovari/Struktury_dannyh.html#kommunikacionnye-pola).
- [[entity-crm-legacy-events]] — события, которые вызывают эти методы
- [[recipe-crm-lead-conversion]] — конвертация лида и привязка через `LEAD_ID`
- [[entity-crm-settings]], [[concept-crm-universal-api]] — когда вместо `CCrm*` писать через UA
- [[entity-ccrm-field-multi]], [[entity-ccrm-owner-type]] — мультиполя и коды типов

[← CRM](_index-crm.md)
