---
title: "История смарт-процесса: писать все поля и помечать источник (человек, робот, интеграция)"
type: recipe
module: crm
edition: box
status: verified
provenance: empirical
verified: "2026-09-16 / коробка: main 26.700, crm 26.800, PHP 8.2"
tags: [crm, smart-process, история, фабрика, operation, servicelocator, аудит]
sources: []
related: ["[[pattern-crm-action-vs-event]]", "[[concept-crm-universal-api]]", "[[pattern-module-self-disabling-guard]]", "[[entity-smart-process]]"]
aliases: ["bitrix24-crm-history-all-fields"]
updated: "2026-09-18"
---

# История смарт-процесса: все поля + источник изменения

**Результат:** штатная вкладка «История» в карточке смарт-процесса показывает изменения **всех**
полей (включая пользовательские), в читаемом виде «было → стало», и отделяет правку человеком от
робота и интеграции.

## Предусловия
- Смарт-процесс работает через Universal API — [[concept-crm-universal-api]].
- Решено вмешиваться подменой фабрики одного типа, а не контейнера —
  [[pattern-crm-action-vs-event]].
- Модуль защищён сторожем: наследование ядрового класса при обновлении продукта может дать фатал —
  [[pattern-module-self-disabling-guard]].

## Почему не своя таблица

У ядра уже есть история (`b_crm_event` + `b_crm_event_relations`, `Service\EventHistory`), своя
вкладка карточки и свои права. Дублировать это — лишняя работа, которая к тому же не видна в
штатной истории. Из коробки пишутся только несколько полей: название, стадия, ответственный,
направление.

## Шаги

### 1. Подменить фабрику типа

Наследник `Factory\Dynamic`, регистрируется в `ServiceLocator` как
`crm.service.factory.dynamic.<entityTypeId>`, лениво и **только для типов, которые нам нужны** —
если настроек нет, модуль в работу CRM вообще не вмешивается.

### 2. Расширить список отслеживаемых полей

```php
protected function getTrackedFieldNames(): array
{
    $names = parent::getTrackedFieldNames();
    // НЕ getFieldsInfo(): там только системные поля, UF_CRM_* отсутствуют
    foreach ($this->getFieldsCollection()->getFieldNameList() as $name) {
        if ($this->isTrackable($name)) {
            $names[] = $name;
        }
    }
    return array_values(array_unique($names));
}
```

### 3. Свой `TrackedObject` — читаемые подписи и значения

```php
public function getTrackedObject(Item $before, Item $item = null): TrackedObject
{
    $obj = new MyTrackedItem($before, $item);          // extends TrackedObject\Item
    $obj->bindToEntityType($this->getEntityName(), $this->getEntityDescription());
    $obj->setTrackedFieldNames($this->getTrackedFieldNames());
    foreach ($this->getDependantTrackedObjects() as $d) {
        $obj->addDependantTrackedObject($d);
    }
    return $obj;
}
```

`MyTrackedItem` переопределяет три метода:

| Метод | Что делает |
|---|---|
| `getUpdateEventName()` | `parent::` + « (вручную)» / « (робот)» / « (интеграция)» |
| `getFieldNameCaption()` | для `UF_*` берёт `$field->getTitle()` вместо кода поля |
| `getFieldValueCaption()` | форматирует значение: `CUserFieldEnum` → текст варианта, `getUserBroker()->getName()` → ФИО, `CFile::GetFileArray()` → `ORIGINAL_NAME`, привязка CRM → `getItem()->getHeading()`, флажок → Да/Нет, массив → через запятую |

### 4. Запомнить источник изменения до записи истории

```php
public function getUpdateOperation(Item $item, ?Context $context = null): Operation\Update
{
    $op = parent::getUpdateOperation($item, $context);
    $op->addAction(Operation::ACTION_BEFORE_SAVE, new RememberSource(), 900001);
    return $op;
}
// RememberSource::process() запоминает $this->getContext()->getScope()
// manual | automation | rest | task | ai
```

Работает потому, что `Operation::launch()` идёт по порядку: действия «перед сохранением» → запись
элемента → `saveToHistory()` (берёт фабрику из контейнера и зовёт `getTrackedObject`) → действия
«после». К моменту записи истории источник уже запомнен. Автора записи (`Context::getUserId()`)
ядро ставит само.

## Проверка результата

Посмотреть, что будет записано, **не сохраняя** элемент:

```php
$item->set('UF_CRM_1_EXAMPLE', 'новое значение');
$data = $factory->getTrackedObject($item, $item)->prepareUpdateEventData();
```

После реальной правки — перезагрузить карточку: вкладка «История» в уже открытой карточке
закэширована и покажет старый список.

## Подводные камни

- **`getFieldsInfo()` не отдаёт `UF_CRM_*`.** Список, собранный по нему, для смарт-процесса без
  системных изменений пуст — история «молчит». Брать `getFieldsCollection()->getFieldNameList()`.
- **Ядро плохо подписывает пользовательские поля:** в записи код поля, множественные значения —
  `Array` плюс warning «Array to string conversion», списки и сотрудники — голые ID. Без своего
  форматирования история нечитаема.
- **Системные множественные поля** (например, `OBSERVERS`) ядро подписывает приведением массива к
  `int` — получается один случайный человек. Массивы форматировать самим.
- **Привязки и товары** (`CONTACT_BINDINGS`, `PRODUCTS`…) из добавленных полей исключать: значения —
  списки объектов, у товаров своя зависимая запись.
- **Контекст брать у операции** (`Action::getContext()`), а не у контейнера: у контейнера при
  REST-запросе остаётся «ручная» область.
- **Робота от бизнес-процесса так не отличить:** и робот «Изменить элемент», и БП идут через
  `Integration\BizProc\Document\Item`, который сам ставит `SCOPE_AUTOMATION`.
- `isChanged()` ядра сравнивает массивы нестрого: `['119']` и `[119]` равны — ложных записей нет.

## Откат
- Снять регистрацию фабрики в `ServiceLocator` (выключить модуль) — CRM возвращается к штатной
  истории, уже записанные записи остаются.

## Альтернативы

| Подход | Почему нет |
|---|---|
| Своя таблица + своя вкладка | дублирует ядро, отдельные права, не видно в штатной истории |
| События `onCrmDynamicItemUpdate` | нет значений «до» и области контекста |
| Таймлайн | для полей есть только стадия и ответственный; свои записи захламляют ленту |

## Связанное
- [[pattern-crm-action-vs-event]] — почему действие, а не событие
- [[recipe-crm-card-editor-js-access]] — соседний рецепт по карточке смарт-процесса

[← CRM](_index-crm.md)
