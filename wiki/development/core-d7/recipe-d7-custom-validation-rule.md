---
title: "Свой валидатор и правило валидации D7"
type: recipe
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-22 / коробка в Docker, main 26.750.0: классы рецепта развёрнуты в /local/php_interface/classes и прогнаны — валидатор отдельно, правило свойства и правило класса через main.validation.service, оба способа в контроллере (Controller::run); текст — «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Технологии / Валидация"
tags: [d7, валидация, атрибуты, валидатор, правило, dto, контроллеры]
sources: ["[[source-devbook-core-d7]]"]
related: ["[[concept-validation-d7]]", "[[entity-validation-service]]", "[[entity-validation-result]]", "[[antipattern-ajax-controller-lowercase-name]]", "[[concept-code-namespaces-and-autoloading]]", "[[recipe-cli-script-bootstrap]]"]
aliases: []
updated: "2026-09-22"
---

# Свой валидатор и правило валидации D7

**Результат:** проверка, которой нет среди встроенных, оформлена двумя классами — **валидатором**
(проверяет значение) и **правилом-атрибутом** (вешается на свойство DTO, параметр action-метода или
класс). Дальше она работает через штатный `ValidationService` и в контроллерах так же, как
встроенные `#[Email]` или `#[PositiveNumber]`. Основа —
[«Собственные правила и валидаторы»](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Validacia/Svoi_pravila.html).

> **Проверено на стенде** (коробка в Docker, main 26.750.0, 2026-09-22): все классы ниже развёрнуты
> в `/local/php_interface/classes` и прогнаны. Подтвердилось всё, что обещает книга; уточнения —
> в тексте («стенд») и в разделе «Проверка результата».

**Когда применять:** встроенных правил не хватает. Сначала смотрим каталог: `RegExp`, `Length`,
`Range`, `InArray`, `Json`, `Url` и другие закрывают большинство форматов
([Существующие правила](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Validacia/Susestvuusie_pravila.html),
[[concept-validation-d7]]). Своё правило — когда логика не собирается из готовых: кратность,
контрольная сумма, согласованность нескольких полей.

## Предусловия

- Коробка с пакетом `Bitrix\Main\Validation` (`bitrix/modules/main/lib/Validation/`). В `main`
  26.750.0 он есть (стенд); **с какой версии появился, в книге не указано** — на своей версии
  проверяем существованием класса `Bitrix\Main\Validation\ValidationService`.
- PHP: правила — это атрибуты, а примеры книги используют `readonly`-свойства, значит нужен
  PHP 8.1+ (вывод из синтаксиса).
- Место для классов: в модуле — `lib/`, namespace `Vendor\Module\…`
  ([[concept-code-namespaces-and-autoloading]]); в клиентском решении — `/local/php_interface/classes/`
  ([[pattern-local-solution-structure]]). Подпространства `Validation\Validator` и `Validation\Rule`
  повторяют раскладку ядра (вывод команды).

## Что реализуем

| Что | Контракт | Удобная база |
|---|---|---|
| Валидатор | `Bitrix\Main\Validation\Validator\ValidatorInterface`: `validate(mixed $value): ValidationResult` | — |
| Правило свойства или параметра | `Bitrix\Main\Validation\Rule\PropertyValidationAttributeInterface`: `validateProperty(mixed $propertyValue): ValidationResult` | `Bitrix\Main\Validation\Rule\AbstractPropertyValidationAttribute` |
| Правило класса | `Bitrix\Main\Validation\Rule\ClassValidationAttributeInterface`: `validateObject(object $object): ValidationResult` | `Bitrix\Main\Validation\Rule\AbstractClassValidationAttribute` |

Результат и ошибка — `Bitrix\Main\Validation\ValidationResult` и `Bitrix\Main\Validation\ValidationError`
([[entity-validation-result]]). Валидатор ничего не знает о свойствах и атрибутах; правило решает,
какие валидаторы применить и с какими настройками
([Как это работает](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Validacia/Osnovnoe.html#kak-eto-rabotaet)).

Сквозной пример: количество товара — только целыми упаковками (не меньше одной и кратно её размеру).

## Шаг 1. Валидатор

По [книге](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Validacia/Svoi_pravila.html#validator):
класс реализует `ValidatorInterface`; параметры — через конструктор; внутри создаём
`ValidationResult` и кладём в него `ValidationError` со ссылкой на себя (`failedValidator: $this`) —
по ней вызывающий код узнает, кто отказал (`getFailedValidator()`). Правило **fail fast**: первый же
непройденный критерий — ошибка и выход.

```php
<?php
// local/modules/vendor.module/lib/validation/validator/packquantityvalidator.php
namespace Vendor\Module\Validation\Validator;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Validation\ValidationError;
use Bitrix\Main\Validation\ValidationResult;
use Bitrix\Main\Validation\Validator\ValidatorInterface;

Loc::loadMessages(__FILE__);

final class PackQuantityValidator implements ValidatorInterface
{
    public function __construct(private readonly int $packSize)
    {
        if ($packSize < 1) {
            throw new \InvalidArgumentException('packSize must be positive');
        }
    }

    public function validate(mixed $value): ValidationResult
    {
        $result = new ValidationResult();
        $replace = ['#SIZE#' => (string)$this->packSize];

        if (!is_int($value) || $value < $this->packSize) {
            $result->addError(new ValidationError(
                message: Loc::getMessage('VENDOR_MODULE_PACK_QUANTITY_MIN', $replace),
                failedValidator: $this,
            ));
            return $result; // fail fast
        }

        if ($value % $this->packSize !== 0) {
            $result->addError(new ValidationError(
                message: Loc::getMessage('VENDOR_MODULE_PACK_QUANTITY_MULTIPLE', $replace),
                failedValidator: $this,
            ));
        }

        return $result;
    }
}
```

```php
<?php
// local/modules/vendor.module/lang/ru/lib/validation/validator/packquantityvalidator.php
$MESS['VENDOR_MODULE_PACK_QUANTITY_MIN'] = 'Количество — целое число, не меньше #SIZE#';
$MESS['VENDOR_MODULE_PACK_QUANTITY_MULTIPLE'] = 'Количество должно быть кратно #SIZE# (целые упаковки)';
```

Языковой файл повторяет путь класса внутри `lang/ru/` — как `lang/ru/install/` в
[[recipe-module-structure-and-install]]; коды фраз — с префиксом вендора (в книге — `FUSION_…`).
Ядро ищет его так: поднимается от файла класса вверх до первой папки `lang` и берёт
`lang/<язык>/<остаток пути>` (`Loc::includeLangFiles`). Для решения в `/local` это значит: завели
`/local/php_interface/classes/lang/`, и фразы валидатора лежат в
`classes/lang/ru/Validation/Validator/PackQuantityValidator.php` (стенд).

Проверка `is_int($value)` строга: `'12'` строкой её не проходит (стенд). Из запроса приходят строки —
приводим к типу до валидации (в DTO это делает типизированное свойство, см. шаг 4).

Валидатор работает и без атрибутов — для старого кода с массивами
([без атрибутов](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Validacia/Osnovnoe.html#validatory-bez-atributov)):

```php
$check = (new PackQuantityValidator(6))->validate((int)$fields['QUANTITY']);
if (!$check->isSuccess()) {
    // $check->getErrors()
}
```

## Шаг 2. Правило для свойства или параметра

Минимальный вариант — класс с `#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]`,
реализующий `validateProperty()`. У него два недостатка, которые называет сама книга: проверка
живёт в правиле (валидатор не переиспользовать, код дублируется) и текст ошибки не поменять. Поэтому
наследуем `AbstractPropertyValidationAttribute`: объявляем `errorMessage` в конструкторе и отдаём
валидаторы из `getValidators()`. `validateProperty()` в примере книги наследник не определяет — его
даёт базовый класс
([Правила для свойства](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Validacia/Svoi_pravila.html#pravila-dla-svojstva)).

```php
<?php
// local/modules/vendor.module/lib/validation/rule/packquantity.php
namespace Vendor\Module\Validation\Rule;

use Attribute;
use Bitrix\Main\Localization\LocalizableMessageInterface;
use Bitrix\Main\Validation\Rule\AbstractPropertyValidationAttribute;
use Vendor\Module\Validation\Validator\PackQuantityValidator;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class PackQuantity extends AbstractPropertyValidationAttribute
{
    public function __construct(
        private readonly int $packSize,
        protected string|LocalizableMessageInterface|null $errorMessage = null,
    ) {
    }

    protected function getValidators(): array
    {
        return [new PackQuantityValidator($this->packSize)];
    }
}
```

- `errorMessage` объявляем ровно так, как в книге: `protected string|LocalizableMessageInterface|null`.
  Задан — вместо сообщений валидатора вернётся одно это сообщение; не задан — сообщения валидатора,
  как у встроенных правил без `errorMessage`
  ([сообщение об ошибке](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Validacia/Osnovnoe.html#soobsenie-ob-osibke-posle-validacii)).
  Механика — `ValidationErrorTrait::replaceWithCustomError()`: при успехе и при незаданном
  `errorMessage` результат возвращается **как есть**, иначе все ошибки заменяются одной, но
  `failedValidator` первого отказавшего валидатора сохраняется (ядро + стенд).
- Флаг `TARGET_PARAMETER` позволяет повесить правило на параметр метода — в том числе action-метода
  контроллера (шаг 4).
- В примере книги с `AbstractPropertyValidationAttribute` нет `use` для самого базового класса — при
  копировании импорт нужно добавить.

## Шаг 3. Правило для класса

Нужно, когда проверка затрагивает несколько свойств. Контракт — `validateObject(object $object)` и
`#[Attribute(Attribute::TARGET_CLASS)]`; база — `AbstractClassValidationAttribute`. В примере книги
каждый выход из `validateObject()` проходит через `$this->replaceWithCustomError($result)` — так
правило отдаёт единое `errorMessage` вместо своих сообщений. Значения свойств правило читает
рефлексией и перед чтением проверяет `isInitialized()`
([Правила для класса](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Validacia/Svoi_pravila.html#pravila-dla-klassa)).

Пример — два свойства должны совпадать (адрес и его повтор):

```php
<?php
// local/modules/vendor.module/lib/validation/rule/propertiesequal.php
namespace Vendor\Module\Validation\Rule;

use Attribute;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Localization\LocalizableMessage;
use Bitrix\Main\Localization\LocalizableMessageInterface;
use Bitrix\Main\Validation\Rule\AbstractClassValidationAttribute;
use Bitrix\Main\Validation\ValidationError;
use Bitrix\Main\Validation\ValidationResult;
use ReflectionObject;

Loc::loadMessages(__FILE__);

#[Attribute(Attribute::TARGET_CLASS)]
final class PropertiesEqual extends AbstractClassValidationAttribute
{
    public function __construct(
        private readonly string $first,
        private readonly string $second,
        protected string|LocalizableMessageInterface|null $errorMessage = null,
    ) {
    }

    public function validateObject(object $object): ValidationResult
    {
        $result = new ValidationResult();
        $reflection = new ReflectionObject($object);

        if (!$reflection->hasProperty($this->first) || !$reflection->hasProperty($this->second)) {
            // ошибка в объявлении правила, а не в данных
            $result->addError(new ValidationError(
                new LocalizableMessage('VENDOR_MODULE_RULE_PROPERTIES_EQUAL_UNKNOWN')
            ));
            return $this->replaceWithCustomError($result);
        }

        if ($this->read($reflection, $object, $this->first) !== $this->read($reflection, $object, $this->second)) {
            $result->addError(new ValidationError(
                new LocalizableMessage('VENDOR_MODULE_RULE_PROPERTIES_EQUAL_MISMATCH')
            ));
        }

        return $this->replaceWithCustomError($result);
    }

    private function read(ReflectionObject $reflection, object $object, string $name): mixed
    {
        $property = $reflection->getProperty($name);

        return $property->isInitialized($object) ? $property->getValue($object) : null;
    }
}
```

```php
use Bitrix\Main\Validation\Rule\Email;
use Vendor\Module\Validation\Rule\PropertiesEqual;

#[PropertiesEqual(first: 'email', second: 'emailRepeat')]
final class ChangeEmailDto
{
    public function __construct(
        #[Email]
        public readonly string $email,
        public readonly string $emailRepeat,
    ) {
    }
}
```

Своё сообщение — аргумент `errorMessage:`; тип допускает строку и `LocalizableMessageInterface`.
Ошибки правила класса создаются без `failedValidator` — так и в примере книги, — поэтому обработку
таких ошибок на `getFailedValidator()` не строим (вывод команды).

На стенде: ошибка правила класса приходит **с пустым кодом** (`0`), а не с именем свойства — правило
не знает, какое поле виновато, поэтому в интерфейсе привязать её к полю формы нечем. Нужна привязка —
делаем правило свойства (шаг 2) либо кладём имя поля в текст сообщения.

## Шаг 4. Применение

**Сервис** — как со встроенными правилами ([[entity-validation-service]]):

```php
$result = \Bitrix\Main\DI\ServiceLocator::getInstance()
    ->get('main.validation.service')
    ->validate($dto);
```

**Контроллер, скалярный параметр** — атрибут прямо на параметре action-метода; при ошибке метод не
вызывается, клиент получает ошибку в стандартном формате
([контроллеры](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Validacia/Kontrollery.html#validacia-prostyh-tipov-dannyh)).
Книга показывает так встроенный `#[PositiveNumber]`; своё правило с `TARGET_PARAMETER` работает
точно так же (стенд). Но **текст ошибки клиенту другой**: биндер (`ValidationChecker`) заворачивает
её в служебное сообщение `Invalid value to match parameter: [quantity] <текст правила>.` с кодом
`100` (`ArgumentException`), тогда как у DTO уходит чистая ошибка правила с кодом-именем свойства.
Для форм, где сообщение показывают пользователю, берём DTO; скалярный атрибут — для внутренних
вызовов и грубой отсечки.

**Контроллер, DTO** — `getAutoWiredParameters()` и `ValidationParameter`: фабрика собирает DTO из
запроса; если DTO не прошёл валидацию, действие не вызывается, а клиенту уходит JSON с ошибками
([AutoWire](https://bx24devbook.website.yandexcloud.net/Razrabotka/Tehnologii/Validacia/Kontrollery.html#avtomaticeskaa-validacia-cerez-autowire)).

```php
<?php
// local/modules/vendor.module/lib/order/addtoorderdto.php
namespace Vendor\Module\Order;

use Bitrix\Main\HttpRequest;
use Bitrix\Main\Validation\Rule\PositiveNumber;
use Vendor\Module\Validation\Rule\PackQuantity;

final class AddToOrderDto
{
    public function __construct(
        #[PositiveNumber]
        public readonly int $productId,
        #[PackQuantity(packSize: 6)]
        public readonly int $quantity,
    ) {
    }

    public static function fromRequest(HttpRequest $request): self
    {
        return new self(
            productId: (int)$request->get('productId'),
            quantity: (int)$request->get('quantity'),
        );
    }
}
```

```php
<?php
// local/modules/vendor.module/lib/controller/order.php
namespace Vendor\Module\Controller;

use Bitrix\Main\Engine\Controller; // в примерах книги базовый класс без use — namespace сверить
use Bitrix\Main\Validation\Engine\AutoWire\ValidationParameter;
use Vendor\Module\Order\AddToOrderDto;

final class Order extends Controller
{
    public function getAutoWiredParameters()
    {
        return [
            new ValidationParameter(
                AddToOrderDto::class,
                fn() => AddToOrderDto::fromRequest($this->getRequest()),
            ),
        ];
    }

    public function addAction(AddToOrderDto $dto): array
    {
        // сюда попадаем только с валидным $dto
        return ['productId' => $dto->productId, 'quantity' => $dto->quantity];
    }
}
```

```js
BX.ajax.runAction('vendor:module.Order.add', { data: { productId: 128, quantity: 12 } });
```

Имя контроллера в действии — с заглавной буквы: [[antipattern-ajax-controller-lowercase-name]].

## Группы правил

Книга о них не пишет, но `validate()` принимает вторым аргументом **группу**:
`$service->validate($dto, 'order')`. Правило попадает в группу, если реализует
`Bitrix\Main\Validation\Rule\ValidateByGroupInterface` и возвращает её имя из `getGroups()`;
встроенные правила принимают список в конструкторе — `#[PositiveNumber(groups: ['order'])]`.

Поведение (стенд): без второго аргумента применяются все правила; с группой — правила этой группы и
правила с пустым списком групп. Правило **без** интерфейса применяется всегда, даже когда запрошена
чужая группа. Поэтому если в проекте группы используются, своё правило объявляем так:

```php
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class PackQuantity extends AbstractPropertyValidationAttribute implements ValidateByGroupInterface
{
    public function __construct(
        private readonly int $packSize,
        protected string|LocalizableMessageInterface|null $errorMessage = null,
        protected array $groups = [],
    ) {
    }

    protected function getValidators(): array
    {
        return [new PackQuantityValidator($this->packSize)];
    }

    public function getGroups(): array
    {
        return $this->groups;
    }
}
```

Нужен ещё один импорт — `use Bitrix\Main\Validation\Rule\ValidateByGroupInterface;`.

## Проверка результата (прогон на стенде)

Коробка в Docker, `main` 26.750.0, классы в `/local/php_interface/classes`, запуск из консоли
([[recipe-cli-script-bootstrap]]); контроллер — через `Controller::run()` со снятыми префильтрами
(авторизация, CSRF, метод запроса).

| Что проверяли | Результат |
|---|---|
| `(new PackQuantityValidator(6))->validate(12)` | успех |
| `validate(7)` / `validate(0)` / `validate('12')` | ошибка; `getErrors()[0]->getFailedValidator()` — экземпляр `PackQuantityValidator` |
| Сервис: DTO `quantity: 7` | ошибка, **код = `quantity`** (имя свойства), текст — от валидатора |
| Сервис: DTO `productId: -1, quantity: 7` | две ошибки сразу, коды `productId` и `quantity` — правила разных свойств не мешают друг другу |
| Правило с `errorMessage` | одно своё сообщение, `failedValidator` сохранён |
| Правило класса: значения разные | ошибка, код пустой (`0`), `failedValidator` нет; фраза `LocalizableMessage` разворачивается |
| Правило класса: свойства нет | своя ошибка «правило указывает на несуществующее свойство» — исключения нет |
| Nullable-свойство не инициализировано / присвоен `null` | пропущено / провалидировано и даёт ошибку |
| Контроллер, DTO: `quantity: 12` → `quantity: 7` | успех и тело действия выполнено / `status: error`, тело **не** выполнено |
| Контроллер, скалярный параметр `#[PackQuantity]` | отсекает так же, но текст обёрнут биндером (см. шаг 4) |
| Группы: `#[PositiveNumber(groups: ['order'])]` | `validate($dto)` и `validate($dto, 'order')` — ошибка; `validate($dto, 'other')` — правило пропущено |
| Опечатка в имени атрибута (`#[PackQuantityy]`) | ошибок нет — проверка молча не выполняется |

Если своя проверка не срабатывает — смотрим в первую очередь на тип значения (`is_int` против строки
из запроса) и на то, объявлен ли атрибут с нужным `Attribute::TARGET_*`.

## Чего избегать

- ❌ Своего правила там, где хватает встроенных.
- ❌ Логики проверки прямо в правиле (голая реализация интерфейса) — дублирование и нет своего
  текста ошибки.
- ❌ Копирования примеров книги как есть — см. пропущенный импорт в шаге 2.
- ⚠️ Nullable-свойство, которое не устанавливали, пропускается; явно присвоенный `null`
  валидируется ([[entity-validation-service]]). В правиле класса неинициализированное свойство
  читаем как `null` — только после `isInitialized()`.
- ⚠️ Аргументы атрибута — константные выражения (ограничение PHP): размер упаковки, который зависит
  от товара, правилом-атрибутом не задать — такую проверку делаем в сервисе.
- ⚠️ Скалярный параметр action-метода там, где текст ошибки увидит пользователь: клиент получит
  служебную обёртку биндера. Для форм — DTO и `ValidationParameter`.
- ⚠️ Привязки ошибки к полю формы по правилу класса — у такой ошибки кода-свойства нет.
- ⚠️ Опечатки в имени правила и забытого `use`: атрибут неизвестного класса сервис **молча
  пропускает** (стенд) — проверка просто не работает. После правки правил прогоняем заведомо
  плохое значение и смотрим, что ошибка появилась.

## `Loc::getMessage()` или `LocalizableMessage`

Книга использует оба, не поясняя разницу. По коду ядра и прогону (стенд):

- `Loc::getMessage('CODE')` — фраза разворачивается **сразу**, в текущем языке; в ошибку попадает
  готовая строка. Языковой файл ядро подтянет само, по файлу вызывающего кода, даже без
  `Loc::loadMessages(__FILE__)` (`Loc::loadLazy`) — но `loadMessages` оставляем, он дешевле поиска.
- `LocalizableMessage('CODE')` — хранит код, подстановки и путь к своему файлу фраз, а разворачивает
  текст **при обращении** (`localize($lang)`, приведение к строке) и умеет сериализоваться. Годится,
  когда ошибка уедет в очередь, кэш или в ответ на другом языке. Цена — в конструкторе снимается
  backtrace, поэтому создаём его только для сообщения, которое действительно покажут.

Практика: сообщения правил и валидаторов — `LocalizableMessage` (язык ответа определится позже),
разовые тексты внутри бизнес-логики — `Loc::getMessage()`.

## Открытые вопросы

- С какой версии `main` доступна валидация — книга не указывает; на 26.750.0 пакет есть.

## Связанное
- [[concept-validation-d7]] — модель «валидатор + правило», каталог встроенных правил
- [[entity-validation-service]] — исполнитель, нюансы с `null`
- [[entity-validation-result]] — `ValidationError` и `getFailedValidator()`
- [[antipattern-ajax-controller-lowercase-name]] — имя контроллера в `runAction`
- [[recipe-module-structure-and-install]] — где `lib/` и `lang/` модуля

[← Ядро D7](_index-core-d7.md)
