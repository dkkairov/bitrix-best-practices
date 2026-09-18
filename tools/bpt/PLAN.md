# План реализации: каталог действий и сборщик спецификаций БП

> **Для исполнителя (агента):** реализуй по задачам, отмечая шаги галочками. Навык —
> `superpowers:subagent-driven-development` или `superpowers:executing-plans`.
> Файл временный: удаляется после сдачи блока (история остаётся в git).

**Цель:** собрать из спецификации процесса рабочий `.bpt`, а из `.bpt` — спецификацию, со
справочником действий, проверками, схемой и тестами.

**Архитектура:** `bpt.php` — тонкий CLI; вся логика в `tools/bpt/src/` по классу на задачу; каталог
действий — PHP-массив; спецификации в YAML (расширение `yaml`) или JSON. Подробности и решения — в
[DESIGN.md](DESIGN.md).

**Технологии:** PHP ≥ 8.1, расширения `zlib`, `json`, `mbstring`, опционально `yaml`. Внешних
зависимостей нет.

**Дизайн:** [tools/bpt/DESIGN.md](DESIGN.md) — план опирается на него, читать вместе.

## Общие ограничения

- PHP ≥ 8.1, без внешних зависимостей и composer.
- Разбор `.bpt` — только `unserialize(..., ['allowed_classes' => false])`.
- Коды выхода: `0` — успех, `1` — ошибки, `2` — неверные аргументы.
- Комментарии и сообщения — на русском; идентификаторы кода — латиницей.
- Клиентские данные в репозиторий не попадают: примеры синтетические, `*.bpt`, `*.bpt.json`,
  `*.portal.yaml`, `*.portal.json` — в `.gitignore` (`CLAUDE.md` §4.4).
- Каждая задача заканчивается коммитом на русском с `Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>`.
- Тесты запускаются командой `php tools/bpt/tests/run.php` (корпус — `--corpus <папка>`).

---

## Карта файлов

| Файл | Ответственность |
|------|-----------------|
| `tools/bpt/bpt.php` | разбор аргументов, вызов классов, вывод |
| `tools/bpt/src/BptFile.php` | чтение и запись `.bpt`, JSON, кодировки, `compact` |
| `tools/bpt/src/Analyzer.php` | проверки существующей команды `analyze` |
| `tools/bpt/src/Catalog.php` | каталог: типы, алиасы, свойства, формы, нормализация значений |
| `tools/bpt/src/SpecReader.php` | чтение и запись спецификаций (YAML/JSON) |
| `tools/bpt/src/Compiler.php` | спецификация → дерево → `.bpt` |
| `tools/bpt/src/Decompiler.php` | `.bpt` → спецификация |
| `tools/bpt/src/Snapshot.php` | снимок портала, плейсхолдеры |
| `tools/bpt/src/Mermaid.php` | схема процесса |
| `tools/bpt/catalog/activities.php` | данные каталога |
| `tools/bpt/tests/*` | тесты и фикстуры |

---

### Задача 1: Каркас тестов и перенос кода в `src/`

**Файлы:**
- Создать: `tools/bpt/src/BptFile.php`, `tools/bpt/src/Analyzer.php`,
  `tools/bpt/tests/run.php`, `tools/bpt/tests/support.php`,
  `tools/bpt/tests/fixtures/sample_template.php`, `tools/bpt/tests/bptfile_test.php`
- Изменить: `tools/bpt/bpt.php` (оставить только CLI)

**Интерфейсы (потребляют следующие задачи):**
```php
final class BptException extends RuntimeException {}
final class BptFile {
    public static function read(string $path, ?string $charset = null): array;    // ['data','serialized','compressed']
    public static function readAny(string $path, ?string $charset = null): array; // .bpt или .json
    public static function write(string $path, array $data, ?string $charset = null, bool $force = false): void;
    public static function toJson(array $data): string;      // pretty, UTF-8
    public static function fromJson(string $json): array;
    public static function flatJson(mixed $value): string;   // без отступов
    public static function compactTree(mixed $value): mixed;
}
final class Analyzer {
    public function __construct(?Catalog $catalog = null);
    public function analyze(string $file, array $bpt): array; // текущий отчёт + errors/warnings
}
```

- [ ] **Шаг 1: Фикстура и каркас тестов.** Создай `tests/fixtures/sample_template.php` — PHP-массив
  шаблона из двух действий (синтетические значения):

```php
<?php // Синтетический шаблон: корень → Параллельное выполнение → последовательность → смена стадии
return ['VERSION' => 2, 'TEMPLATE' => [[
    'Type' => 'SequentialWorkflowActivity', 'Name' => 'Template', 'Activated' => 'Y', 'Node' => null,
    'Properties' => ['Title' => 'Bizproc Automation template'],
    'Children' => [[
        'Type' => 'SequenceActivity', 'Name' => 'A1_1_1_1', 'Activated' => 'Y', 'Node' => null,
        'Properties' => ['Title' => 'Последовательность действий'],
        'Children' => [[
            'Type' => 'CrmChangeStatusActivity', 'Name' => 'A2_2_2_2', 'Activated' => 'Y', 'Node' => null,
            'Properties' => ['TargetStatus' => 'DT1000_10:CLIENT', 'ModifiedBy' => [], 'Title' => 'Сменить стадию', 'EditorComment' => ''],
            'Children' => [],
        ]],
    ]],
]], 'PARAMETERS' => [], 'VARIABLES' => [], 'CONSTANTS' => [], 'DOCUMENT_FIELDS' => [
    'STAGE_ID' => ['Name' => 'Стадия', 'Type' => 'select', 'Options' => ['DT1000_10:NEW' => 'Общая/Новая', 'DT1000_10:CLIENT' => 'Общая/Клиент']],
    'TITLE' => ['Name' => 'Название', 'Type' => 'string'],
]];
```

- [ ] **Шаг 2: Раннер тестов.** `tests/support.php` — регистрация и проверки:

```php
<?php
$GLOBALS['bpt_tests'] = [];
function test(string $name, callable $fn): void { $GLOBALS['bpt_tests'][$name] = $fn; }
function assertSame(mixed $expected, mixed $actual, string $msg = ''): void {
    if ($expected !== $actual) {
        throw new RuntimeException(trim($msg . ' ожидалось: ' . json_encode($expected, JSON_UNESCAPED_UNICODE)
            . ', получено: ' . json_encode($actual, JSON_UNESCAPED_UNICODE)));
    }
}
function assertTrue(bool $cond, string $msg): void { if (!$cond) throw new RuntimeException($msg); }
function assertThrows(callable $fn, string $needle, string $msg = ''): void {
    try { $fn(); } catch (Throwable $e) {
        if (!str_contains($e->getMessage(), $needle)) throw new RuntimeException("$msg: в ошибке нет «$needle»: {$e->getMessage()}");
        return;
    }
    throw new RuntimeException("$msg: ошибка не возникла");
}
function tmpPath(string $suffix): string { return sys_get_temp_dir() . '/bpt_test_' . bin2hex(random_bytes(4)) . $suffix; }
function fixtureBpt(): string { // собирает .bpt во временный файл
    $path = tmpPath('.bpt');
    file_put_contents($path, gzcompress(serialize(require __DIR__ . '/fixtures/sample_template.php'), 9));
    return $path;
}
// Минимальная спецификация — общий помощник для тестов сборщика, разбора и схемы
function minimalSpec(?array $steps = null): array {
    return ['bizproc' => 1, 'name' => 'Тест', 'kind' => 'robots',
        'steps' => $steps ?? [['change_stage' => ['TargetStatus' => 'DT1000_10:CLIENT']]]];
}
```

  `tests/run.php` — подключает `support.php`, все `tests/*_test.php`, поддерживает `--filter` и
  `--corpus`, печатает `OK`/`FAIL` на тест и возвращает 1 при падении.

- [ ] **Шаг 3: Первый тест — чтение и запись.** `tests/bptfile_test.php`:

```php
<?php
test('BptFile: чтение фикстуры', function () {
    $bpt = BptFile::read(fixtureBpt());
    assertSame(2, $bpt['data']['VERSION']);
    assertSame('SequentialWorkflowActivity', $bpt['data']['TEMPLATE'][0]['Type']);
});
test('BptFile: JSON туда-обратно без потерь', function () {
    $file = fixtureBpt();
    $bpt = BptFile::read($file);
    assertSame($bpt['serialized'], serialize(BptFile::fromJson(BptFile::toJson($bpt['data']))));
});
test('BptFile: файл с объектом отклоняется', function () {
    $path = tmpPath('.bpt');
    file_put_contents($path, gzcompress('a:2:{s:8:"TEMPLATE";a:1:{i:0;a:1:{s:4:"Type";s:1:"X";}}s:4:"EVIL";O:11:"ArrayObject":0:{}}', 9));
    assertThrows(fn () => BptFile::read($path), 'сериализованный объект', 'объект должен отклоняться');
});
```

- [ ] **Шаг 4: Запусти тесты — они падают.** `php tools/bpt/tests/run.php` → FAIL: класса `BptFile` нет.
- [ ] **Шаг 5: Перенеси код.** Вынеси из `bpt.php` в `src/BptFile.php` функции чтения, записи,
  распаковки, кодировок, JSON и `compactTree` (как методы класса), в `src/Analyzer.php` — анализ.
  В `bpt.php` оставь разбор аргументов, `require_once` файлов `src/` и команды.
- [ ] **Шаг 6: Тесты зелёные.** `php tools/bpt/tests/run.php` → все OK.
- [ ] **Шаг 7: Поведение CLI не изменилось.** Прогони на корпусе:
  `php tools/bpt/bpt.php check <корпус>/*.bpt` → 11 OK; `analyze` даёт тот же отчёт, что и раньше.
- [ ] **Шаг 8: Коммит.** `git add tools/bpt && git commit -m "Утилита .bpt: разбор на классы и каркас тестов"`

---

### Задача 2: Каталог действий

**Файлы:**
- Создать: `tools/bpt/catalog/activities.php`, `tools/bpt/src/Catalog.php`, `tools/bpt/tests/catalog_test.php`
- Изменить: `tools/bpt/bpt.php` (команда `catalog`)

**Интерфейсы:**
```php
final class Catalog {
    public static function load(?string $path = null): self;
    public function resolveType(string $aliasOrType): string;   // 'approve' → 'ApproveActivity'
    public function has(string $type): bool;
    public function entry(string $type): array;                 // alias,title,shape,props,returns,observed
    public function shape(string $type): string;                // root|sequence|branch|leaf|waiting|waiting-branches|ifelse|parallel|loop|block
    public function defaults(string $type): array;              // свойства по умолчанию
    public function propType(string $type, string $prop): ?string;
    public function requiredProps(string $type): array;
    public function returns(string $type): array;
    public function isForbidden(string $type): bool;
    public function types(): array;
    public function normalizeProps(string $type, array $props): array;   // Y/N, числа → строки
    public static function normalizeDefinition(array $def): array;       // Required/Multiple → "1"/"0"
    public function toMarkdown(): string;
    public function toJson(): string;
}
```

- [ ] **Шаг 1: Тесты каталога.** `tests/catalog_test.php`:

```php
<?php
test('Каталог: алиас и тип', function () {
    $c = Catalog::load();
    assertSame('ApproveActivity', $c->resolveType('approve'));
    assertSame('ApproveActivity', $c->resolveType('ApproveActivity'));
    assertThrows(fn () => $c->resolveType('нет_такого'), 'неизвестное действие', 'неизвестный алиас');
});
test('Каталог: формы вложенности', function () {
    $c = Catalog::load();
    assertSame('waiting-branches', $c->shape('ApproveActivity'));
    assertSame('leaf', $c->shape('SetFieldActivity'));
    assertSame('ifelse', $c->shape('IfElseActivity'));
});
test('Каталог: значения по умолчанию и обязательные свойства', function () {
    $c = Catalog::load();
    assertSame([], $c->defaults('SetFieldActivity')['ModifiedBy']);
    assertSame('N', $c->defaults('SetFieldActivity')['MergeMultipleFields']);
    assertSame(['FieldValue'], $c->requiredProps('SetFieldActivity'));
    assertSame(['Comments', 'LastApprover'], $c->returns('ApproveActivity'));
});
test('Каталог: нормализация значений из YAML', function () {
    $c = Catalog::load();
    $props = $c->normalizeProps('Task2Activity', ['HoldToClose' => false, 'Fields' => ['PRIORITY' => 1, 'TASK_CONTROL' => true]]);
    assertSame('N', $props['HoldToClose']);
    assertSame('1', $props['Fields']['PRIORITY']);
    assertSame('Y', $props['Fields']['TASK_CONTROL']);
    assertSame('1', Catalog::normalizeDefinition(['Name' => 'Сумма', 'Type' => 'double', 'Required' => true])['Required']);
});
test('Каталог: PHP-код запрещён', function () {
    assertTrue(Catalog::load()->isForbidden('CodeActivity'), 'CodeActivity должен быть запрещён');
});
```

- [ ] **Шаг 2: Запусти — падают.** Ожидается FAIL: нет класса `Catalog`.
- [ ] **Шаг 3: Заполни каталог.** `catalog/activities.php` — по записи на тип. Состав берётся из
  корпуса (`analyze` и разведка структуры); заголовки по умолчанию — самые частые из корпуса, а где
  в корпусе все заголовки свои, ставим общее название и помечаем `'title_guess' => true`.

| Тип | alias | shape | Свойства (значение по умолчанию; `!` — обязательное) | returns |
|-----|-------|-------|------------------------------------------------------|---------|
| `SequentialWorkflowActivity` | — | root | `Title`, `Permission`=[] | — |
| `SequenceActivity` | — | sequence | `Title` | — |
| `ParallelActivity` | `parallel` | parallel | `Title`='Параллельное выполнение', `EditorComment`='' | — |
| `EmptyBlockActivity` | `block` | block | `Title`='Блок действий', `EditorComment`='' | — |
| `IfElseActivity` | `if` | ifelse | `Title`='Условие', `EditorComment`='' | — |
| `IfElseBranchActivity` | — | branch | `Title`, `EditorComment`='', условия: `fieldcondition`, `propertyvariablecondition`, `mixedcondition`, `truecondition` | — |
| `WhileActivity` | `while` | loop | `Title`='Цикл', `EditorComment`='', `fieldcondition`! | — |
| `SetFieldActivity` | `set_field` | leaf | `FieldValue`(map)!, `ModifiedBy`=[], `MergeMultipleFields`='N', `Title`='Изменение документа', `EditorComment`='' | — |
| `SetVariableActivity` | `set_var` | leaf | `VariableValue`(map)!, `Title`='Изменение переменных', `EditorComment`='' | — |
| `CrmChangeStatusActivity` | `change_stage` | leaf | `TargetStatus`(portal-id)!, `ModifiedBy`=[], `Title`='Сменить стадию', `EditorComment`='' | — |
| `CrmEventAddActivity` | `crm_event` | leaf | `EventType`='INFO', `EventText`(text)!, `EventUser`=[], `Title`='Запись события в crm', `EditorComment`='' | — |
| `CrmTimelineCommentAdd` | `timeline_comment` | leaf | `CommentText`(text)!, `CommentUser`=[], `Title`='Добавить комментарий в элемент', `EditorComment`='' | — |
| `CrmSetObserverField` | `observers` | leaf | `ActionOnObservers`='add', `Observers`(list)!, `Title`='Изменить наблюдателей', `EditorComment`='' | — |
| `CrmGetDynamicInfoActivity` | `get_smart_item` | leaf | `DynamicTypeId`(portal-id)!, `ReturnFields`(list)!, `OnlyDynamicEntities`='Y', `DynamicFilterFields`(map), `DynamicEntityFields`(map), `Title`='Получить информацию об элементе смарт-процесса', `EditorComment`='' | поля из `ReturnFields` |
| `IMNotifyActivity` | `notify` | leaf | `MessageSite`(text)!, `MessageOut`='', `MessageType`='2', `MessageUserFrom`=[], `MessageUserTo`(list)!, `Title`='Уведомление пользователя', `EditorComment`='' | — |
| `ImMessageActivity` | `chat_message` | leaf | `MessageUserFrom`(str), `MessageUserTo`(list)!, `MessageTemplate`='notify', `MessageFields`(map)!, `Title`='Отправить сообщение сотруднику в чат', `EditorComment`='' | — |
| `Task2Activity` | `task` | leaf | `Fields`(map)! (обязательные подключи `TITLE`, `RESPONSIBLE_ID`), `HoldToClose`='N', `AUTO_LINK_TO_CRM_ENTITY`='Y', `AsChildTask`='', `CheckListItems`=[], `TimeEstimateHour`='', `TimeEstimateMin`='', `Title`='Поставить задачу', `EditorComment`='' | — |
| `RobotDelayActivity` | `delay` | leaf | `TimeoutTime`(expr)!, `TimeoutTimeIsLocal`='N', `WriteToLog`='Y', `WaitWorkDayUser`=[], `Title`='Пауза робота', `EditorComment`='' | — |
| `StartWorkflowActivity` | `start_workflow` | leaf | `DocumentId`(expr)!, `TemplateId`(portal-id)!, `UseSubscription`='N', `TemplateParameters`=[], `Title`='Запустить бизнес-процесс', `EditorComment`='' | — |
| `ApproveActivity` | `approve` | waiting-branches | `Users`(list)!, `Name`(text)!, `ApproveType`='all', `ApproveMinPercent`='50', `ApproveWaitForAll`='N', `Description`='', `Parameters`='', `StatusMessage`='', `SetStatusMessage`='Y', `OverdueDate`='', `TimeoutDuration`='', `TimeoutDurationType`='s', `TaskButton1Message`='Утвердить', `TaskButton2Message`='Отклонить', `CommentLabelMessage`='Пояснение', `ShowComment`='Y', `CommentRequired`='N', `AccessControl`='N', `DelegationType`='1', `Title`='Утверждение документа', `EditorComment`='' | `Comments`, `LastApprover` |
| `ReviewActivity` | `review` | waiting | как `ApproveActivity`, но без `ApproveMinPercent`, `ApproveWaitForAll`, `TaskButton1/2Message`; есть `TaskButtonMessage`='Принято', `CommentLabelMessage`='Комментарий', `Title`='Ознакомление' | `Comments`, `LastReviewer` |
| `RequestInformationActivity` | `request_info` | waiting | `Users`(list)!, `Name`(text)!, `RequestedInformation`(list)!, `Description`='', `TaskButtonMessage`='Принято', `ShowComment`='Y', `CommentRequired`='N', `CommentLabelMessage`='Пояснение', `SetStatusMessage`='Y', `StatusMessage`='', `TimeoutDuration`='', `TimeoutDurationType`='s', `AccessControl`='N', `DelegationType`='1', `OverdueDate`='', `Title`='Запрос дополнительной информации', `EditorComment`='' | `Comments`, `InfoUser` |
| `RequestInformationOptionalActivity` | `request_info_optional` | waiting-branches | как `RequestInformationActivity` + `CancelType`='any', `TaskButtonCancelMessage`='Отклонить', `SaveVariables`='N' | `Comments`, `InfoUser` |
| `CodeActivity` | `php_code` | leaf | `forbidden` => 'выполнение PHP-кода (только коробка)' | — |

  Общие свойства всех действий вынеси в `'common'`: `Title`, `EditorComment`, `_DesMinimized`
  (состояние дизайнера: принимается при разборе, при сборке не пишется).

- [ ] **Шаг 4: Реализуй `Catalog`.** Загрузка файла, построение карты алиасов, `normalizeProps`
  по типам свойств (`yn` → `Y`/`N`; числа → строки; `map`/`list` — рекурсивно; `null` сохраняется),
  `normalizeDefinition` (`Required`, `Multiple` → `"1"`/`"0"`).
- [ ] **Шаг 5: Тесты зелёные.** `php tools/bpt/tests/run.php --filter Каталог`
- [ ] **Шаг 6: Команда `catalog`.** `bpt.php catalog [Тип] [--json]` — таблица или JSON. Проверь:
  `php tools/bpt/bpt.php catalog | head -20` и `php tools/bpt/bpt.php catalog Task2Activity`.
- [ ] **Шаг 7: Сверка с корпусом.** Временный тест: для каждого файла корпуса все типы и свойства
  есть в каталоге. Запусти `php tools/bpt/tests/run.php --corpus <папка>`; недостающее — внеси в каталог.
- [ ] **Шаг 8: Коммит.** `git commit -m "Каталог действий БП: 23 типа, формы вложенности, нормализация"`

---

### Задача 3: Чтение спецификаций

**Файлы:** создать `tools/bpt/src/SpecReader.php`, `tools/bpt/tests/specreader_test.php`

**Интерфейсы:**
```php
final class SpecReader {
    public static function read(string $path): array;        // по расширению и содержимому
    public static function parse(string $text, string $format): array;  // 'yaml'|'json'
    public static function dump(array $spec): string;        // YAML при наличии ext-yaml, иначе JSON
    public static function hasYaml(): bool;
}
```

- [ ] **Шаг 1: Тесты.**

```php
<?php
test('SpecReader: YAML и JSON дают одинаковую структуру', function () {
    if (!SpecReader::hasYaml()) return;  // без ext-yaml проверяем только JSON
    $yaml = "bizproc: 1\nname: Тест\nsteps:\n  - change_stage: {TargetStatus: \"{{stage:Клиент}}\"}\n";
    $json = '{"bizproc":1,"name":"Тест","steps":[{"change_stage":{"TargetStatus":"{{stage:Клиент}}"}}]}';
    assertSame(SpecReader::parse($json, 'json'), SpecReader::parse($yaml, 'yaml'));
});
test('SpecReader: понятная ошибка на битом YAML', function () {
    if (!SpecReader::hasYaml()) return;
    assertThrows(fn () => SpecReader::parse("steps:\n  - [не закрыт\n", 'yaml'), 'не удалось разобрать', 'битый YAML');
});
```

- [ ] **Шаг 2: Запусти — падают.**
- [ ] **Шаг 3: Реализуй.** `yaml_parse` при наличии расширения; иначе для `.yaml` — ошибка с
  подсказкой «переведите спецификацию в JSON или включите расширение yaml». `dump` — `yaml_emit`
  с `YAML_UTF8_ENCODING`, иначе `BptFile::toJson`.
- [ ] **Шаг 4: Тесты зелёные.**
- [ ] **Шаг 5: Коммит.** `git commit -m "Чтение спецификаций БП: YAML и JSON"`

---

### Задача 4: Сборщик — простые шаги

**Файлы:** создать `tools/bpt/src/Compiler.php`, `tools/bpt/tests/compile_test.php`;
изменить `tools/bpt/bpt.php` (команда `compile`)

**Интерфейсы:**
```php
final class Compiler {
    public function __construct(Catalog $catalog, ?Snapshot $snapshot = null, bool $strict = false);
    public function compile(array $spec): array;   // ['bpt' => array, 'errors' => string[], 'warnings' => string[]]
    public static function activityName(string $seed): string;  // детерминированное «A…»
}
```

- [ ] **Шаг 1: Тесты.**

```php
<?php // minimalSpec() определён в tests/support.php (задача 1)
test('Сборщик: один шаг превращается в дерево', function () {
    $r = (new Compiler(Catalog::load()))->compile(minimalSpec());
    assertSame([], $r['errors']);
    $root = $r['bpt']['TEMPLATE'][0];
    assertSame('SequentialWorkflowActivity', $root['Type']);
    assertSame('Bizproc Automation template', $root['Properties']['Title']);
    $step = $root['Children'][0];
    assertSame('CrmChangeStatusActivity', $step['Type']);
    assertSame('DT1000_10:CLIENT', $step['Properties']['TargetStatus']);
    assertSame([], $step['Properties']['ModifiedBy']);              // значение по умолчанию
    assertSame('Сменить стадию', $step['Properties']['Title']);     // заголовок по умолчанию
    assertSame('Y', $step['Activated']);
});
test('Сборщик: имена стабильны между прогонами', function () {
    $a = (new Compiler(Catalog::load()))->compile(minimalSpec());
    $b = (new Compiler(Catalog::load()))->compile(minimalSpec());
    assertSame($a['bpt'], $b['bpt']);
    assertTrue((bool) preg_match('/^A\d+_\d+_\d+_\d+$/', $a['bpt']['TEMPLATE'][0]['Children'][0]['Name']), 'формат имени');
});
test('Сборщик: служебные ключи', function () {
    $r = (new Compiler(Catalog::load()))->compile(minimalSpec([[
        'change_stage' => ['id' => 'st', 'title' => 'Свой заголовок', 'off' => true,
                           'comment' => 'заметка', 'TargetStatus' => 'DT1000_10:CLIENT'],
    ]]));
    $step = $r['bpt']['TEMPLATE'][0]['Children'][0];
    assertSame('Свой заголовок', $step['Properties']['Title']);
    assertSame('заметка', $step['Properties']['EditorComment']);
    assertSame('N', $step['Activated']);
});
test('Сборщик: неизвестный тип и свойство — ошибки', function () {
    $c = new Compiler(Catalog::load());
    assertTrue((bool) $c->compile(minimalSpec([['нет_такого' => []]]))['errors'], 'неизвестный тип');
    $r = $c->compile(minimalSpec([['change_stage' => ['TargetStatus' => 'X', 'Выдумка' => 1]]]));
    assertTrue(str_contains(implode(' ', $r['errors']), 'Выдумка'), 'неизвестное свойство');
});
test('Сборщик: нет обязательного свойства', function () {
    $r = (new Compiler(Catalog::load()))->compile(minimalSpec([['change_stage' => []]]));
    assertTrue(str_contains(implode(' ', $r['errors']), 'TargetStatus'), 'обязательное свойство');
});
```

- [ ] **Шаг 2: Запусти — падают.**
- [ ] **Шаг 3: Реализуй сборку листьев:** корень по `kind` (`robots` → заголовок
  `Bizproc Automation template`, ветки `Automation sequence`; `designer` → заголовок из `name`,
  ветки «Последовательность действий»), разбор шага (алиас, служебные ключи, свойства), значения по
  умолчанию, нормализация через `Catalog`, детерминированное имя от `md5(имя процесса + путь + id)`,
  сборка `VERSION`/`PARAMETERS`/`VARIABLES`/`CONSTANTS`, `DOCUMENT_FIELDS` = `[]` с предупреждением.
- [ ] **Шаг 4: Тесты зелёные.**
- [ ] **Шаг 5: Команда `compile`.** `compile <spec> -o <out.bpt> [--force]`; при ошибках файл не
  пишется, они печатаются с путём до шага, код выхода 1.
- [ ] **Шаг 6: Коммит.** `git commit -m "Сборщик БП: простые шаги, значения по умолчанию, имена"`

---

### Задача 5: Сборщик — структура и ветки

**Файлы:** изменить `tools/bpt/src/Compiler.php`, `tools/bpt/tests/compile_test.php`

- [ ] **Шаг 1: Тесты.**

```php
<?php
test('Сборщик: условие с ветками', function () {
    $r = (new Compiler(Catalog::load()))->compile(minimalSpec([[ 'if' => ['branches' => [
        ['title' => 'Да', 'when' => ['fieldcondition' => [['UF_X', '!empty', '', '0']]],
         'steps' => [['change_stage' => ['TargetStatus' => 'DT1000_10:CLIENT']]]],
        ['title' => 'Иначе', 'else' => true, 'steps' => []],
    ]]]]));
    assertSame([], $r['errors']);
    $if = $r['bpt']['TEMPLATE'][0]['Children'][0];
    assertSame('IfElseActivity', $if['Type']);
    assertSame('IfElseBranchActivity', $if['Children'][0]['Type']);
    assertSame([['UF_X', '!empty', '', '0']], $if['Children'][0]['Properties']['fieldcondition']);
    assertSame('1', $if['Children'][1]['Properties']['truecondition']);
    assertSame('CrmChangeStatusActivity', $if['Children'][0]['Children'][0]['Type']);  // без обёртки
});
test('Сборщик: параллель, цикл и блок оборачиваются в последовательности', function () {
    $c = new Compiler(Catalog::load());
    $step = [['change_stage' => ['TargetStatus' => 'DT1000_10:CLIENT']]];
    $r = $c->compile(minimalSpec([
        ['parallel' => ['branches' => [$step, $step]]],
        ['while' => ['when' => ['fieldcondition' => [['UF_X', 'empty', '', '0']]], 'steps' => $step]],
        ['block' => ['title' => 'Блок', 'steps' => $step]],
    ]));
    assertSame([], $r['errors']);
    [$par, $loop, $block] = $r['bpt']['TEMPLATE'][0]['Children'];
    assertSame('SequenceActivity', $par['Children'][0]['Type']);
    assertSame(2, count($par['Children']));
    assertSame('SequenceActivity', $loop['Children'][0]['Type']);
    assertSame([['UF_X', 'empty', '', '0']], $loop['Properties']['fieldcondition']);
    assertSame('SequenceActivity', $block['Children'][0]['Type']);
    assertSame('Блок', $block['Properties']['Title']);
});
test('Сборщик: действие с двумя исходами', function () {
    $r = (new Compiler(Catalog::load()))->compile(minimalSpec([[ 'approve' => [
        'id' => 'ap', 'Users' => ['user_42'], 'Name' => 'Согласование',
        'on_yes' => [['change_stage' => ['TargetStatus' => 'DT1000_10:CLIENT']]],
        'on_no' => [],
    ]]]));
    assertSame([], $r['errors']);
    $ap = $r['bpt']['TEMPLATE'][0]['Children'][0];
    assertSame(2, count($ap['Children']));
    assertSame('SequenceActivity', $ap['Children'][0]['Type']);
    assertSame('CrmChangeStatusActivity', $ap['Children'][0]['Children'][0]['Type']);
    assertSame([], $ap['Children'][1]['Children']);
});
test('Сборщик: ошибки вложенности', function () {
    $c = new Compiler(Catalog::load());
    $bad = [
        [['if' => ['branches' => [['steps' => []]]]], 'минимум две ветки'],
        [['while' => ['steps' => []]], 'условие'],
        [['change_stage' => ['TargetStatus' => 'X', 'on_yes' => []]], 'исход'],
    ];
    foreach ($bad as [$step, $needle]) {
        $errors = implode(' ', $c->compile(minimalSpec([$step]))['errors']);
        assertTrue(str_contains($errors, $needle), "ожидали «$needle», получили: $errors");
    }
});
```

- [ ] **Шаг 2: Запусти — падают.**
- [ ] **Шаг 3: Реализуй** ветвление по `shape` из каталога: `ifelse` (ветки без обёртки),
  `parallel`, `loop`, `block`, `waiting-branches` (ровно две последовательности), рекурсивная сборка
  `steps`, проверки вложенности с путями до шага.
- [ ] **Шаг 4: Тесты зелёные.**
- [ ] **Шаг 5: Коммит.** `git commit -m "Сборщик БП: условия, параллель, цикл, блок, исходы"`

---

### Задача 6: Ссылки между шагами и проверки после сборки

**Файлы:** изменить `tools/bpt/src/Compiler.php`, `tools/bpt/tests/compile_test.php`

- [ ] **Шаг 1: Тесты.**

```php
<?php
test('Сборщик: ссылка на результат шага', function () {
    $r = (new Compiler(Catalog::load()))->compile(minimalSpec([
        ['approve' => ['id' => 'ap', 'Users' => ['user_42'], 'Name' => 'Согласование', 'on_yes' => [], 'on_no' => []]],
        ['crm_event' => ['EventType' => 'INFO', 'EventText' => 'Комментарий: {=@ap:Comments}']],
    ]));
    assertSame([], $r['errors']);
    $children = $r['bpt']['TEMPLATE'][0]['Children'];
    $name = $children[0]['Name'];
    assertSame("Комментарий: {={$name}:Comments}", $children[1]['Properties']['EventText']);
});
test('Сборщик: битая ссылка и повтор id — ошибки', function () {
    $c = new Compiler(Catalog::load());
    $e1 = implode(' ', $c->compile(minimalSpec([['crm_event' => ['EventText' => '{=@нет:Comments}']]]))['errors']);
    assertTrue(str_contains($e1, 'нет'), 'ссылка на несуществующий шаг');
    $dup = [['change_stage' => ['id' => 'x', 'TargetStatus' => 'A']], ['change_stage' => ['id' => 'x', 'TargetStatus' => 'B']]];
    assertTrue(str_contains(implode(' ', $c->compile(minimalSpec($dup))['errors']), 'повтор'), 'повтор id');
});
test('Сборщик: неизвестный результат — предупреждение', function () {
    $r = (new Compiler(Catalog::load()))->compile(minimalSpec([
        ['approve' => ['id' => 'ap', 'Users' => ['user_42'], 'Name' => 'С', 'on_yes' => [], 'on_no' => []]],
        ['crm_event' => ['EventText' => '{=@ap:Выдумка}']],
    ]));
    assertSame([], $r['errors']);
    assertTrue(str_contains(implode(' ', $r['warnings']), 'Выдумка'), 'предупреждение о результате');
});
test('Сборщик: необъявленная переменная ловится анализатором', function () {
    $r = (new Compiler(Catalog::load()))->compile(minimalSpec([['crm_event' => ['EventText' => '{=Variable:призрак}']]]));
    assertTrue(str_contains(implode(' ', $r['errors']), 'призрак'), 'необъявленная переменная');
});
```

- [ ] **Шаг 2: Запусти — падают.**
- [ ] **Шаг 3: Реализуй:** сбор `id` → сгенерированное имя за первый проход, замена `{=@id:X}` во
  всех строках за второй, проверка результата по `returns` каталога (для `get_smart_item` — по
  `ReturnFields`), прогон `Analyzer` по собранному дереву и слияние его ошибок и предупреждений.
- [ ] **Шаг 4: Тесты зелёные.**
- [ ] **Шаг 5: Коммит.** `git commit -m "Сборщик БП: ссылки между шагами и проверки"`

---

### Задача 7: Снимок портала и плейсхолдеры

**Файлы:**
- Создать: `tools/bpt/src/Snapshot.php`, `tools/bpt/tests/snapshot_test.php`,
  `tools/bpt/examples/example.portal.yaml`
- Изменить: `tools/bpt/bpt.php` (команда `snapshot`, ключ `--portal`), `.gitignore`

**Интерфейсы:**
```php
final class Snapshot {
    public static function load(string $path): self;
    public static function fromBpt(array $bpt): self;
    public function toArray(): array;
    public function resolve(string $kind, string $name): string;          // не найдено/неоднозначно → BptException
    public function substitute(mixed $value, string $path, array &$errors, array &$warnings): mixed;
    public function nameFor(string $kind, string $value): ?string;        // обратный поиск для разбора
    public function documentFields(): array;
}
```

- [ ] **Шаг 1: Тесты.**

```php
<?php
test('Снимок: извлечение полей и стадий из .bpt', function () {
    $s = Snapshot::fromBpt(BptFile::read(fixtureBpt())['data']);
    assertSame('DT1000_10:CLIENT', $s->resolve('stage', 'Общая/Клиент'));
    assertSame('DT1000_10:CLIENT', $s->resolve('stage', 'Клиент'));      // краткое название, если однозначно
    assertSame('TITLE', $s->resolve('field', 'Название'));
    assertTrue($s->documentFields() !== [], 'DOCUMENT_FIELDS сохраняются');
});
test('Снимок: подстановка в значениях и ключах', function () {
    $s = Snapshot::load(__DIR__ . '/../examples/example.portal.yaml');
    $errors = $warnings = [];
    $out = $s->substitute(['FieldValue' => ['{{field:Сумма к оплате}}' => 'x'], 'To' => ['{{group:Бухгалтерия}}']], 'steps[0]', $errors, $warnings);
    assertSame([], $errors);
    assertSame(['UF_CRM_7_1700000000001' => 'x'], $out['FieldValue']);
    assertSame(['group_g7'], $out['To']);
});
test('Снимок: понятные ошибки', function () {
    $s = Snapshot::load(__DIR__ . '/../examples/example.portal.yaml');
    $errors = $warnings = [];
    $s->substitute('{{field:Нет такого}}', 'steps[1]', $errors, $warnings);
    assertTrue(str_contains(implode(' ', $errors), 'Нет такого'), 'отсутствующее название');
    assertTrue(str_contains(implode(' ', $errors), 'steps[1]'), 'путь до шага в ошибке');
});
test('Снимок: сырой ID даёт предупреждение', function () {
    $errors = $warnings = [];
    Snapshot::load(__DIR__ . '/../examples/example.portal.yaml')->substitute('user_42', 'steps[0]', $errors, $warnings);
    assertTrue(str_contains(implode(' ', $warnings), 'user_42'), 'предупреждение о сыром ID');
});
```

- [ ] **Шаг 2: Запусти — падают.**
- [ ] **Шаг 3: Пример снимка.** `examples/example.portal.yaml` — синтетические значения из §5
  DESIGN.md. В `.gitignore` добавь `*.portal.yaml`, `*.portal.json` и исключения
  `!tools/bpt/examples/*.portal.yaml`.
- [ ] **Шаг 4: Реализуй `Snapshot`** и подключи в `Compiler`: подстановка перед проверками,
  `--strict` превращает предупреждение о сыром ID в ошибку, `DOCUMENT_FIELDS` берётся из снимка.
- [ ] **Шаг 5: Тесты зелёные.**
- [ ] **Шаг 6: Команда `snapshot`** и ключ `--portal` у `compile`. Проверь на фикстуре:
  `php tools/bpt/bpt.php snapshot <фикстура.bpt>`.
- [ ] **Шаг 7: Коммит.** `git commit -m "Снимок портала и плейсхолдеры в спецификациях"`

---

### Задача 8: Разбор `.bpt` в спецификацию

**Файлы:** создать `tools/bpt/src/Decompiler.php`, `tools/bpt/tests/decompile_test.php`;
изменить `tools/bpt/bpt.php` (команда `decompile`)

**Интерфейсы:**
```php
final class Decompiler {
    public function __construct(Catalog $catalog, ?Snapshot $snapshot = null, bool $keepNames = false);
    public function decompile(array $bpt): array;   // ['spec' => array, 'warnings' => string[]]
}
```

- [ ] **Шаг 1: Тесты.**

```php
<?php
test('Разбор: собранное разбирается обратно и собирается так же', function () {
    $spec = minimalSpec([
        ['approve' => ['id' => 'ap', 'Users' => ['user_42'], 'Name' => 'Согласование',
                       'on_yes' => [['crm_event' => ['EventText' => 'Готово: {=@ap:Comments}']]], 'on_no' => []]],
    ]);
    $c = new Compiler(Catalog::load());
    $first = $c->compile($spec);
    assertSame([], $first['errors']);
    $back = (new Decompiler(Catalog::load()))->decompile($first['bpt']);
    $second = $c->compile($back['spec']);
    assertSame([], $second['errors']);
    assertSame($first['bpt']['TEMPLATE'], $second['bpt']['TEMPLATE']);
});
test('Разбор: значения по умолчанию опускаются', function () {
    $r = (new Decompiler(Catalog::load()))->decompile(BptFile::read(fixtureBpt())['data']);
    $step = $r['spec']['steps'][0]['parallel']['branches'][0][0] ?? $r['spec']['steps'][0];
    assertTrue(!isset($step['change_stage']['ModifiedBy']), 'ModifiedBy по умолчанию не пишется');
});
test('Разбор: висячая ссылка сохраняется и предупреждает', function () {
    $data = require __DIR__ . '/fixtures/sample_template.php';
    $data['TEMPLATE'][0]['Children'][0]['Children'][0]['Properties']['Title'] = 'Ссылка {=A9_9_9_9:Comments}';
    $r = (new Decompiler(Catalog::load()))->decompile($data);
    assertTrue(str_contains(implode(' ', $r['warnings']), 'A9_9_9_9'), 'предупреждение о висячей ссылке');
});
```

- [ ] **Шаг 2: Запусти — падают.**
- [ ] **Шаг 3: Реализуй** обход дерева по формам каталога, читаемые `id` для шагов, на которые есть
  ссылки, перезапись `{=A…:X}` → `{=@id:X}`, пропуск значений по умолчанию, `--keep-names`,
  `raw` для неизвестных свойств, подстановку названий из снимка.
- [ ] **Шаг 4: Тесты зелёные.**
- [ ] **Шаг 5: Команда `decompile`** с ключами `--portal`, `--keep-names`, `--json`.
- [ ] **Шаг 6: Коммит.** `git commit -m "Разбор .bpt в спецификацию"`

---

### Задача 9: Схема процесса

**Файлы:** создать `tools/bpt/src/Mermaid.php`, `tools/bpt/tests/mermaid_test.php`;
изменить `tools/bpt/bpt.php` (команда `render`)

**Интерфейсы:**
```php
final class Mermaid {
    public function __construct(Catalog $catalog);
    public function render(array $bpt): string;   // markdown с блоком mermaid
}
```

- [ ] **Шаг 1: Тесты.**

```php
<?php
test('Схема: узлы, ветки и отключённые шаги', function () {
    $r = (new Compiler(Catalog::load()))->compile(minimalSpec([
        ['approve' => ['id' => 'ap', 'Users' => ['user_42'], 'Name' => 'Согласование',
                       'on_yes' => [['change_stage' => ['TargetStatus' => 'DT1000_10:CLIENT']]], 'on_no' => []]],
        ['change_stage' => ['off' => true, 'TargetStatus' => 'DT1000_10:NEW']],
    ]));
    $mmd = (new Mermaid(Catalog::load()))->render($r['bpt']);
    assertTrue(str_contains($mmd, '```mermaid'), 'блок mermaid');
    assertTrue(str_contains($mmd, 'flowchart TD'), 'тип схемы');
    assertTrue(str_contains($mmd, 'Согласование'), 'заголовок действия');
    assertTrue(str_contains($mmd, 'Сменить стадию'), 'узел смены стадии');
    assertTrue(str_contains($mmd, 'off'), 'класс для отключённого шага');
});
test('Схема: кавычки в заголовках не ломают разметку', function () {
    $r = (new Compiler(Catalog::load()))->compile(minimalSpec([['change_stage' => ['title' => 'Стадия "Клиент"', 'TargetStatus' => 'X']]]));
    $mmd = (new Mermaid(Catalog::load()))->render($r['bpt']);
    assertTrue(!str_contains($mmd, '"Клиент"'), 'кавычки экранированы');
});
```

- [ ] **Шаг 2: Запусти — падают.**
- [ ] **Шаг 3: Реализуй** обход дерева, формы узлов по `shape`, подписи веток, развилку и слияние
  для параллели, обратную стрелку для цикла, подграф для блока, класс `off` и обрезку длинных строк.
- [ ] **Шаг 4: Тесты зелёные.**
- [ ] **Шаг 5: Команда `render`** для `.bpt` и спецификации (спецификация собирается в режиме
  черновика: незаполненные плейсхолдеры допускаются).
- [ ] **Шаг 6: Коммит.** `git commit -m "Схема процесса в Mermaid"`

---

### Задача 10: Прогон на корпусе

**Файлы:** создать `tools/bpt/tests/corpus_test.php`; изменить каталог и классы по результатам

- [ ] **Шаг 1: Тест на корпусе.**

```php
<?php
test('Корпус: каталог покрывает типы и свойства', function () {
    foreach (corpusFiles() as $file) {                 // пусто, если --corpus не задан → тест пропускается
        $data = BptFile::read($file)['data'];
        foreach (allActivities($data['TEMPLATE'][0]) as $node) {
            assertTrue(Catalog::load()->has($node['Type']), "{$file}: типа {$node['Type']} нет в каталоге");
            foreach (array_keys($node['Properties'] ?? []) as $prop) {
                assertTrue(Catalog::load()->propType($node['Type'], $prop) !== null,
                    "{$file}: свойства {$node['Type']}.{$prop} нет в каталоге");
            }
        }
    }
});
test('Корпус: разбор и сборка совпадают по смыслу', function () {
    $catalog = Catalog::load();
    foreach (corpusFiles() as $file) {
        $original = BptFile::read($file)['data'];
        $spec = (new Decompiler($catalog, null, true))->decompile($original)['spec'];
        $built = (new Compiler($catalog))->compile($spec);
        assertSame([], $built['errors'], basename($file));
        assertSame(canonicalTree($original, $catalog), canonicalTree($built['bpt'], $catalog), basename($file));
    }
});
test('Корпус: снимок, плейсхолдеры и схема', function () {
    $catalog = Catalog::load();
    foreach (corpusFiles() as $file) {
        $original = BptFile::read($file)['data'];
        $snapshot = Snapshot::fromBpt($original);
        $spec = (new Decompiler($catalog, $snapshot, true))->decompile($original)['spec'];
        $built = (new Compiler($catalog, $snapshot))->compile($spec);
        assertSame([], $built['errors'], basename($file));
        assertSame(canonicalTree($original, $catalog), canonicalTree($built['bpt'], $catalog), basename($file));
        assertTrue(str_contains((new Mermaid($catalog))->render($original), 'flowchart TD'), basename($file));
    }
});
```

  В `tests/support.php` добавь `corpusFiles()` (из `--corpus`), `allActivities()` (обход дерева) и
  `canonicalTree()` — приведение к сравнимому виду: заполнить значения по умолчанию, убрать `Node`,
  привести `Activated` к `Y`, отсортировать ключи отображений, имена действий заменить на позиции
  (`#0.1.0`), ссылки `{=A…:X}` — на `{=#позиция:X}`.
- [ ] **Шаг 2: Запусти на корпусе.** `php tools/bpt/tests/run.php --corpus <папка>` — ожидаются
  падения: недостающие свойства, не совпавшие значения по умолчанию, особенности форм.
- [ ] **Шаг 3: Правь каталог и классы**, пока все 11 файлов не пройдут. Каждое расхождение —
  строка в каталоге или правило в сборщике, а не «заплатка» в тесте.
- [ ] **Шаг 4: Синтетика тоже зелёная.** `php tools/bpt/tests/run.php`
- [ ] **Шаг 5: Коммит.** `git commit -m "Сверка сборщика и каталога на корпусе шаблонов"`

---

### Задача 11: Пример, документация и вики

**Файлы:**
- Создать: `tools/bpt/examples/invoice-approval.bizproc.yaml`, `tools/bpt/SPEC.md`,
  `wiki/modules/bizproc/concept-bizproc-activity-catalog.md`
- Изменить: `tools/bpt/README.md`, `tools/bpt/src/Analyzer.php`,
  `wiki/modules/bizproc/concept-bizproc-bpt-format.md`,
  `wiki/modules/bizproc/pattern-bizproc-ai-assisted-generation.md`,
  `wiki/modules/bizproc/_index-bizproc.md`, `index.md`, `log.md`; удалить `tools/bpt/PLAN.md`

- [ ] **Шаг 1: Пример спецификации.** `examples/invoice-approval.bizproc.yaml` — «Согласование
  счёта» из §3 DESIGN.md на синтетических данных, вместе с `example.portal.yaml`.
- [ ] **Шаг 2: Тест примера.** В `tests/compile_test.php`: пример собирается без ошибок,
  разбирается обратно и рисуется схемой.
- [ ] **Шаг 3: Запусти тесты** — зелёные.
- [ ] **Шаг 4: Привязки к порталу в `analyze`.** Добавь свойства с типом `portal-id`
  (`TemplateId`, `DynamicTypeId`) в раздел «Привязки к порталу». Проверь на корпусе: у файла со
  «Запустить бизнес-процесс» появляются ID шаблонов.
- [ ] **Шаг 5: `SPEC.md`** — формат спецификации: шаги, служебные ключи, структурные шаги, условия,
  выражения, плейсхолдеры, нормализация, пример целиком, частые ошибки.
- [ ] **Шаг 6: `README.md`** — новые команды, ограничения, ссылка на `SPEC.md` и каталог.
- [ ] **Шаг 7: Страница каталога в вики.** `concept-bizproc-activity-catalog.md`: frontmatter по
  `CLAUDE.md` §4.2 (`type: concept`, `module: bizproc`, `edition: both`, `status: verified`,
  `verified: "<дата> / корпус 11 шаблонов (cloud)"`), таблица из `bpt.php catalog`, пометки о
  непроверенных заголовках по умолчанию, ссылки на формат и паттерн.
- [ ] **Шаг 8: Перелинковка.** Добавь страницу в хаб `_index-bizproc.md` и `index.md`
  (разделы «Концепты» и «По статусу»), поставь ссылки из
  `concept-bizproc-bpt-format` и `pattern-bizproc-ai-assisted-generation` (шаги 2–3 реализованы;
  статус паттерна остаётся `draft` до пилота на портале), обнови `updated`.
- [ ] **Шаг 9: Проверка ссылок.** Прогони проверку вики (битые `[[ссылки]]`, орфаны, frontmatter) —
  без замечаний по своим страницам.
- [ ] **Шаг 10: Журнал и уборка.** Запись в `log.md` (что создано, что проверено, открытые
  вопросы), удали `tools/bpt/PLAN.md`.
- [ ] **Шаг 11: Коммит.** `git commit -m "Спецификации БП: пример, документация и каталог в вики"`

---

## Проверка готовности (§12 DESIGN.md)

- [ ] `php tools/bpt/tests/run.php` — все тесты зелёные.
- [ ] `php tools/bpt/tests/run.php --corpus <папка>` — 11 из 11 файлов проходят сверку.
- [ ] `php tools/bpt/bpt.php compile tools/bpt/examples/invoice-approval.bizproc.yaml --portal tools/bpt/examples/example.portal.yaml -o /tmp/x.bpt` — файл собран.
- [ ] `php tools/bpt/bpt.php render /tmp/x.bpt` — схема выводится.
- [ ] `php tools/bpt/bpt.php catalog` — таблица 23 типов.
- [ ] Вики и `log.md` обновлены, всё закоммичено.
