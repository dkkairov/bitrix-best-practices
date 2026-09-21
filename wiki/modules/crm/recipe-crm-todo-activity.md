---
title: "Универсальное дело (ToDo) из кода"
type: recipe
module: crm
edition: box
status: draft
provenance: mixed
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Модуль CRM / Дело — Введение, Общее API, Универсальное дело (ToDo); без проверки на стенде"
tags: [crm, дела, activity, todo, удаление, события, таймлайн]
sources: ["[[source-devbook-crm]]"]
related: ["[[entity-ccrm-owner-type]]", "[[entity-crm-legacy-events]]", "[[pattern-events-over-core-modification]]", "[[pattern-local-solution-structure]]", "[[entity-main-result]]"]
aliases: []
updated: "2026-09-21"
---

# Универсальное дело (ToDo) из кода

> **Черновик.** Книга предупреждает, что раздел о делах быстро меняется, а её примеры датированы
> 2023 годом. Перед использованием сверить классы и сигнатуры с установленной версией модуля `crm`
> и прогнать код на стенде. Пробелы книги собраны в конце страницы.

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

`ToDo::load($owner, $activityId)` возвращает `ToDo` или `null`
([Получение дела](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Delo/Universalnoe_delo.html#polucenie-dela-po-ego-id)).
Изменение — те же сеттеры и повторный `save()` на загруженном объекте; `setOwner()` перепривязывает
дело к другому элементу
([Редактирование](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Delo/Universalnoe_delo.html#redaktirovanie-dela)).

```php
$todo = ToDo::load($owner, $activityId);
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

| Ключ `$options` | Что делает |
|---|---|
| `MOVED_TO_RECYCLE_BIN` | признак, что дело переносится в корзину, а не удаляется; `SKIP_FILES` тогда включается сам |
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
- **Как передать текст причины отказа — для дел не описано.** В примере книги он пишется в локальную
  переменную и никуда не попадает. Приём с `RESULT_MESSAGE` из событий `OnBefore…Add/Update`
  ([[pattern-events-over-core-modification]]) здесь не подходит: массива полей нет, приходит только
  `$id` (вывод команды). Для удаления сделки книга передаёт текст через
  `$APPLICATION->ThrowException()`
  ([OnBeforeCrmDealDelete](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Sdelka/Cobytia.html#onbeforecrmdealdelete),
  [[entity-crm-legacy-events]]); подхватывает ли его `CCrmActivity::Delete` — проверить на стенде.

## Проверка результата
- `save()` вернул успешный `Result`; дело видно в карточке владельца, с нужным ответственным и сроком.
- После изменения `ToDo::load($owner, $id)` отдаёт объект с новыми значениями.
- После `CCrmActivity::Delete` дело пропало из карточки; при `$regEvent = true` удаление отражено в
  истории владельца.
- Запрет: удаление защищённого дела кодом возвращает `false`. Срабатывает ли обработчик при удалении
  из карточки — проверить на стенде (см. пробел 6).

## Чего избегать
- **Новых дел устаревших типов** «Звонок» и «Встреча» — их заменил ToDo.
- **«Текущего пользователя» по умолчанию** в агентах, CLI и обработчиках: ответственного задавать
  явно (вывод команды).
- **`setCheckPermissions(false)` и `$checkPerms = false` в действиях от имени сотрудника.** Своих прав у
  дел нет — это единственная проверка по правам на владельца (вывод команды).
- **Чтения дела в `OnActivityDelete`** — его уже нет.
- **Отмены удаления «ложным» значением** (`0`, `null`, пустая строка): проверка строгая, нужен именно
  `false`.
- **Переноса примеров книги без сверки:** опечатка в `use`, даты 2023 года, пример только для сделки.

## Пробелы книги — проверить на стенде
1. **Поиск дел.** Отдельного API поиска нет; книга отсылает к «Общему API», где описано только
   удаление ([Поиск дела](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Delo/Universalnoe_delo.html#poisk-dela)).
   Выборку дел элемента этот рецепт не закрывает.
2. **ID созданного дела** после `save()` книга не показывает.
3. **Смарт-процессы.** Пример — только сделка, а доступ к делам книга связывает с контактом, компанией,
   лидом и сделкой. Работает ли `ItemIdentifier` с типом [[entity-smart-process|смарт-процесса]] —
   проверить.
4. **Проверка прав по умолчанию** — поведение без `setCheckPermissions()` не описано; задавать явно.
5. **Корзина.** Переносит ли `Delete` дело в корзину сам при `MOVED_TO_RECYCLE_BIN` или флаг лишь
   сообщает контекст вызова — не уточнено.
6. **Другие пути удаления.** Книга привязывает события к `CCrmActivity::Delete`; срабатывают ли они при
   удалении из карточки, вместе с владельцем или через REST — не сказано.
7. **Текст отказа** в `OnBeforeActivityDelete` — механизм не описан; кандидат — `ThrowException`, как
   у сделки.

## Связанное
- [[entity-ccrm-owner-type]] — типы владельцев дела
- [[entity-crm-legacy-events]] — другие события старого ядра CRM
- [[pattern-events-over-core-modification]] — подписка на события старого ядра
- [[pattern-local-solution-structure]] — где держать `events.php` и классы обработчиков

[← CRM](_index-crm.md)
