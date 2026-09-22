---
title: "Универсальное дело (ToDo) из кода"
type: recipe
module: crm
edition: box
status: verified
provenance: mixed
verified: "2026-09-22 / коробка в Docker, crm 26.800.0: создание, правка, удаление дела и события прогнаны на тестовых элементах (в том числе на смарт-процессе); удаление из карточки и через REST не проверяли. Текст — «Книга разработчика Bitrix24» (снимок 2026-09-21): Модуль CRM / Дело"
tags: [crm, дела, activity, todo, удаление, события, таймлайн]
sources: ["[[source-devbook-crm]]"]
related: ["[[entity-ccrm-owner-type]]", "[[entity-crm-legacy-events]]", "[[pattern-events-over-core-modification]]", "[[pattern-local-solution-structure]]", "[[entity-main-result]]"]
aliases: []
updated: "2026-09-22"
---

# Универсальное дело (ToDo) из кода

> **Проверено на стенде** (коробка в Docker, `crm` 26.800.0, 2026-09-22): дело создано, изменено,
> удалено и защищено от удаления на тестовых элементах. Книга даёт примеры 2023 года, и **один
> из них уже не собирается**: `load()` — метод экземпляра, а не статический (шаг 3). Пробелы книги
> закрыты в разделе «Что прогнали на стенде»; что осталось непроверенным, сказано там же.

**Результат:** код ставит сотруднику дело по элементу CRM, меняет его, находит по ID, удаляет и при
необходимости запрещает удаление — штатными классами модуля `crm`, без записи в таблицы напрямую.

## Контекст: что такое дело

Любое действие с клиентом в CRM — элемент сущности «Дело» (activity). Книга делит дела на три
группы и оговаривает, что граница условная
([Введение](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Delo/index.html#vvedenie)):

| Группа | Что это |
|---|---|
| Реальные дела | существуют только в карточке CRM, вне модуля `crm` смысла не имеют |
| Прокси-дела | динамические связки с объектами других модулей: события календаря, задачи, звонки |
| Специальные | не подходят под первые две группы: «ожидание», дела REST и т. п. |

- **Где лежат.** Дело бывает только в таблице дел, только в таймлайне или и там и там. Выборка по
  одной таблице видит не все дела (вывод команды).
- **Прав на дела нет.** Доступ определяется правами на элемент-родитель: контакт, компанию, лид или
  сделку. Смарт-процессы в этом перечне книги не названы.
- **ToDo — основной тип.** Универсальное дело пришло на смену устаревшим делам «Звонок» и «Встреча»;
  книга описывает только его
  ([Универсальное дело](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Delo/Universalnoe_delo.html#universal-noe-delo-todo)).

## Когда применять
- Интеграция, обработчик или агент должен поставить сотруднику дело по элементу CRM, сдвинуть срок,
  перепоручить или снять его.
- Нужно запретить удаление части дел или отреагировать на удаление.
- **Не для прокси-дел:** задачи, события календаря и звонки — объекты своих модулей.
- **Только коробка:** это PHP-API модуля `crm`, в облаке его нет.

## Предусловия
- Модуль `crm` подключён.
- Известен владелец дела — тип и ID элемента CRM. Типы — константы
  [[entity-ccrm-owner-type|\CCrmOwnerType]].
- Подписки на события — в `local/php_interface/events.php`, код обработчиков — в классах
  ([[pattern-local-solution-structure|структура /local/php_interface]]).

```php
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Bitrix\Crm\ItemIdentifier;
use Bitrix\Crm\Activity\AutocompleteRule;
use Bitrix\Crm\Activity\Entity\ToDo;
use Bitrix\Crm\Activity\Provider\ToDo\ToDo as ToDoProvider;

Loader::requireModule('crm');
```

> В списке `use` книги опечатка: `use Main\Type\DateTime;` — такого класса нет. В других главах книга
> подключает `Bitrix\Main\Type\DateTime`
> ([пример](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Validacia/Svoi_pravila.html#pravila-dla-klassa)).

## Шаги

### 1. Владелец — `ItemIdentifier`

Дело всегда привязано к элементу CRM: `new ItemIdentifier(<тип>, <ID>)`. Пример книги — только для
сделки ([API](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Delo/Universalnoe_delo.html#api)).

```php
$owner = new ItemIdentifier(\CCrmOwnerType::Deal, 128);   // сделка 128
```

### 2. Создать

Объект `ToDo` собирают из владельца и провайдера `ToDoProvider`, заполняют сеттерами и сохраняют;
для простого дела, по книге, хватает пары полей
([Создание дела](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Delo/Universalnoe_delo.html#sozdanie-dela)):

| Метод | Что задаёт |
|---|---|
| `setDescription($text)` | текст дела |
| `setResponsibleId($userId)` | ответственный; не задан — текущий авторизованный пользователь |
| `setCheckPermissions($bool)` | проверять ли права; `true` — с проверкой |
| `setDeadline(?DateTime)` | крайний срок; `null` — без срока |
| `setAutocompleteRule($rule)` | `AutocompleteRule::NONE` — без правила; `AutocompleteRule::AUTOMATION_ON_STATUS_CHANGED` — дело завершится при смене стадии владельца |
| `save()` | сохраняет, возвращает [[entity-main-result\|`\Bitrix\Main\Result`]] |

**ID созданного дела** книга не показывает, хотя он есть сразу после `save()`: `$todo->getId()`,
он же в данных результата — `$result->getData()['id']` (стенд). Умолчание `checkPermissions` —
`true` (свойство `BaseActivity`), так что в фоновом коде его выключают осознанно.

```php
$todo = new ToDo($owner, new ToDoProvider());
$todo->setDescription('Уточнить реквизиты для счёта');
$todo->setResponsibleId(1030);       // в агенте и CLI «текущего» пользователя нет — задавать явно
$todo->setCheckPermissions(false);   // технический сценарий; от имени сотрудника — true
$todo->setDeadline(DateTime::createFromTimestamp(strtotime('tomorrow 10:00')));
$todo->setAutocompleteRule(AutocompleteRule::NONE);

$result = $todo->save();
if (!$result->isSuccess()) {
    $errors = $result->getErrorMessages();
}
```

### 3. Загрузить по ID и изменить

В книге — `ToDo::load($owner, $activityId)`, но в 26.800.0 это **метод экземпляра**:
`load(int $id): ?static` у `BaseActivity`. Владелец и провайдер берутся у объекта, на котором
вызвали, поэтому сначала создаём «пустой» `ToDo` с нужным владельцем
([Получение дела](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Delo/Universalnoe_delo.html#polucenie-dela-po-ego-id)).
Изменение — те же сеттеры и повторный `save()` на загруженном объекте; `setOwner()` перепривязывает
дело к другому элементу
([Редактирование](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Delo/Universalnoe_delo.html#redaktirovanie-dela)).

> **`load()` читает с проверкой прав — и в фоне возвращает `null`.** Внутри он идёт в
> `CCrmActivity::GetList()` без `CHECK_PERMISSIONS => 'N'`, а `setCheckPermissions(false)`
> относится только к записи. На стенде из консоли (пользователь — гость) `load()` вернул `null`
> для существующего дела, а от авторизованного пользователя — объект. В агенте, CLI и интеграции
> читайте дело через `\CCrmActivity::GetByID($id, false)` или `GetList` с
> `'CHECK_PERMISSIONS' => 'N'`. То же касается `loadNearest()` — ближайшего незавершённого дела
> владельца (метода книга не называет).

```php
$todo = (new ToDo($owner, new ToDoProvider()))->load($activityId);   // метод экземпляра
if ($todo !== null) {
    $todo->setDescription('Реквизиты получены — выставить счёт');
    $todo->setResponsibleId(1031);
    $todo->setDeadline(null);                                          // снять срок
    $todo->setAutocompleteRule(AutocompleteRule::AUTOMATION_ON_STATUS_CHANGED);
    // $todo->setOwner(new ItemIdentifier(\CCrmOwnerType::Deal, 129));  // перенести к другой сделке
    $result = $todo->save();
}
```

### 4. Удалить

Своего метода удаления у ToDo нет — общий
`\CCrmActivity::Delete($ID, $checkPerms = true, $regEvent = true, $options = []): bool`. `$checkPerms` —
проверять ли права, `$regEvent` — регистрировать ли удаление в истории элемента-владельца. Причины
отказа — `\CCrmActivity::GetErrorMessages()`
([Удаление дела](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Delo/Obsee_API.html#udalenie-dela)).

```php
if (!\CCrmActivity::Delete($activityId)) {   // с проверкой прав и записью в историю
    $errors = \CCrmActivity::GetErrorMessages();
}
// агент или CLI (пользователя нет): \CCrmActivity::Delete($activityId, false)
```

> **«Удалить» чаще значит «в корзину».** Если корзина CRM включена
> (`\Bitrix\Crm\Settings\ActivitySettings::getCurrent()->isRecycleBinEnabled()`; на свежей коробке
> — да), `Delete()` сам кладёт дело в корзину, а не стирает. Для тестовых данных это значит, что
> после уборки записи остаются в корзине — чистить её отдельно
> (`\Bitrix\Recyclebin\Recyclebin::remove($recyclebinId)`).

| Ключ `$options` | Что делает |
|---|---|
| `MOVED_TO_RECYCLE_BIN` | признак, что дело **уже** переносится в корзину: отключает ветку «положить в корзину» внутри `Delete` и включает `SKIP_FILES` |
| `ENABLE_RECYCLE_BIN` | книгой не описан: заставляет положить дело в корзину, даже если в настройках CRM она выключена |
| `ACTUAL_ITEM` | массив данных дела — экономит запрос; не массив или дело не найдено → удаление прерывается |
| `ACTUAL_BINDINGS` | текущие связи дела с элементами CRM; не передан — читается из БД |
| `SKIP_BINDINGS` | не удалять связи с элементами CRM |
| `SKIP_COMMUNICATIONS` | не удалять привязанные к делу каналы связи |
| `SKIP_FILES` | не удалять файлы дела |
| `SKIP_USER_ACTIVITY_SYNC` | не пересчитывать ближайшие дела |
| `SKIP_STATISTICS` | не пересчитывать статистику сделки или лида |
| `SKIP_ASSOCIATED_ENTITY` | не удалять связанную сущность (её определяет провайдер дела) |
| `SKIP_CALENDAR_EVENT` | не удалять связанное с делом событие календаря |

Флаги `SKIP_*` выключают шаги, которые держат данные согласованными. Ставить их, только если эти шаги
выполняет ваш код (вывод команды).

### 5. Отреагировать на удаление или запретить его

Оба события модуля `crm` вызывает `CCrmActivity::Delete` — и при удалении, и при переносе в корзину;
параметр один — `$id` дела. В примерах книги подписка идёт через `addEventHandlerCompatible`
([События метода](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Delo/Obsee_API.html#sobytia-metoda)).

| Событие | Когда | Что можно |
|---|---|---|
| `OnBeforeActivityDelete` | до удаления | вернуть **строго** `false` — удаление, в том числе перенос в корзину, прерывается |
| `OnActivityDelete` | после успешного удаления | возврат не обрабатывается; прочитать дело уже нельзя |

```php
// local/php_interface/events.php ($eventManager объявлен в шапке файла)
$eventManager->addEventHandlerCompatible(
    'crm', 'OnBeforeActivityDelete', ['\\Vendor\\Project\\Crm\\ActivityDeleteHandler', 'onBefore']
);
$eventManager->addEventHandlerCompatible(
    'crm', 'OnActivityDelete', ['\\Vendor\\Project\\Crm\\ActivityDeleteHandler', 'onAfter']
);
```

```php
// local/php_interface/classes/Vendor/Project/Crm/ActivityDeleteHandler.php
namespace Vendor\Project\Crm;

class ActivityDeleteHandler
{
    public static function onBefore($id): bool
    {
        // false (строго) отменяет удаление; любое другое значение — нет
        return !self::isProtected((int)$id);
    }

    public static function onAfter($id): void
    {
        // дела уже нет: здесь только то, что не требует его данных (свои связи по $id, журнал)
    }

    private static function isProtected(int $id): bool
    {
        return false;   // ваше правило
    }
}
```

- Данные дела, нужные после удаления, доступны только до него — в `OnBeforeActivityDelete`. Чем
  прочитать дело по одному `$id` без владельца, книга не показывает.
- **Текст причины отказа передаём через `$APPLICATION->ThrowException()`** — как у сделки
  ([OnBeforeCrmDealDelete](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Sdelka/Cobytia.html#onbeforecrmdealdelete),
  [[entity-crm-legacy-events]]). Проверено на стенде: `CCrmActivity::GetErrorMessages()` при этом
  **остаётся пустым**, а текст виден вызывающему коду в `$APPLICATION->GetException()->GetString()`.
  Значит свой код после неуспешного `Delete()` читает причину оттуда (и не забывает
  `ResetException()` перед следующей попыткой). Приём с `RESULT_MESSAGE` из `OnBefore…Add/Update`
  ([[pattern-events-over-core-modification]]) здесь не работает: массива полей нет, приходит только
  `$id`.

## Проверка результата
- `save()` вернул успешный `Result`; дело видно в карточке владельца, с нужным ответственным и сроком.
- После изменения `ToDo::load($owner, $id)` отдаёт объект с новыми значениями.
- После `CCrmActivity::Delete` дело пропало из карточки; при `$regEvent = true` удаление отражено в
  истории владельца.
- Запрет: удаление защищённого дела кодом возвращает `false`, дело остаётся в базе, а после
  снятия запрета удаляется и срабатывает `OnActivityDelete` (стенд). Срабатывает ли обработчик при
  удалении из карточки — не проверяли.

## Чего избегать
- **Новых дел устаревших типов** «Звонок» и «Встреча» — их заменил ToDo.
- **«Текущего пользователя» по умолчанию** в агентах, CLI и обработчиках: ответственного задавать
  явно (вывод команды).
- **`setCheckPermissions(false)` и `$checkPerms = false` в действиях от имени сотрудника.** Своих прав у
  дел нет — это единственная проверка по правам на владельца (вывод команды).
- **Чтения дела в `OnActivityDelete`** — его уже нет.
- **`load()` в фоновом коде** — вернёт `null` из-за проверки прав; для агентов и CLI —
  `CCrmActivity::GetByID($id, false)`.
- **Отмены удаления «ложным» значением** (`0`, `null`, пустая строка): проверка строгая, нужен именно
  `false`.
- **Переноса примеров книги без сверки:** опечатка в `use`, даты 2023 года, пример только для сделки.

## Что прогнали на стенде

Коробка в Docker, `crm` 26.800.0, 2026-09-22: тестовые сделка и элемент смарт-процесса, дела на них,
удаление с запретом и без. Всё создано и убрано, включая корзину.

| Пробел книги | Чем закрыт |
|---|---|
| ID созданного дела | `$todo->getId()`; он же в `$result->getData()['id']` |
| Проверка прав по умолчанию | `checkPermissions = true` у `BaseActivity` |
| Смарт-процессы как владелец | работает: `new ItemIdentifier($entityTypeId, $itemId)` с типом смарт-процесса, дело создалось |
| Поиск дел | отдельного API нет: `CCrmActivity::GetList()` с фильтром `BINDINGS` (+ `CHECK_PERMISSIONS`), либо `loadNearest()` у объекта дела |
| Корзина | при включённой корзине `Delete()` переносит дело в неё сам; `MOVED_TO_RECYCLE_BIN` — «уже переносится» |
| Текст отказа | `ThrowException()` в обработчике; читается через `$APPLICATION->GetException()`, не через `GetErrorMessages()` |
| Сигнатура `load()` | метод экземпляра `load(int $id)`, читает с проверкой прав |

Осталось непроверенным: вызываются ли события при удалении дела **из карточки**, вместе с
элементом-владельцем или через REST — это уже не про `CCrmActivity::Delete` и требует браузера.

## Связанное
- [[entity-ccrm-owner-type]] — типы владельцев дела
- [[entity-crm-legacy-events]] — другие события старого ядра CRM
- [[pattern-events-over-core-modification]] — подписка на события старого ядра
- [[pattern-local-solution-structure]] — где держать `events.php` и классы обработчиков

[← CRM](_index-crm.md)
