---
title: "Своя фабрика смарт-процесса: действия операций и поля"
type: recipe
module: smart-process
edition: box
status: verified
provenance: mixed
verified: "2026-09-22 / коробка в Docker, crm 26.800.0, main 26.750.0: фабрика развёрнута на тестовом смарт-процессе — подмена сервиса, атрибут Immutable, запрет перехода стадии, журнал удаления, поведение при удалённом типе; интерфейс (карточка, канбан, REST, роботы) не проверяли. Текст — «Книга разработчика Bitrix24» (снимок 2026-09-21)"
tags: [smart-process, crm, фабрика, servicelocator, kernel-php, операции, действия, стадии, поля]
sources: ["[[source-devbook-crm]]"]
related: ["[[recipe-cli-script-bootstrap]]", "[[concept-crm-universal-api]]", "[[entity-crm-container]]", "[[entity-crm-operation]]", "[[pattern-crm-action-vs-event]]", "[[recipe-crm-history-all-fields]]", "[[pattern-module-self-disabling-guard]]"]
aliases: []
updated: "2026-09-22"
---

# Своя фабрика смарт-процесса: действия операций и поля

> **Проверено на стенде** (коробка в Docker, `crm` 26.800.0, `main` 26.750.0, 2026-09-22): фабрика
> развёрнута на тестовом смарт-процессе, все три доработки прогнаны. Две правки по итогам:
> **регистрация сервиса принимает только замыкание** (массив `['Класс', 'метод']` даёт фатал) и
> **в `ACTION_AFTER_SAVE` удаления у элемента уже нет ID**. В примерах книги коды стадий с
> опечаткой (`D150_3:…` вместо `DT150_3:…`) — не копировать: формат подтверждён, `DT<тип>_<категория>:<код>`.

**Результат:** для одного смарт-процесса CRM отдаёт фабрику-наследника `Factory\Dynamic`, в которой
(а) поле закрыто от правки в интерфейсе, скрыто или обязательно, (б) удаление элемента пишется в
журнал, (в) запрещён переход между заданными стадиями. Остальные типы CRM работают штатно.

## Суть и когда применять

В новом API CRM своё поведение добавляют не обработчиками событий, а **действиями**, привязанными к
**операциям** (добавление, изменение, удаление…). Чтобы привязать действие, подменяют фабрику
сущности и переопределяют метод, который собирает операцию
([Кастомизация → Общее](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Kastomizacia/Kak_rabotaet.html#obsee)).
Кубики API — [[concept-crm-universal-api|Universal API]], операции и действия —
[[entity-crm-operation]].

Применять, когда:
- логика относится к одному [[entity-smart-process|смарт-процессу]] и нужен весь элемент или
  значение «до» — выбор между действием и событием разобран в [[pattern-crm-action-vs-event]];
- сохранение нужно отклонить с понятным пользователю текстом;
- поле нужно закрыть, скрыть или сделать обязательным в карточке.

Только коробка: это PHP. В облаке тех же целей добиваются роботами, БП и REST
([[concept-crm-universal-api]]).

## Предусловия
- Смарт-процесс создан; его `entityTypeId`, коды полей и стадий на этом портале лежат в настройках, а
  не в коде ([[recipe-smart-process-programmatic-creation]], [[entity-config-option]]). Книга ради
  простоты держит ID в константе `SUPER_ENTITY_TYPE_ID` и сама оговаривает, что в проекте механизм
  будет другим
  ([Изменение логики](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Smart_processy/Kastomizacia/Izmenenie_logiki.html#kastomizacia-izmenenie-logiki)).
- Код разложен по `/local/php_interface`: классы — в `classes/`, регистрация сервисов — в `kernel.php`
  ([[pattern-local-solution-structure]]). Если подмена живёт в модуле — модуль со
  [[pattern-module-self-disabling-guard|сторожем]].
- Контейнер CRM никто не подменил: `get_class(\Bitrix\Crm\Service\Container::getInstance())`
  возвращает штатный класс. Чужой класс — значит, контейнер подменяет сторонний модуль (книга называет
  модули Маркетплейса); инструкции по кастомизации — у его разработчиков
  ([Подмена фабрики → Подмена контейнера](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Kastomizacia/Podmena_fabriki.html#podmena-kontejnera)).
- В примерах `Settings` — наш небольшой класс: отдаёт из настроек ID типа, код поля и правила
  переходов. Его реализация не показана.

## Правила подмены класса

По книге ([Добавление действий → Подготовка](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Kastomizacia/Dobavlenie_dejstvij.html#podgotovka)):
1. наследоваться от штатного класса;
2. менять точечно — только переопределяемые методы;
3. сохранять интерфейсы и контракты родителя;
4. не добавлять в наследника новых методов — это осложняет поддержку. Вспомогательную логику держим
   в отдельных классах: действия, построитель, настройки.

## Шаги

### 1. Класс фабрики

Наследник `\Bitrix\Crm\Service\Factory\Dynamic`. `Loader::requireModule('crm')` в начале файла книга
ставит на случай прямого обращения к классу: родитель из `crm` должен быть загружен
([Шаг 1. Фабрика](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Smart_processy/Kastomizacia/Izmenenie_logiki.html#sag-1-fabrika)).
Три переопределения разобраны в шагах 3–5.

```php
// local/php_interface/classes/Vendor/Project/Crm/Contract/Factory.php
namespace Vendor\Project\Crm\Contract;

use Bitrix\Main\Loader;
use Bitrix\Crm\Item;
use Bitrix\Crm\Service\Context;
use Bitrix\Crm\Service\Factory\Dynamic;
use Bitrix\Crm\Service\Operation;
use Vendor\Project\Crm\Contract\Action\LogDeletion;
use Vendor\Project\Crm\Contract\Action\StageTransitionGuard;

Loader::requireModule('crm');

class Factory extends Dynamic
{
    // (а) атрибуты поля — шаг 3
    public function getUserFieldsInfo(): array
    {
        $fields = parent::getUserFieldsInfo();
        $code = Settings::lockedFieldCode();
        if (isset($fields[$code])) {
            $fields[$code]['ATTRIBUTES'][] = \CCrmFieldInfoAttr::Immutable;   // или NotDisplayed / Required
        }
        return $fields;
    }

    // (б) журнал удалений — шаг 4
    public function getDeleteOperation(Item $item, ?Context $context = null): Operation\Delete
    {
        $operation = parent::getDeleteOperation($item, $context);
        $operation->addAction(Operation::ACTION_AFTER_SAVE, new LogDeletion());
        return $operation;
    }

    // (в) запрет перехода стадии — шаг 5
    public function getUpdateOperation(Item $item, ?Context $context = null): Operation\Update
    {
        $operation = parent::getUpdateOperation($item, $context);
        $operation->addAction(Operation::ACTION_BEFORE_SAVE, new StageTransitionGuard());
        return $operation;
    }
}
```

### 2. Подключить фабрику к CRM

Сам класс ничего не меняет — CRM должна начать его отдавать. Способов в книге два
([Подмена фабрики → Способы](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Kastomizacia/Podmena_fabriki.html#sposoby-podmeny)):

| | А. Подмена контейнера | Б. Подмена сервиса фабрики |
|---|---|---|
| Регистрация в `kernel.php` | `crm.service.container` → наследник `\Bitrix\Crm\Service\Container` | `crm.service.factory.dynamic.<entityTypeId>` → построитель фабрики |
| Свой код | `getFactory(int $entityTypeId)`: наш тип — своя фабрика, остальные — `parent::` | построитель в отдельном классе |
| Ограничения | контейнер общий для всей CRM, его подменяют и модули Маркетплейса; при разработке модулей способ может не сработать | только смарт-процессы; успеть до первого `getFactory()` этого типа в хите |
| У нас | не используем | **основной способ** |

Подменять одну фабрику, а не контейнер — принятая в вики практика ([[concept-crm-universal-api]],
[[entity-crm-container]]).

**Способ А** — для справки. Контейнер регистрируется лениво, имя класса — строкой: контейнер нужен не
на каждом хите, а строка не подключает класс и не даёт фатала там, где `crm` не загружен. В
`getFactory()` для своего типа: имя сервиса —
`static::getIdentifierByClassName(static::$dynamicFactoriesClassName, [$entityTypeId])`; сервис уже в
локаторе — вернуть его; иначе взять тип `getTypeByEntityTypeId()` (`null` — СП удалён, вернуть `null`),
создать свою фабрику, зарегистрировать `addInstance()` и вернуть
([Добавление действий → Подмена контейнера](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Kastomizacia/Dobavlenie_dejstvij.html#podmena-kontejnera)).

**Способ Б.** Штатный контейнер перед созданием фабрики проверяет, нет ли уже в
[[concept-service-locator|локаторе служб]] сервиса с её именем. Зарегистрировали свой раньше —
контейнер отдаст его
([Добавление действий → Подмена фабрики](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Kastomizacia/Dobavlenie_dejstvij.html#podmena-fabriki)).
Условия:
- **Только смарт-процессы.** Имя `crm.service.factory.dynamic.<entityTypeId>` строит
  `Container::getIdentifierByClassName`; у счёта и других типов свои фабрики ([[entity-crm-factory]]).
- **До первого `getFactory()` этого типа в хите** — в `kernel.php` или на раннем событии вроде
  `OnPageStart`
  ([без подмены контейнера](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Kastomizacia/Podmena_fabriki.html#podmena-fabriki-bez-podmeny-kontejnera)).
  `init.php`, а с ним `kernel.php`, подключается раньше `OnPageStart`
  ([порядок выполнения страницы](https://bx24devbook.website.yandexcloud.net/Obsie_svedenia/Stranica.html#poradok-vypolnenia)).
- **Лениво, имя класса — строкой, построитель — отдельным классом.** Книга советует оставлять в
  `kernel.php` (секция Service locator —
  [Свой код → kernel.php](https://bx24devbook.website.yandexcloud.net/Razrabotka/Struktura_papki_local/Svoj_kod.html#kernel-php))
  только регистрацию, а построители выносить в классы
  ([Подмена фабрики](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Kastomizacia/Dobavlenie_dejstvij.html#podmena-fabriki),
  [Локатор служб → Через API](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Lokator_sluzb.html#cerez-api)).

```php
// local/php_interface/kernel.php — секция Service locator
$contractTypeId = \Vendor\Project\Crm\Contract\Settings::typeId();
if ($contractTypeId > 0) {
    $serviceLocator->addInstanceLazy(
        'crm.service.factory.dynamic.' . $contractTypeId,
        // только замыкание: массив ['Класс', 'метод'] локатор не принимает
        ['constructor' => static fn () => \Vendor\Project\Crm\Contract\FactoryBuilder::build()]
    );
}
unset($contractTypeId);
```

> **`constructor` — только `Closure`.** `ServiceLocator` хранит значение как имя класса и создаёт
> объект через `new $class(...)`, а замыкание вызывает. Массив-колбэк `['Класс', 'метод']` ломает
> портал в момент первого обращения к фабрике: `Error: Class name must be a valid object or a
> string` (`main` 26.750.0, стенд). Само ядро везде регистрирует сервисы замыканиями. Совет книги
> держать построитель в отдельном классе остаётся в силе — замыкание получается однострочным.

```php
// local/php_interface/classes/Vendor/Project/Crm/Contract/FactoryBuilder.php
namespace Vendor\Project\Crm\Contract;

use Bitrix\Main\Loader;
use Bitrix\Crm\Service\Container;

class FactoryBuilder
{
    public static function build(): ?Factory
    {
        Loader::requireModule('crm');
        $type = Container::getInstance()->getTypeByEntityTypeId(Settings::typeId());

        // СП удалён — фабрики нет, как в варианте книги с контейнером
        return $type ? new Factory($type) : null;
    }
}
```

### 3. Поле: только чтение, скрыто, обязательно

Переопределить `getUserFieldsInfo()` и дописать атрибут в `ATTRIBUTES` нужного поля (код — в шаге 1)
([Шаг 2. Readonly-поле](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Smart_processy/Kastomizacia/Izmenenie_logiki.html#sag-2-readonly-pole)):

| Атрибут | Эффект по книге |
|---|---|
| `\CCrmFieldInfoAttr::Immutable` | пользователь не может изменить поле через интерфейс |
| `\CCrmFieldInfoAttr::NotDisplayed` | поле скрыто из детальной карточки |
| `\CCrmFieldInfoAttr::Required` | поле обязательно независимо от настроек |

- **`Immutable` действует и в коде — но молча.** Проверено на стенде: правка такого поля через
  операцию обновления **отбрасывается без ошибки** — `launch()` возвращает успех, `getErrorMessages()`
  пуст, значение в базе прежнее. Контрольный опыт: то же поле через штатную фабрику (без атрибута)
  записывается. Значит все каналы, которые пишут через операции, атрибут соблюдают; обходят его
  низкоуровневое `$item->save()` и код, работающий со штатной фабрикой.
- **Тихое отбрасывание — само по себе ловушка:** ваш код увидит «сохранено», а значение не
  изменится. Если поле должно меняться из кода, не ставьте `Immutable`, а закрывайте правку
  проверкой в действии `ACTION_BEFORE_SAVE` (шаг 5) — там будет понятный текст ошибки.
- **Код поля не выводить из `entityTypeId`:** префикс `UF_CRM_<n>_` строится от ID типа, а не от
  `entityTypeId` ([[entity-crm-factory]]). Код — из настроек; `isset()` не даст дописать атрибут полю,
  которое удалили.

### 4. Журнал удалений: действие после удаления

Действие — наследник `\Bitrix\Crm\Service\Operation\Action` с методом `process(Item $item): Result`.
В операцию удаления оно добавлено на `Operation::ACTION_AFTER_SAVE` (шаг 1). Подменять ради такой
задачи всю операцию удаления книга считает слишком затратным
([Шаг 3](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Smart_processy/Kastomizacia/Izmenenie_logiki.html#sag-3-podmena-operacii-udalenia),
[логгирование элемента](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Kastomizacia/Dobavlenie_dejstvij.html#dopolnitel-noe-loggirovanie-elementa)).

```php
// local/php_interface/classes/Vendor/Project/Crm/Contract/Action/LogDeletion.php
namespace Vendor\Project\Crm\Contract\Action;

use Bitrix\Crm\Item;
use Bitrix\Crm\Service\Operation\Action;
use Bitrix\Main\Result;
use Bitrix\Main\Web\Json;

class LogDeletion extends Action
{
    public function process(Item $item): Result
    {
        \AddMessage2Log(Json::encode([
            'entityTypeId' => $item->getEntityTypeId(),
            'id'           => $item->getId(),
            'userId'       => $this->getContext()->getUserId(),   // кто удалил — из контекста операции
        ]));

        return new Result();
    }
}
```

Для проверки определить константу `LOG_FILENAME` (книга). В бою — свой журнал вместо
`AddMessage2Log`.

> **`ACTION_AFTER_SAVE` у удаления получает элемент без ID.** На стенде тот же журнал из
> `ACTION_BEFORE_SAVE` записал `id = 25`, а из `ACTION_AFTER_SAVE` — `id = 0`: после удаления
> идентификатор сброшен. Пример книги логирует именно «после» — и запишет ноль. Логируем
> **до** удаления (`ACTION_BEFORE_SAVE`), а «после» оставляем для того, что не требует ID.

### 5. Запрет перехода стадии: действие до сохранения

Действие добавлено в `getUpdateOperation()` на `Operation::ACTION_BEFORE_SAVE` (шаг 1); ошибка в
`Result` прерывает операцию
([Шаг 4](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Smart_processy/Kastomizacia/Izmenenie_logiki.html#sag-4-podmena-operacii-redaktirovania),
[запрет редактирования](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Kastomizacia/Dobavlenie_dejstvij.html#zapreta-redaktirovania-elementa)).
Нужное даёт [[entity-crm-item|элемент]]: `isChangedStageId()` — стадию меняют, `getStageId()` — новая
стадия, `remindActual(Item::FIELD_NAME_STAGE_ID)` — стадия, записанная в БД до правки.

```php
// local/php_interface/classes/Vendor/Project/Crm/Contract/Action/StageTransitionGuard.php
namespace Vendor\Project\Crm\Contract\Action;

use Bitrix\Crm\Item;
use Bitrix\Crm\Service\Operation\Action;
use Bitrix\Main\Error;
use Bitrix\Main\Result;
use Vendor\Project\Crm\Contract\Settings;

class StageTransitionGuard extends Action
{
    public function process(Item $item): Result
    {
        $result = new Result();
        if (!$item->isChangedStageId()) {
            return $result;
        }

        $from   = (string)$item->remindActual(Item::FIELD_NAME_STAGE_ID);   // было в БД
        $to     = (string)$item->getStageId();                              // ставят сейчас
        $userId = (int)$this->getContext()->getUserId();                    // пользователь операции

        if (Settings::isTransitionForbidden($from, $to, $userId)) {
            $result->addError(new Error('Этот переход стадии вам недоступен'));
        }
        return $result;
    }
}
```

**Чей пользователь.** Книга различает два источника:

| Откуда | `getUserId()` вернёт |
|---|---|
| `Container::getInstance()->getContext()` | пользователя, на чей хит пришлось выполнение |
| `$this->getContext()` в действии | пользователя, переданного в операцию |

В примере книги проверяется пользователь хита, контекст действия упомянут как вариант. В вики принято
брать **контекст операции**: у контейнера при REST-запросе остаётся «ручная» область, и источник
изменения определяется неверно ([[entity-crm-operation]], [[recipe-crm-history-all-fields]]).

**Коды стадий.** Формат — `DT<entityTypeId>_<categoryId>:<код>`, например `DT1030_12:PREPARATION`.
Книга приводит его в главе [Счета](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Scet.html#osnovnoe):
у счёта префикс `DT31_<categoryId>` строится так же, как у смарт-процессов; см. также
[[entity-smart-process]]. В примерах кастомизации у книги `D150_3:PREPARATION` и `D150_3:CLIENT` — без
«T»: с такими кодами условие не выполнится ни разу и запрет молча не сработает (вывод команды). Коды
стадий и ID пользователей — из настроек, не хардкодом
([[antipattern-bizproc-hardcoded-portal-ids]]).

**Запрет не абсолютный — проверено.** Он живёт в операции: на стенде запрещённый переход
NEW → SUCCESS через операцию отклонён нашим текстом, а разрешённый NEW → PREPARATION прошёл. Тот же
переход **успешно проходит** низкоуровневым `$item->save()` и операцией с
`disableBeforeSaveActions()` — оба раза стадия сменилась
([конфигурация операций](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Universalnoe_api/Operacii.html#konfiguracia-operacij),
[сохранение элементов](https://bx24devbook.website.yandexcloud.net/Modul_CRM/Smart_processy/Elementy.html#sohranenie)).

## Проверка результата
1. В [[entity-admin-php-console|командной PHP-строке]] или консольном скрипте
   ([[recipe-cli-script-bootstrap]]):
   ```php
   \Bitrix\Main\Loader::requireModule('crm');
   $container = \Bitrix\Crm\Service\Container::getInstance();
   var_dump(get_class($container));                     // штатный контейнер
   var_dump(get_class($container->getFactory(1030)));   // Vendor\Project\Crm\Contract\Factory
   ```
   Класс фабрики не ваш — регистрация опоздала или фабрику перехватывает кто-то ещё: перепроверить
   шаг 2 и сторонние модули, подменяющие контейнер (книга).
2. Карточка элемента: поле не редактируется, скрыто или обязательно — по выбранному атрибуту.
3. Удаление тестового элемента оставляет запись с ID и пользователем.
4. Запрещённый переход отклоняется с вашим текстом.
5. Другие смарт-процессы и сделки работают как раньше (на стенде фабрика сделки осталась штатной).

**В консоли не забудьте про права:** операции проверяют доступ, а у консольного скрипта
пользователя нет — без `$USER->Authorize(<id>)` или `disableCheckAccess()` вы получите «У вас нет
прав на изменение элемента» вместо своей ошибки (стенд).

## Что прогнали на стенде

Коробка в Docker, `crm` 26.800.0, 2026-09-22; фабрика развёрнута в `/local`, тестовый элемент создан
и удалён.

| Проверка | Результат |
|---|---|
| Подмена сервиса фабрики | `getFactory(<тип>)` отдал наш класс, фабрика сделки осталась штатной |
| Регистрация массивом `['Класс','метод']` | **фатал** `Class name must be a valid object or a string` — нужно замыкание |
| `Immutable` вне интерфейса | правка через операцию молча отбрасывается (успех, значение прежнее) |
| Запрет перехода стадии | NEW → SUCCESS отклонён нашим текстом, NEW → PREPARATION прошёл |
| Обход запрета | `$item->save()` и `disableBeforeSaveActions()` проводят запрещённый переход |
| Журнал удаления | `ACTION_BEFORE_SAVE` — ID элемента есть, `ACTION_AFTER_SAVE` — ID = 0 |
| Построитель вернул `null` | `getFactory()` вернул `null`, исключения нет |
| Коды стадий | формат `DT<entityTypeId>_<categoryId>:<код>` подтверждён; префикс UF строится от **ID типа**, а не `entityTypeId` |

Осталось непроверенным: поведение атрибутов и запрета в карточке, канбане, REST и роботах — это
браузер и отдельный портал; механика (всё это пишет через операции) уже проверена.

## Откат
Убрать регистрацию из `kernel.php` или обнулить ID типа в настройках — со следующего хита CRM
отдаёт штатную фабрику. Данные подмена не меняет; записи журнала остаются.

## Чего избегать
- **Подменять контейнер ради одной фабрики** — конфликт со сторонними модулями, см. таблицу способов.
- **Поздней регистрации** — в компоненте или обработчике позднего события: к этому моменту контейнер
  мог уже создать штатную фабрику.
- **Толстых замыканий в `kernel.php`** — только регистрация, построитель в классе.
- **Хардкода** `entityTypeId`, кодов полей и стадий, ID пользователей.
- **Копирования кодов стадий из примеров книги** — опечатка `D…` вместо `DT…`.
- **Новых методов в наследнике** фабрики или контейнера.
- **Надежды на `Immutable` как на защиту данных:** операции его соблюдают, но `$item->save()` и
  штатная фабрика — нет.
- **Регистрации сервиса массивом-колбэком** вместо замыкания — фатал на первом обращении.
- **Логирования ID элемента в `ACTION_AFTER_SAVE` удаления** — там уже ноль.
- **Забытых обновлений.** Наследник повторяет сигнатуры методов ядра; поменяются они — PHP упадёт на
  всём портале. В модуле — сторож со сверкой сигнатур ([[pattern-module-self-disabling-guard]]); в
  решении в `/local` — прогон проверки после каждого обновления `crm` (вывод команды).

## Связанное
- [[entity-crm-container]] — как `getFactory()` ищет сервис фабрики
- [[entity-crm-factory]] — методы фабрики, имя сервиса, пользовательские поля
- [[concept-service-locator]] — `addInstanceLazy` и построители
- [[recipe-crm-history-all-fields]] — рабочий пример подменённой фабрики (история изменений)
- [[recipe-smart-process-programmatic-creation]] — откуда берутся `entityTypeId` и коды полей

[← Смарт-процессы](_index-smart-process.md)
