# Оценка навыка «БП по ТЗ» — план реализации

> **Для исполнителя-агента:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development (рекомендуется)
> или superpowers:executing-plans — задача за задачей. Шаги отмечаются чекбоксами (`- [ ]`).

**Цель:** утилита `tools/bpt-eval`, которая прогоняет навык `building-bizproc-templates` на типовых
задачах и по сценариям на тестовом стенде даёт решение «порог 80% достигнут или нет» и список слабых мест.

**Архитектура:** задачи — данные (`tz.md`, `acceptance.yaml`, эталон). Хост (`eval.php`, классы `src/`)
читает и проверяет форматы, собирает `.bpt` существующим `tools/bpt`, отправляет PHP-скрипты `stand/`
в контейнер стенда (`docker compose exec`), складывает результаты и строит отчёт. Сценарии выполняет
один прогонщик `stand/runner.php` через API Битрикса. Агентов запускает оркестратор (сессия Claude Code)
по `AGENT_PROMPT.md`.

**Технологии:** PHP ≥ 8.1 на хосте (сейчас 8.5), PHP 8.2 в контейнере стенда, ext-yaml, Docker Compose,
`tools/bpt` (Compiler, Snapshot, SpecReader, BptFile, Catalog).

**Спецификация:** [DESIGN.md](DESIGN.md) — план выводится из неё; при расхождении прав дизайн.

## Общие ограничения

- Скрипты стенда работают на **PHP 8.2** (контейнер `php` образа `quay.io/bitrix24/php:8.2.33`): без
  возможностей PHP 8.3+ (типизированные константы классов, `json_validate`, `#[\Override]`).
- Скрипты стенда выполняются **от пользователя `bitrix`** (`--user=bitrix`), не от root
  ([антипаттерн](../../wiki/development/server-admin/antipattern-cli-php-as-root.md)).
- Скрипт стенда уходит в контейнер через stdin одним потоком вместе с `_bootstrap.php`: **без импортов
  `use Bitrix\…`, без `declare`, без `namespace`**, только полные имена классов (`use (…)` у замыканий можно);
  первая строка — `<?php`, закрывающего `?>` нет.
- В stdout скрипт стенда печатает **только JSON** (`eval_out()`); диагностика — в stderr.
- Папка стенда — `BPT_EVAL_STAND`, по умолчанию `C:/docker/b24-test`.
- Шаблоны задач на стенде называются `EVAL <прогон> <задача>`, элементы — `EVAL <задача>: <сценарий>`.
- Сценарий проверяет только то, что ТЗ говорит **однозначно**; остальное — в чек-лист (DESIGN.md §5).
- В git — только обезличенные задачи; ID стенда, снимки и результаты прогонов — в `work/eval/` (вне git).
- Секреты и персданные — никогда (CLAUDE.md §4.4). Администратор стенда (#1) в снимок не попадает.
- Тексты и сообщения — по-русски; стиль кода — как в `tools/bpt` (`declare(strict_types=1)` в файлах
  хоста, `final class`, ошибки — исключением с понятным текстом и путём).
- После каждой задачи: `php tools/bpt-eval/tests/run.php` и
  `php tools/bpt/tests/run.php --corpus=C:/Users/d.kairov/Downloads` — зелёные; коммит по-русски с
  `Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>`; без push.

---

## Структура файлов

```
tools/bpt-eval/
  eval.php                      CLI хоста: prepare, reference, check, usage, report
  src/EvalException.php         ошибка утилиты
  src/Acceptance.php            чтение и проверка acceptance.yaml
  src/Roles.php                 роли из roles.yaml
  src/ConstantMatcher.php       константы-пользователи шаблона → роли
  src/Stand.php                 запуск скриптов stand/ в контейнере, JSON туда и обратно
  src/CheckRunner.php           одна задача: сборка → импорт → константы → сценарии → уборка
  src/Report.php                сводка прогона → report.md
  stand/_bootstrap.php          общая часть скриптов стенда (ядро, помощники)
  stand/setup.php               подготовка стенда
  stand/snapshot.php            данные для снимка портала
  stand/import.php              импорт шаблона
  stand/runner.php              сценарии
  stand/cleanup.php             уборка
  tasks/T01-summa/…, tasks/T04-parallel/…, tasks/T10-email/… (пилот), остальные — задача 10
  roles.yaml                    роли по именам
  AGENT_PROMPT.md, CHECKLIST_PROMPT.md
  tests/run.php, tests/*_test.php
  DESIGN.md, PLAN.md, README.md
tools/bpt/src/Snapshot.php      + раздел related: поля связанных смарт-процессов (задача 4)
tools/bpt/src/Compiler.php      + DynamicEntityFields у get_smart_item по снимку (задача 4)
```

---

### Задача 1: Каркас и формат проверок (`Acceptance`)

**Файлы:**
- Создать: `tools/bpt-eval/src/EvalException.php`, `tools/bpt-eval/src/Acceptance.php`,
  `tools/bpt-eval/tests/run.php`, `tools/bpt-eval/tests/acceptance_test.php`

**Интерфейсы:**
- Использует: `SpecReader::read(string $file): array` из `tools/bpt/src/SpecReader.php`; помощники тестов
  `test()`, `assertSame()`, `assertTrue()`, `assertThrows()`, `tmpPath()` из `tools/bpt/tests/support.php`.
- Даёт: `Acceptance::load(string $file): Acceptance`, `Acceptance::fromArray(array $data, string $source): Acceptance`,
  `task(): string`, `document(): string`, `start(): string` (`create`|`manual`), `parameters(): array`,
  `item(): array`, `scenarios(): array` (нормализованные: `name`, `steps[]` с ключами `task_for`, `do`,
  `comment`, `values`, `title_contains`, `optional`; `expect`), `checklist(): array` (`[{text, required}]`),
  `roleNames(): string[]`, `toArray(): array`.

- [ ] **Шаг 1: написать падающий тест** — `tools/bpt-eval/tests/acceptance_test.php`

```php
<?php
/** Тесты формата проверок acceptance.yaml. */

declare(strict_types=1);

function acceptanceData(): array
{
    return [
        'task' => 'T04', 'document' => 'Заявки',
        'item' => ['Название' => 'Заявка T04', 'Проект' => 'Проект Альфа'],
        'scenarios' => [[
            'name' => 'юрист отказал',
            'steps' => [
                ['task_for' => 'Юристы', 'do' => 'reject', 'comment' => 'Нет доверенности'],
                ['task_for' => 'Бухгалтер проекта', 'do' => 'approve', 'optional' => true],
            ],
            'expect' => ['stage' => 'Доработка', 'notify' => [['to' => 'Инициатор', 'contains' => 'доверенност']]],
        ]],
        'checklist' => ['Ждать ли второго согласующего', ['text' => 'Срок', 'required' => false]],
    ];
}

test('Проверки: разбор и значения по умолчанию', function () {
    $a = Acceptance::fromArray(acceptanceData(), 'T04');
    assertSame('create', $a->start());
    assertSame([], $a->parameters());
    assertSame([], $a->scenarios()[0]['item']);   // поля элемента сценария — только поверх item задачи
    $data = acceptanceData();
    $data['scenarios'][0]['item'] = ['Сумма к оплате' => 500000];
    assertSame(['Сумма к оплате' => 500000], Acceptance::fromArray($data, 'T02')->scenarios()[0]['item']);
    $step = $a->scenarios()[0]['steps'][0];
    assertSame(['task_for' => 'Юристы', 'do' => 'reject', 'comment' => 'Нет доверенности',
        'values' => [], 'title_contains' => null, 'optional' => false], $step);
    assertTrue($a->scenarios()[0]['steps'][1]['optional'], 'optional читается');
    assertSame([['text' => 'Ждать ли второго согласующего', 'required' => true],
        ['text' => 'Срок', 'required' => false]], $a->checklist());
});

test('Проверки: все роли задачи', function () {
    assertSame(['Бухгалтер проекта', 'Инициатор', 'Юристы'], Acceptance::fromArray(acceptanceData(), 'T04')->roleNames());
});

test('Проверки: ошибки формата — с путём', function () {
    $data = acceptanceData();
    $data['scenarios'][0]['steps'][0]['do'] = 'approv';
    assertThrows(fn () => Acceptance::fromArray($data, 'T04'), 'scenarios[0].steps[0].do', 'неизвестное действие');
    $data = acceptanceData();
    $data['scenarios'][0]['steps'][] = ['task_for' => 'Бухгалтер проекта', 'do' => 'provide'];
    assertThrows(fn () => Acceptance::fromArray($data, 'T04'), 'values', 'provide без values');
    $data = acceptanceData();
    $data['scenarios'][0]['expect']['цвет'] = 'синий';
    assertThrows(fn () => Acceptance::fromArray($data, 'T04'), 'expect.цвет', 'неизвестная проверка');
    $data = acceptanceData();
    $data['start'] = 'сразу';
    assertThrows(fn () => Acceptance::fromArray($data, 'T04'), 'start', 'неизвестный запуск');
});

test('Проверки: задача только с чек-листом', function () {
    $a = Acceptance::fromArray(['task' => 'T10', 'document' => 'Заявки', 'checklist' => ['Нет действия']], 'T10');
    assertSame([], $a->scenarios());
    assertThrows(fn () => Acceptance::fromArray(['task' => 'T10', 'document' => 'Заявки'], 'T10'),
        'нет ни сценариев, ни чек-листа', 'пустая задача');
});

test('Проверки: загрузка из файла', function () {
    $path = tmpPath('.yaml');
    file_put_contents($path, SpecReader::dump(acceptanceData()));
    assertSame('T04', Acceptance::load($path)->task());
});
```

- [ ] **Шаг 2: запуск тестов — ожидаем падение** (`tests/run.php` ещё нет — создаём его в шаге 3 вместе с
  классом; первый прогон: `php tools/bpt-eval/tests/run.php` → «Class "Acceptance" not found»).

- [ ] **Шаг 3: реализация** — `tools/bpt-eval/tests/run.php`

```php
<?php
/**
 * Тесты утилиты оценки: php tools/bpt-eval/tests/run.php [--filter=<часть имени>]
 * Помощники и классы tools/bpt переиспользуются. Код выхода: 0 — всё прошло, 1 — есть падения.
 */

declare(strict_types=1);

$bpt = dirname(__DIR__, 2) . '/bpt';
require_once $bpt . '/tests/support.php';
foreach (array_merge(glob($bpt . '/src/*.php') ?: [], glob(dirname(__DIR__) . '/src/*.php') ?: []) as $file) {
    require_once $file;
}
foreach (glob(__DIR__ . '/*_test.php') ?: [] as $file) {
    require_once $file;
}

$filter = null;
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--filter=(.+)$/u', $arg, $m)) {
        $filter = mb_strtolower($m[1]);
    }
}
$passed = $failed = $skipped = 0;
foreach ($GLOBALS['bpt_tests'] as $name => $fn) {
    if ($filter !== null && !str_contains(mb_strtolower($name), $filter)) {
        $skipped++;
        continue;
    }
    try {
        $fn();
        $passed++;
        fwrite(STDOUT, "OK    {$name}" . PHP_EOL);
    } catch (Throwable $e) {
        $failed++;
        fwrite(STDOUT, "FAIL  {$name}" . PHP_EOL . '      ' . $e->getMessage() . PHP_EOL);
    }
}
cleanupTempFiles();
fwrite(STDOUT, sprintf("\nПройдено: %d, упало: %d%s\n", $passed, $failed, $skipped ? ", пропущено: {$skipped}" : ''));
exit($failed ? 1 : 0);
```

`tools/bpt-eval/src/EvalException.php`

```php
<?php
/** Ошибка утилиты оценки: неверный формат задачи, сбой стенда, нет файла. */

declare(strict_types=1);

final class EvalException extends RuntimeException
{
}
```

`tools/bpt-eval/src/Acceptance.php`

```php
<?php
/**
 * Проверки задачи (acceptance.yaml): как стартует процесс, на каком элементе гоняем сценарии, какие
 * шаги и ожидания, какой чек-лист неоднозначностей. Формат — tools/bpt-eval/DESIGN.md §5.
 */

declare(strict_types=1);

final class Acceptance
{
    public const STARTS = ['create', 'manual'];
    public const ACTIONS = ['approve', 'reject', 'review', 'provide', 'complete'];
    public const EXPECT_KEYS = ['stage', 'fields', 'history_contains', 'notify', 'task_created',
        'observers', 'process', 'no_task_for'];
    public const PROCESS_STATES = ['completed', 'running'];

    private function __construct(private readonly array $data)
    {
    }

    public static function load(string $file): self
    {
        if (!is_file($file)) {
            throw new EvalException("нет файла проверок: {$file}");
        }
        return self::fromArray(SpecReader::read($file), $file);
    }

    public static function fromArray(array $data, string $source): self
    {
        $errors = [];
        $out = [
            'task'       => self::str($data, 'task', $errors),
            'document'   => self::str($data, 'document', $errors),
            'start'      => (string) ($data['start'] ?? 'create'),
            'parameters' => is_array($data['parameters'] ?? []) ? ($data['parameters'] ?? []) : [],
            'item'       => is_array($data['item'] ?? []) ? ($data['item'] ?? []) : [],
            'scenarios'  => [],
            'checklist'  => [],
        ];
        if (!in_array($out['start'], self::STARTS, true)) {
            $errors[] = "start: «{$out['start']}» — допустимо: " . implode(', ', self::STARTS);
        }
        foreach (array_values((array) ($data['scenarios'] ?? [])) as $i => $scenario) {
            $out['scenarios'][] = self::scenario((array) $scenario, "scenarios[{$i}]", $errors);
        }
        foreach (array_values((array) ($data['checklist'] ?? [])) as $i => $item) {
            if (is_string($item) && trim($item) !== '') {
                $out['checklist'][] = ['text' => trim($item), 'required' => true];
            } elseif (is_array($item) && trim((string) ($item['text'] ?? '')) !== '') {
                $out['checklist'][] = ['text' => trim((string) $item['text']), 'required' => (bool) ($item['required'] ?? true)];
            } else {
                $errors[] = "checklist[{$i}]: нужна строка или {text, required}";
            }
        }
        if (!$out['scenarios'] && !$out['checklist']) {
            $errors[] = 'нет ни сценариев, ни чек-листа';
        }
        if ($errors) {
            throw new EvalException("{$source}: " . implode('; ', $errors));
        }
        return new self($out);
    }

    public function task(): string { return $this->data['task']; }
    public function document(): string { return $this->data['document']; }
    public function start(): string { return $this->data['start']; }
    public function parameters(): array { return $this->data['parameters']; }
    public function item(): array { return $this->data['item']; }
    public function scenarios(): array { return $this->data['scenarios']; }
    public function checklist(): array { return $this->data['checklist']; }
    public function toArray(): array { return $this->data; }

    /** @return string[] все роли, упомянутые в шагах и проверках, по алфавиту */
    public function roleNames(): array
    {
        $roles = [];
        foreach ($this->data['scenarios'] as $scenario) {
            foreach ($scenario['steps'] as $step) {
                $roles[] = $step['task_for'];
            }
            $expect = $scenario['expect'];
            foreach ($expect['notify'] ?? [] as $n) {
                $roles[] = $n['to'];
            }
            foreach ($expect['task_created'] ?? [] as $t) {
                $roles[] = $t['responsible'];
            }
            array_push($roles, ...($expect['observers'] ?? []), ...($expect['no_task_for'] ?? []));
        }
        $roles = array_values(array_unique($roles));
        sort($roles);
        return $roles;
    }

    private static function scenario(array $s, string $path, array &$errors): array
    {
        $out = ['name' => self::str($s, 'name', $errors, $path), 'steps' => [], 'expect' => [],
            // поля элемента сценария — поверх item задачи (T02: разные суммы)
            'item' => is_array($s['item'] ?? null) ? $s['item'] : []];
        foreach (array_values((array) ($s['steps'] ?? [])) as $i => $step) {
            $p = "{$path}.steps[{$i}]";
            $step = (array) $step;
            $do = (string) ($step['do'] ?? '');
            if (!in_array($do, self::ACTIONS, true)) {
                $errors[] = "{$p}.do: неизвестное действие «{$do}»; допустимо: " . implode(', ', self::ACTIONS);
            }
            $values = is_array($step['values'] ?? null) ? $step['values'] : [];
            if ($do === 'provide' && !$values) {
                $errors[] = "{$p}: для provide нужны values: {поле: значение}";
            }
            $out['steps'][] = [
                'task_for'       => self::str($step, 'task_for', $errors, $p),
                'do'             => $do,
                'comment'        => (string) ($step['comment'] ?? ''),
                'values'         => $values,
                'title_contains' => isset($step['title_contains']) ? (string) $step['title_contains'] : null,
                'optional'       => (bool) ($step['optional'] ?? false),
            ];
        }
        foreach ((array) ($s['expect'] ?? []) as $key => $value) {
            if (!in_array($key, self::EXPECT_KEYS, true)) {
                $errors[] = "{$path}.expect.{$key}: неизвестная проверка; допустимо: " . implode(', ', self::EXPECT_KEYS);
                continue;
            }
            if ($key === 'process' && !in_array($value, self::PROCESS_STATES, true)) {
                $errors[] = "{$path}.expect.process: допустимо " . implode(', ', self::PROCESS_STATES);
            }
            if (in_array($key, ['notify', 'task_created'], true)) {
                $need = $key === 'notify' ? 'to' : 'responsible';
                foreach (array_values((array) $value) as $j => $row) {
                    if (trim((string) ($row[$need] ?? '')) === '') {
                        $errors[] = "{$path}.expect.{$key}[{$j}]: нужен {$need}";
                    }
                }
            }
            $out['expect'][$key] = $value;
        }
        return $out;
    }

    private static function str(array $data, string $key, array &$errors, string $path = ''): string
    {
        $value = trim((string) ($data[$key] ?? ''));
        if ($value === '') {
            $errors[] = ltrim("{$path}.{$key}", '.') . ': обязательное поле';
        }
        return $value;
    }
}
```

- [ ] **Шаг 4: тесты зелёные** — `php tools/bpt-eval/tests/run.php` → «Пройдено: 5, упало: 0».
- [ ] **Шаг 5: коммит** — `git add tools/bpt-eval/src tools/bpt-eval/tests` →
  «bpt-eval: каркас и формат проверок acceptance.yaml».

---

### Задача 2: Роли и сопоставление констант

**Файлы:** создать `tools/bpt-eval/src/Roles.php`, `tools/bpt-eval/src/ConstantMatcher.php`,
`tools/bpt-eval/roles.yaml`, `tools/bpt-eval/tests/roles_test.php`.

**Интерфейсы:**
- Даёт: `Roles::load(string $file): Roles`, `Roles::fromArray(array $data, string $source): Roles`,
  `has(string $role): bool`, `kind(string $role): string` (`group`|`department`|`project_field`|`user`),
  `target(string $role): string`, `missing(array $roles): string[]`, `names(): string[]`, `toArray(): array`
  (`[роль => ['kind' => …, 'target' => …]]`);
  `ConstantMatcher::match(array $constants, Roles $roles): array` →
  `['matched' => [код => роль], 'unmatched' => [код, …]]` — только константы с `Type` = `user`.

- [ ] **Шаг 1: падающий тест** — `tools/bpt-eval/tests/roles_test.php`

```php
<?php
/** Тесты ролей и сопоставления констант. */

declare(strict_types=1);

function rolesData(): array
{
    return [
        'Юристы' => ['group' => 'Юристы'],
        'Руководитель проекта' => ['project_field' => 'Руководитель проекта'],
        'Руководитель отдела продаж' => ['user' => 'Руководитель отдела продаж Тест'],
        'Юридический отдел' => ['department' => 'Юридический отдел'],
    ];
}

test('Роли: виды и цели', function () {
    $r = Roles::fromArray(rolesData(), 'roles.yaml');
    assertSame('project_field', $r->kind('Руководитель проекта'));
    assertSame('Юристы', $r->target('Юристы'));
    assertSame(['Сметчик'], $r->missing(['Юристы', 'Сметчик']));
    assertSame(['kind' => 'department', 'target' => 'Юридический отдел'], $r->toArray()['Юридический отдел']);
});

test('Роли: ошибки формата', function () {
    assertThrows(fn () => Roles::fromArray(['Юристы' => ['group' => 'Юристы', 'user' => 'X']], 'r'), 'ровно один', 'два вида');
    assertThrows(fn () => Roles::fromArray(['Юристы' => ['team' => 'Юристы']], 'r'), 'team', 'неизвестный вид');
});

test('Константы: сопоставление по названию', function () {
    $roles = Roles::fromArray(rolesData(), 'roles.yaml');
    $result = ConstantMatcher::match([
        'pm'    => ['Name' => 'Руководитель проекта', 'Type' => 'user'],
        'sales' => ['Name' => 'Руководитель отдела продаж (уведомление)', 'Type' => 'user'],
        'boss'  => ['Name' => 'Директор филиала', 'Type' => 'user'],
        'sum'   => ['Name' => 'Порог суммы', 'Type' => 'double'],
        // название важнее описания: в описании упомянуты юристы, но константа — про руководителя продаж
        'head'  => ['Name' => 'Руководитель отдела продаж', 'Description' => 'Сообщить, если юристы отказали', 'Type' => 'user'],
        'who'   => ['Name' => 'Кому сообщить', 'Description' => 'Руководитель проекта из карточки', 'Type' => 'user'],
    ], $roles);
    assertSame(['pm' => 'Руководитель проекта', 'sales' => 'Руководитель отдела продаж',
        'head' => 'Руководитель отдела продаж', 'who' => 'Руководитель проекта'], $result['matched']);
    assertSame(['boss'], $result['unmatched']);
});
```

- [ ] **Шаг 2:** `php tools/bpt-eval/tests/run.php --filter=Рол` → падает: «Class "Roles" not found».
- [ ] **Шаг 3: реализация** — `tools/bpt-eval/src/Roles.php`

```php
<?php
/**
 * Роли задач: название роли → кто это на стенде (группа, отдел, поле-роль проекта, сотрудник).
 * Файл roles.yaml хранит только имена — ID стенда определяет прогонщик на месте.
 */

declare(strict_types=1);

final class Roles
{
    public const KINDS = ['group', 'department', 'project_field', 'user'];

    private function __construct(private readonly array $roles)
    {
    }

    public static function load(string $file): self
    {
        if (!is_file($file)) {
            throw new EvalException("нет файла ролей: {$file}");
        }
        return self::fromArray(SpecReader::read($file), $file);
    }

    public static function fromArray(array $data, string $source): self
    {
        $roles = [];
        $errors = [];
        foreach ($data as $name => $spec) {
            $spec = is_array($spec) ? $spec : [];
            $kinds = array_keys($spec);
            if (count($kinds) !== 1) {
                $errors[] = "{$name}: нужен ровно один вид из " . implode(', ', self::KINDS);
                continue;
            }
            $kind = (string) $kinds[0];
            if (!in_array($kind, self::KINDS, true)) {
                $errors[] = "{$name}: неизвестный вид «{$kind}»; допустимо: " . implode(', ', self::KINDS);
                continue;
            }
            $target = trim((string) $spec[$kind]);
            if ($target === '') {
                $errors[] = "{$name}: пустое значение";
                continue;
            }
            $roles[(string) $name] = ['kind' => $kind, 'target' => $target];
        }
        if ($errors) {
            throw new EvalException("{$source}: " . implode('; ', $errors));
        }
        return new self($roles);
    }

    public function has(string $role): bool { return isset($this->roles[$role]); }
    public function kind(string $role): string { return $this->roles[$role]['kind']; }
    public function target(string $role): string { return $this->roles[$role]['target']; }
    public function names(): array { return array_keys($this->roles); }
    public function toArray(): array { return $this->roles; }

    /** @return string[] роли, которых нет в файле */
    public function missing(array $roles): array
    {
        return array_values(array_filter($roles, fn ($r) => !$this->has((string) $r)));
    }
}
```

`tools/bpt-eval/src/ConstantMatcher.php`

```php
<?php
/**
 * Константы-пользователи шаблона → роли задачи. Агент может вынести роль в константу; прогонщик
 * должен её заполнить. Совпадение — по вхождению названия роли в название константы, а если там
 * роли нет — в описание (регистр, «ё» и знаки препинания не важны); при нескольких совпадениях —
 * самое длинное название роли.
 */

declare(strict_types=1);

final class ConstantMatcher
{
    /** @return array{matched: array<string, string>, unmatched: string[]} */
    public static function match(array $constants, Roles $roles): array
    {
        $matched = [];
        $unmatched = [];
        foreach ($constants as $code => $constant) {
            if (($constant['Type'] ?? '') !== 'user') {
                continue;
            }
            $best = self::bestRole((string) ($constant['Name'] ?? ''), $roles)
                ?? self::bestRole((string) ($constant['Description'] ?? ''), $roles);
            if ($best === null) {
                $unmatched[] = (string) $code;
            } else {
                $matched[(string) $code] = $best;
            }
        }
        return ['matched' => $matched, 'unmatched' => $unmatched];
    }

    private static function bestRole(string $text, Roles $roles): ?string
    {
        $haystack = self::norm($text);
        $best = null;
        foreach ($roles->names() as $role) {
            if ($haystack !== '' && str_contains($haystack, self::norm($role))
                && ($best === null || mb_strlen($role) > mb_strlen($best))) {
                $best = $role;
            }
        }
        return $best;
    }

    private static function norm(string $s): string
    {
        $s = str_replace('ё', 'е', mb_strtolower($s));
        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s));
    }
}
```

`tools/bpt-eval/roles.yaml`

```yaml
# Роли задач оценки → кто это на стенде. Только имена: ID стенда прогонщик находит сам.
# Группы и «Проекты» создаёт eval.php prepare; сотрудников заводит человек.
Юристы:                     {group: Юристы}
Финансовый отдел:           {group: Финансовый отдел}
Бухгалтерия:                {group: Бухгалтеры}                  # группа; одноимённый отдел тоже есть
Юридический отдел:          {department: Юридический отдел}
Руководитель проекта:       {project_field: Руководитель проекта}
Бухгалтер проекта:          {project_field: Бухгалтер проекта}
Сметчик:                    {project_field: Сметчик}
Генеральный директор:       {user: Генеральный директор Тест}
Руководитель отдела продаж: {user: Руководитель отдела продаж Тест}
Инициатор:                  {user: Инженер ПТО Тест}
```

- [ ] **Шаг 4:** `php tools/bpt-eval/tests/run.php` → все зелёные.
- [ ] **Шаг 5: коммит** — «bpt-eval: роли задач и сопоставление констант».

---

### Задача 3: Отчёт (`Report`)

**Файлы:** создать `tools/bpt-eval/src/Report.php`, `tools/bpt-eval/tests/report_test.php`.

**Интерфейсы:**
- Использует: формат `result.json` (задача 6): `task`, `compile` (`ok`|`fail`|`skip`), `import`
  (`ok`|`fail`|`skip`), `scenarios[]` с `ok`, `reason`, `category`, `note`; `checklist.json` (задача 9):
  `{items: [{text, raised, quote}]}`; `agents.json`: `{задача: {tokens, tool_uses, duration_ms}}`; пункты
  чек-листа с `required` — из `Acceptance`.
- Даёт: `Report::fromData(array $results, array $checklists, array $acceptances, array $agents, string $run): Report`,
  `Report::fromRunDir(string $runDir, string $tasksDir): Report`, `rows(): array`, `passRate(): float`,
  `thresholdReached(): bool`, `toMarkdown(): string`; `Report::THRESHOLD = 0.8`.

- [ ] **Шаг 1: падающий тест** — `tools/bpt-eval/tests/report_test.php`

```php
<?php
/** Тесты сводки прогона. */

declare(strict_types=1);

function reportFixture(): Report
{
    $acc = fn (string $t, array $check) => Acceptance::fromArray(['task' => $t, 'document' => 'Заявки',
        'scenarios' => [['name' => 's', 'steps' => [], 'expect' => ['stage' => 'Клиент']]], 'checklist' => $check], $t);
    $results = [
        'T01' => ['task' => 'T01', 'compile' => 'ok', 'import' => 'ok', 'scenarios' => [['ok' => true]], 'reason' => '', 'category' => '', 'note' => ''],
        'T02' => ['task' => 'T02', 'compile' => 'fail', 'import' => 'skip', 'scenarios' => [], 'reason' => 'сборка', 'category' => 'spec', 'note' => 'сырые ID'],
        'T03' => ['task' => 'T03', 'compile' => 'ok', 'import' => 'ok', 'scenarios' => [['ok' => false]], 'reason' => 'сценарий', 'category' => 'harness', 'note' => 'стенд'],
    ];
    $checklists = [
        'T01' => ['items' => [['text' => 'История', 'raised' => true, 'quote' => '…'], ['text' => 'Срок', 'raised' => false, 'quote' => '']]],
        'T02' => ['items' => [['text' => 'Граница', 'raised' => true, 'quote' => '…']]],
    ];
    $acceptances = ['T01' => $acc('T01', ['История', ['text' => 'Срок', 'required' => false]]),
        'T02' => $acc('T02', ['Граница']), 'T03' => $acc('T03', ['Кто'])];
    $agents = ['T01' => ['tokens' => 170000, 'tool_uses' => 40, 'duration_ms' => 540000]];
    return Report::fromData($results, $checklists, $acceptances, $agents, 'пилот');
}

test('Отчёт: итог по задаче и доля без сбоев прогонщика', function () {
    $rows = reportFixture()->rows();
    assertTrue($rows['T01']['passed'], 'T01: сценарии и обязательный пункт чек-листа есть');
    assertTrue(!$rows['T02']['passed'], 'T02: сборка упала');
    assertSame('0/1', $rows['T02']['scenarios']);   // знаменатель — из проверок задачи, а не из результата
    assertSame(0.5, reportFixture()->passRate());   // T01 да, T02 нет; T03 — сбой прогонщика, не в доле
    assertTrue(!reportFixture()->thresholdReached(), 'порог 80% не достигнут');
});

test('Отчёт: сценарии не прогнаны — задача не пройдена', function () {
    $acc = Acceptance::fromArray(['task' => 'T05', 'document' => 'Заявки',
        'scenarios' => [['name' => 's', 'steps' => [], 'expect' => ['stage' => 'Клиент']]]], 'T05');
    $report = Report::fromData(['T05' => ['task' => 'T05', 'compile' => 'ok', 'import' => 'ok', 'scenarios' => [],
        'reason' => 'константы без сопоставления: boss', 'category' => '', 'note' => '']], [], ['T05' => $acc], [], 'r');
    assertTrue(!$report->rows()['T05']['passed'], 'нет результатов сценариев — не пройдена');
});

test('Отчёт: markdown', function () {
    $md = reportFixture()->toMarkdown();
    assertTrue(str_contains($md, '| Задача | Сборка | Импорт | Сценарии | Чек-лист |'), 'таблица');
    assertTrue(str_contains($md, 'Пройдено: 1 из 2 (50%)'), 'итог без сбоев прогонщика');
    assertTrue(str_contains($md, 'не достигнут'), 'вывод по порогу');
    assertTrue(str_contains($md, 'сбоем прогонщика: 1'), 'сбои прогонщика названы отдельно');
    assertTrue(str_contains($md, '- spec: T02 (сырые ID)'), 'слабые места по категориям');
    assertTrue(str_contains($md, '- harness: T03 (стенд)'), 'сбой прогонщика тоже в разборе');
});
```

- [ ] **Шаг 2:** `php tools/bpt-eval/tests/run.php --filter=Отчёт` → «Class "Report" not found».
- [ ] **Шаг 3: реализация** — `tools/bpt-eval/src/Report.php`

```php
<?php
/**
 * Сводка прогона: по задаче — сборка, импорт, сценарии, чек-лист, итог, расход агента, причина и
 * категория провала; по прогону — доля пройденных против порога. Сбои прогонщика (harness) в долю
 * не входят: их чинят и задачу перегоняют.
 */

declare(strict_types=1);

final class Report
{
    public const THRESHOLD = 0.8;

    private function __construct(private readonly array $rows, private readonly string $run)
    {
    }

    public static function fromRunDir(string $runDir, string $tasksDir): self
    {
        $results = $checklists = $acceptances = [];
        foreach (glob(rtrim($runDir, '/\\') . '/*/result.json') ?: [] as $file) {
            $result = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            $task = (string) $result['task'];
            $results[$task] = $result;
            $checklistFile = dirname($file) . '/checklist.json';
            if (is_file($checklistFile)) {
                $checklists[$task] = json_decode((string) file_get_contents($checklistFile), true, 512, JSON_THROW_ON_ERROR);
            }
            $taskDirs = glob(rtrim($tasksDir, '/\\') . "/{$task}-*", GLOB_ONLYDIR) ?: [];
            if ($taskDirs) {
                $acceptances[$task] = Acceptance::load($taskDirs[0] . '/acceptance.yaml');
            }
        }
        $agentsFile = rtrim($runDir, '/\\') . '/agents.json';
        $agents = is_file($agentsFile) ? json_decode((string) file_get_contents($agentsFile), true, 512, JSON_THROW_ON_ERROR) : [];
        ksort($results);
        return self::fromData($results, $checklists, $acceptances, $agents, basename(rtrim($runDir, '/\\')));
    }

    public static function fromData(array $results, array $checklists, array $acceptances, array $agents, string $run): self
    {
        $rows = [];
        foreach ($results as $task => $r) {
            $scenarios = $r['scenarios'] ?? [];
            $ok = count(array_filter($scenarios, fn ($s) => $s['ok'] ?? false));
            // Знаменатель — сценарии задачи: упавший импорт или константы не должны давать «0 из 0»
            $expected = isset($acceptances[$task]) ? count($acceptances[$task]->scenarios()) : count($scenarios);
            $raised = [];
            foreach ($checklists[$task]['items'] ?? [] as $item) {
                if (($item['raised'] ?? false) && trim((string) ($item['quote'] ?? '')) !== '') {
                    $raised[mb_strtolower(trim((string) $item['text']))] = true;
                }
            }
            $items = isset($acceptances[$task]) ? $acceptances[$task]->checklist() : [];
            $required = array_filter($items, fn ($i) => $i['required']);
            $requiredRaised = count(array_filter($required, fn ($i) => isset($raised[mb_strtolower($i['text'])])));
            $compileOk = in_array($r['compile'] ?? 'fail', ['ok', 'skip'], true);
            $importOk = in_array($r['import'] ?? 'fail', ['ok', 'skip'], true);
            $rows[$task] = [
                'task' => $task,
                'compile' => $r['compile'] ?? 'fail',
                'import' => $r['import'] ?? 'fail',
                'scenarios' => "{$ok}/{$expected}",
                'checklist' => count($raised) . '/' . count($items),
                'passed' => $compileOk && $importOk && $ok === $expected && $requiredRaised === count($required),
                'category' => (string) ($r['category'] ?? ''),
                'reason' => (string) ($r['reason'] ?? ''),
                'note' => (string) ($r['note'] ?? ''),
                'tokens' => $agents[$task]['tokens'] ?? null,
                'minutes' => isset($agents[$task]['duration_ms']) ? round($agents[$task]['duration_ms'] / 60000, 1) : null,
            ];
        }
        return new self($rows, $run);
    }

    public function rows(): array { return $this->rows; }

    public function passRate(): float
    {
        $counted = array_filter($this->rows, fn ($r) => $r['category'] !== 'harness');
        return $counted ? count(array_filter($counted, fn ($r) => $r['passed'])) / count($counted) : 0.0;
    }

    public function thresholdReached(): bool { return $this->passRate() >= self::THRESHOLD; }

    public function toMarkdown(): string
    {
        $mark = fn (string $v) => ['ok' => '✓', 'fail' => '✗', 'skip' => '—'][$v] ?? $v;
        $lines = ["# Оценка навыка «БП по ТЗ»: прогон {$this->run}", '',
            '| Задача | Сборка | Импорт | Сценарии | Чек-лист | Итог | Токены | Мин | Причина | Категория | Заметка |',
            '|--------|--------|--------|----------|----------|------|--------|-----|---------|-----------|---------|'];
        foreach ($this->rows as $r) {
            $lines[] = sprintf('| %s | %s | %s | %s | %s | %s | %s | %s | %s | %s | %s |', $r['task'],
                $mark($r['compile']), $mark($r['import']), $r['scenarios'], $r['checklist'],
                $r['passed'] ? '**пройдена**' : 'нет', $r['tokens'] ?? '—', $r['minutes'] ?? '—',
                $r['reason'] ?: '—', $r['category'] ?: '—', $r['note'] ?: '—');
        }
        $counted = array_filter($this->rows, fn ($r) => $r['category'] !== 'harness');
        $harness = count($this->rows) - count($counted);
        $passed = count(array_filter($counted, fn ($r) => $r['passed']));
        $lines[] = '';
        $lines[] = sprintf('**Пройдено: %d из %d (%d%%) — порог %d%% %s.**', $passed, count($counted),
            (int) round($this->passRate() * 100), (int) (self::THRESHOLD * 100),
            $this->thresholdReached() ? 'достигнут' : 'не достигнут');
        if ($harness) {
            $lines[] = "Не учтено задач со сбоем прогонщика: {$harness} — их перегоняют после исправления.";
        }
        $lines[] = 'Один прогон на задачу: порог оценён грубо.';

        // Слабые места: провалы по категориям — что чинить (spec — агент, tool — утилита, skill — навык)
        $byCategory = [];
        foreach ($this->rows as $r) {
            if (!$r['passed']) {
                $byCategory[$r['category'] ?: 'не разобрано'][] = $r['task'] . ($r['note'] ? " ({$r['note']})" : '');
            }
        }
        if ($byCategory) {
            $lines[] = '';
            $lines[] = '## Провалы по категориям';
            foreach ($byCategory as $category => $tasks) {
                $lines[] = "- {$category}: " . implode('; ', $tasks);
            }
        }
        return implode(PHP_EOL, $lines) . PHP_EOL;
    }
}
```

- [ ] **Шаг 4:** тесты зелёные.
- [ ] **Шаг 5: коммит** — «bpt-eval: сводка прогона и порог».

---

### Задача 4: Снимок и сборщик — поля связанных смарт-процессов (`tools/bpt`)

Роли в практике команды — поля карточки «Проекта». Шаблон читает их действием «Получить информацию об
элементе CRM» (`get_smart_item`) и ссылается на коды полей «Проекта». Сейчас снимок знает только поля
документа процесса: эти коды пришлось бы писать сырыми, а `--strict` это запрещает. Кроме кодов действию
нужны описания полей: типы результатов оно берёт из свойства `DynamicEntityFields`
(`setPropertiesTypes($this->DynamicEntityFields)` в `crmgetdynamicinfoactivity.php`; в `.description.php` —
`ADDITIONAL_RESULT => ['DynamicEntityFields']`). Дизайнер заполняет его сам — из диалога. Сборщик должен
делать то же по снимку. Во всех 7 случаях корпуса свойство заполнено.

**Файлы:** изменить `tools/bpt/src/Snapshot.php`, `tools/bpt/src/Compiler.php`,
`tools/bpt/tests/snapshot_test.php`, `tools/bpt/SPEC.md`.

**Интерфейсы:**
- Раздел снимка `related: {<Смарт-процесс>: {fields: {<Название поля>: <код>}, document_fields: {<код>: <описание как в DOCUMENT_FIELDS>}}}`;
  `document_fields` необязателен.
- Плейсхолдер `{{field:<Смарт-процесс>/<Название поля>}}` ищет поле в `related`. Без `/` или при
  неизвестном смарт-процессе работает обычный поиск по полям документа: поле с «/» в названии остаётся доступным.
- `Snapshot::relatedByTypeId(string $typeId): ?array` → `['title' => …, 'fields' => …, 'document_fields' => …]`;
  связь названия с ID — через раздел `smart`.
- `Snapshot::toArray()` отдаёт `related`, если он не пуст.
- Сборщик: у `get_smart_item` с пустым `DynamicEntityFields` заполняет его из `related` для каждого кода из
  `ReturnFields` и добавляет элемент `Document` (как диалог дизайнера). Нет описаний — предупреждение.

- [ ] **Шаг 1: падающие тесты** — дописать в `tools/bpt/tests/snapshot_test.php`

```php
function relatedSnapshot(): Snapshot
{
    return Snapshot::fromArray([
        'fields'  => ['Проект' => 'UF_CRM_4_PROJECT', 'Сумма/НДС' => 'UF_CRM_4_VAT'],
        'smart'   => ['Проекты' => '1040'],
        'related' => ['Проекты' => [
            'fields' => ['Руководитель проекта' => 'UF_CRM_5_PM'],
            'document_fields' => ['UF_CRM_5_PM' => ['Name' => 'Руководитель проекта', 'Type' => 'user',
                'Multiple' => false, 'Required' => false, 'Editable' => true, 'Filterable' => true, 'BaseType' => 'user']],
        ]],
    ], 'тест');
}

test('Снимок: поля связанного смарт-процесса', function () {
    $s = relatedSnapshot();
    assertSame('UF_CRM_5_PM', $s->resolve('field', 'Проекты/Руководитель проекта'));
    assertSame('UF_CRM_5_PM', $s->resolve('field', 'проекты / руководитель проекта'));
    assertSame('UF_CRM_4_VAT', $s->resolve('field', 'Сумма/НДС'));          // не смарт-процесс — обычное поле
    assertThrows(fn () => $s->resolve('field', 'Проекты/Сметчик'), 'Руководитель проекта', 'подсказка со списком полей');
    assertSame(['Руководитель проекта' => 'UF_CRM_5_PM'], $s->toArray()['related']['Проекты']['fields']);
    assertSame('Проекты', $s->relatedByTypeId('1040')['title']);
    assertSame(null, $s->relatedByTypeId('999'));
    assertThrows(fn () => Snapshot::fromArray(['related' => ['Проекты' => ['UF_CRM_5_PM']]], 'тест'), 'related.Проекты', 'формат');
});

test('Сборщик: роли из карточки связанного смарт-процесса', function () {
    $spec = minimalSpec([
        ['get_smart_item' => ['id' => 'project', 'DynamicTypeId' => '{{smart:Проекты}}',
            'ReturnFields' => ['{{field:Проекты/Руководитель проекта}}'],
            'DynamicFilterFields' => ['items' => [[['object' => 'Document', 'field' => 'ID', 'operator' => '=',
                'value' => '{=Document:{{field:Проект}}}'], 'AND']]]]],
        ['approve' => ['Users' => ['{=@project:{{field:Проекты/Руководитель проекта}}}'], 'Name' => 'Согласуйте',
            'ApproveType' => 'any']],
    ]);
    $r = (new Compiler(Catalog::load(), relatedSnapshot(), true))->compile($spec);
    assertSame([], $r['errors']);
    $children = $r['bpt']['TEMPLATE'][0]['Children'];
    $props = $children[0]['Properties'];
    assertSame(['UF_CRM_5_PM'], $props['ReturnFields']);
    assertSame('{=Document:UF_CRM_4_PROJECT}', $props['DynamicFilterFields']['items'][0][0]['value']);
    assertSame(['Name' => 'Руководитель проекта', 'Options' => [], 'Type' => 'user', 'Filterable' => '1',
        'Editable' => '1', 'Multiple' => '0', 'Required' => '0', 'BaseType' => 'user'], $props['DynamicEntityFields']['UF_CRM_5_PM']);
    assertSame(['Type' => 'document', 'Name' => 'Проекты',
        'Default' => ['crm', 'Bitrix\\Crm\\Integration\\BizProc\\Document\\Dynamic', 'DYNAMIC_1040']],
        $props['DynamicEntityFields']['Document']);
    assertSame(['{=' . $children[0]['Name'] . ':UF_CRM_5_PM}'], $children[1]['Properties']['Users']);
});

test('Сборщик: заполненный DynamicEntityFields не трогаем, без описаний — предупреждение', function () {
    $own = ['X' => ['Name' => 'Своё', 'Type' => 'string']];
    $spec = minimalSpec([['get_smart_item' => ['DynamicTypeId' => '{{smart:Проекты}}',
        'ReturnFields' => ['{{field:Проекты/Руководитель проекта}}'], 'DynamicEntityFields' => $own]]]);
    $r = (new Compiler(Catalog::load(), relatedSnapshot()))->compile($spec);
    assertSame($own, $r['bpt']['TEMPLATE'][0]['Children'][0]['Properties']['DynamicEntityFields']);

    $bare = Snapshot::fromArray(['smart' => ['Проекты' => '1040']], 'тест');
    $spec = minimalSpec([['get_smart_item' => ['DynamicTypeId' => '{{smart:Проекты}}', 'ReturnFields' => ['TITLE']]]]);
    $r = (new Compiler(Catalog::load(), $bare))->compile($spec);
    assertTrue((bool) array_filter($r['warnings'], fn ($w) => str_contains($w, 'DynamicEntityFields')), 'предупреждение');
});
```

- [ ] **Шаг 2:** `php tools/bpt/tests/run.php --filter=связанн` → падают: раздел `related` не читается,
  `relatedByTypeId` нет.
- [ ] **Шаг 3: снимок** — `tools/bpt/src/Snapshot.php`:
  - конструктор получает последним параметром `private readonly array $related = []`;
  - в `fromArray()`: `new self($sections, $data['document_fields'] ?? [], $source, $data['document'] ?? null,
    self::relatedFrom($data['related'] ?? [], $source))`;
  - в `toArray()` перед `return`: `if ($this->related) { $out['related'] = $this->related; }`;
  - в `resolve()` сразу после проверки вида:

```php
        if ($kind === 'field' && str_contains($name, '/')) {
            [$entity, $field] = array_map('trim', explode('/', $name, 2));
            foreach ($this->related as $title => $section) {
                if (self::normalizeKey((string) $title) !== self::normalizeKey($entity)) {
                    continue;
                }
                foreach ($section['fields'] as $fieldTitle => $code) {
                    if (self::normalizeKey((string) $fieldTitle) === self::normalizeKey($field)) {
                        return (string) $code;
                    }
                }
                throw new BptException("в снимке ({$this->source}) у «{$title}» нет поля «{$field}»; есть: "
                    . implode(', ', array_slice(array_keys($section['fields']), 0, 10)));
            }
        }
```

  - новые методы:

```php
    /** Раздел related смарт-процесса по его ID (ID — из раздела smart по тому же названию). */
    public function relatedByTypeId(string $typeId): ?array
    {
        foreach ($this->related as $title => $section) {
            try {
                $id = $this->resolve('smart', (string) $title);
            } catch (BptException) {
                continue;
            }
            if ($id === $typeId) {
                return ['title' => (string) $title] + $section;
            }
        }
        return null;
    }

    /** related: {Смарт-процесс: {fields: {Название: код}, document_fields: {код: описание}}} — связанные документы. */
    private static function relatedFrom(mixed $related, string $source): array
    {
        if (!is_array($related)) {
            throw new BptException("{$source}: раздел related должен быть объектом «смарт-процесс: {fields: …}»");
        }
        $out = [];
        foreach ($related as $title => $section) {
            $fields = is_array($section) && is_array($section['fields'] ?? null) ? $section['fields'] : null;
            if ($fields === null) {
                throw new BptException("{$source}: related.{$title} — нужен объект fields: {Название поля: код}");
            }
            $out[(string) $title] = ['fields' => array_map('strval', $fields),
                'document_fields' => is_array($section['document_fields'] ?? null) ? $section['document_fields'] : []];
        }
        return $out;
    }
```

- [ ] **Шаг 4: сборщик** — `tools/bpt/src/Compiler.php`, в построении простого действия сразу после
  `$props = $this->substitute($props, $label);`:

```php
        if ($type === 'CrmGetDynamicInfoActivity') {
            $props = $this->fillDynamicEntityFields($props, $label);
        }
```

  и новые методы (рядом с `substitute()`):

```php
    /**
     * Типы результатов «Получить информацию об элементе CRM» ядро берёт из DynamicEntityFields
     * (setPropertiesTypes); дизайнер заполняет свойство из диалога, мы — из раздела related снимка.
     */
    private function fillDynamicEntityFields(array $props, string $label): array
    {
        if (!empty($props['DynamicEntityFields']) || empty($props['ReturnFields'])) {
            return $props;
        }
        $typeId = (string) ($props['DynamicTypeId'] ?? '');
        $related = $this->snapshot?->relatedByTypeId($typeId);
        if ($related === null || !$related['document_fields']) {
            $this->warning($label, "DynamicEntityFields не заполнено: в снимке нет описаний полей смарт-процесса {$typeId}"
                . ' (раздел related) — типы результатов неизвестны; после импорта открыть действие в дизайнере и сохранить');
            return $props;
        }
        $described = [];
        foreach ((array) $props['ReturnFields'] as $code) {
            $field = $related['document_fields'][$code] ?? null;
            if ($field === null) {
                $this->warning($label, "DynamicEntityFields: в снимке нет описания поля {$code}");
                continue;
            }
            $described[(string) $code] = self::entityFieldDescription($field);
        }
        $described['Document'] = ['Type' => 'document', 'Name' => $related['title'],
            'Default' => ['crm', 'Bitrix\\Crm\\Integration\\BizProc\\Document\\Dynamic', 'DYNAMIC_' . $typeId]];
        $props['DynamicEntityFields'] = $described;
        return $props;
    }

    /** Описание поля результата в формате дизайнера (как в корпусе): флаги — строки «1»/«0». */
    private static function entityFieldDescription(array $field): array
    {
        $flag = fn (mixed $v) => in_array($v, [true, 1, '1', 'Y'], true) ? '1' : '0';
        return ['Name' => (string) ($field['Name'] ?? ''), 'Options' => $field['Options'] ?? [],
            'Type' => (string) ($field['Type'] ?? 'string'), 'Filterable' => $flag($field['Filterable'] ?? false),
            'Editable' => $flag($field['Editable'] ?? false), 'Multiple' => $flag($field['Multiple'] ?? false),
            'Required' => $flag($field['Required'] ?? false),
            'BaseType' => (string) ($field['BaseType'] ?? $field['Type'] ?? 'string')];
    }
```

- [ ] **Шаг 5:** в `tools/bpt/SPEC.md`, раздел про снимок, добавить:

```markdown
**Поля связанных смарт-процессов.** Когда шаблон читает другой смарт-процесс (например, роли из карточки
«Проекта» действием `get_smart_item`), коды и описания его полей берутся из раздела `related` снимка:

    related:
      Проекты:
        fields: {Руководитель проекта: UF_CRM_5_PM, Бухгалтер проекта: UF_CRM_5_ACC}
        document_fields: {UF_CRM_5_PM: {Name: Руководитель проекта, Type: user, Multiple: false}, …}

В спецификации — `{{field:Проекты/Руководитель проекта}}`, результат — `{=@id:{{field:Проекты/Руководитель проекта}}}`.
Свойство `DynamicEntityFields` (типы результатов) сборщик заполняет по `document_fields`, как дизайнер;
без описаний — предупреждение. Раздел заполняет скрипт стенда (`tools/bpt-eval`) или человек;
`bpt.php snapshot` из экспорта его не заполняет — в экспорте только поля своего документа.
```

- [ ] **Шаг 6:** `php tools/bpt/tests/run.php --corpus=C:/Users/d.kairov/Downloads` — все зелёные (было 87, стало 90;
  корпусные тесты «туда-обратно» не должны измениться: у корпуса `DynamicEntityFields` заполнен).
- [ ] **Шаг 7: коммит** — «bpt: поля связанных смарт-процессов в снимке и DynamicEntityFields по снимку».

---

### Задача 5: Стенд — подготовка и снимок (`Stand`, `setup`, `snapshot`, `eval.php prepare`)

**Файлы:** создать `tools/bpt-eval/src/Stand.php`, `tools/bpt-eval/stand/_bootstrap.php`,
`tools/bpt-eval/stand/setup.php`, `tools/bpt-eval/stand/snapshot.php`, `tools/bpt-eval/eval.php`,
`tools/bpt-eval/tests/stand_test.php`.

**Интерфейсы:**
- `Stand::fromEnv(): Stand`; `Stand::compose(string $script, array $input): string` — текст для stdin
  (вход + `_bootstrap.php` + скрипт, без повторных `<?php`); `run(string $script, array $input = []): array`
  — ответ скрипта (JSON), при ошибке — `EvalException` с stderr.
- Скрипты стенда получают вход в `$GLOBALS['EVAL_INPUT']` (массив) и отвечают `eval_out(array)`.
- `stand/setup.php` → `{types: {Заявки: {entityTypeId, typeId}, Проекты: {…}}, groups: {…}, project: {id},
  deactivated: [id шаблонов], created: [что создано]}`.
- `stand/snapshot.php` → `{document_fields, related: {Проекты: {fields}}, users, groups, smart}`.
- `eval.php prepare --run=<id>` пишет `work/eval/<id>/portal.yaml`.

- [ ] **Шаг 1: тест сборки потока для контейнера** — `tools/bpt-eval/tests/stand_test.php`

```php
<?php
/** Тесты сборки скрипта для контейнера (без стенда). */

declare(strict_types=1);

test('Стенд: вход, общая часть и скрипт — один PHP-поток', function () {
    $code = Stand::compose('setup', ['run' => 'пилот']);
    assertTrue(str_starts_with($code, '<?php'), 'начинается с <?php');
    assertSame(1, substr_count($code, '<?php'), 'открывающий тег один');
    assertTrue(str_contains($code, "\$GLOBALS['EVAL_INPUT']"), 'вход передан');
    assertTrue(str_contains($code, 'function eval_out('), 'общая часть подключена');
    assertTrue(!str_contains($code, 'declare(strict_types'), 'declare в потоке нет');
});
```

- [ ] **Шаг 2:** тест падает («Class "Stand" not found»).
- [ ] **Шаг 3: реализация** — `tools/bpt-eval/src/Stand.php`

```php
<?php
/**
 * Связь с тестовым стендом: скрипт stand/<имя>.php вместе с входными данными и общей частью
 * уходит в контейнер php одним потоком через stdin; ответ — JSON в stdout.
 */

declare(strict_types=1);

final class Stand
{
    public function __construct(private readonly string $dir)
    {
    }

    public static function fromEnv(): self
    {
        return new self((string) (getenv('BPT_EVAL_STAND') ?: 'C:/docker/b24-test'));
    }

    public static function compose(string $script, array $input): string
    {
        $standDir = dirname(__DIR__) . '/stand';
        $parts = [];
        foreach (["{$standDir}/_bootstrap.php", "{$standDir}/{$script}.php"] as $file) {
            if (!is_file($file)) {
                throw new EvalException("нет скрипта стенда: {$file}");
            }
            $parts[] = preg_replace('/^<\?php\s*/', '', (string) file_get_contents($file));
        }
        $payload = base64_encode(json_encode($input, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return "<?php\n\$GLOBALS['EVAL_INPUT'] = json_decode(base64_decode('{$payload}'), true);\n"
            . implode("\n", $parts);
    }

    public function run(string $script, array $input = []): array
    {
        $cmd = ['docker', 'compose', 'exec', '-T', '--user=bitrix', 'php', 'php'];
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->dir);
        if (!is_resource($proc)) {
            throw new EvalException("не удалось запустить docker compose в {$this->dir}");
        }
        fwrite($pipes[0], self::compose($script, $input));
        fclose($pipes[0]);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        $json = json_decode(trim($out), true);
        if ($code !== 0 || !is_array($json)) {
            throw new EvalException("стенд, {$script}: код {$code}; " . mb_substr(trim($err . ' ' . $out), 0, 1500));
        }
        if (isset($json['error'])) {
            throw new EvalException("стенд, {$script}: {$json['error']}");
        }
        return $json;
    }
}
```

- [ ] **Шаг 4: общая часть скриптов стенда** — `tools/bpt-eval/stand/_bootstrap.php`

```php
<?php
// Общая часть скриптов стенда: ядро Битрикса, администратор, помощники. Выполняется в контейнере php.
// Без use/declare/namespace: файл склеивается с другими в один поток (tools/bpt-eval/src/Stand.php).
$_SERVER['DOCUMENT_ROOT'] = '/opt/www';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
foreach (['crm', 'bizproc', 'iblock', 'tasks', 'im'] as $module) {
    \Bitrix\Main\Loader::includeModule($module);
}
$GLOBALS['USER']->Authorize(1);

const EVAL_REQUESTS_CODE = 'PILOT_REQUESTS';   // «Заявки» — тип пилота; setup создаст, если его нет
const EVAL_PROJECTS_CODE = 'EVAL_PROJECTS';

function eval_out(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function eval_fail(string $message): never
{
    eval_out(['error' => $message]);
    exit(0);
}

function eval_input(string $key, mixed $default = null): mixed
{
    return $GLOBALS['EVAL_INPUT'][$key] ?? $default;
}

function eval_norm(string $s): string
{
    return trim(str_replace('ё', 'е', mb_strtolower($s)));
}

function eval_type(string $code): ?\Bitrix\Main\ORM\Objectify\EntityObject
{
    $class = \Bitrix\Crm\Service\Container::getInstance()->getDynamicTypeDataClass();
    return $class::getList(['filter' => ['=CODE' => $code]])->fetchObject() ?: null;
}

function eval_factory(string $code): \Bitrix\Crm\Service\Factory
{
    $type = eval_type($code) ?? eval_fail("нет смарт-процесса с кодом {$code} — запустите eval.php prepare");
    return \Bitrix\Crm\Service\Container::getInstance()->getFactory((int) $type->getEntityTypeId());
}

function eval_document_type(string $code): array
{
    return ['crm', \Bitrix\Crm\Integration\BizProc\Document\Dynamic::class,
        'DYNAMIC_' . eval_factory($code)->getEntityTypeId()];
}

/** Поля документа: название → код (как в снимке), без печатных копий и ссылочных полей. */
function eval_fields(string $code): array
{
    $map = [];
    $fields = \CBPRuntime::GetRuntime(true)->getDocumentService()->GetDocumentFields(eval_document_type($code), true);
    foreach ($fields as $fieldCode => $field) {
        if (str_ends_with(mb_strtoupper((string) $fieldCode), '_PRINTABLE') || str_contains((string) $fieldCode, '.')) {
            continue;
        }
        $map[eval_norm((string) ($field['Name'] ?? $fieldCode))] = (string) $fieldCode;
    }
    return $map;
}

/** Пользователь по «Имя Фамилия» (как их заводит человек: «Юрист Тест»). */
function eval_user_id(string $fullName): ?int
{
    $rs = \CUser::GetList('ID', 'asc', ['ACTIVE' => 'Y'], ['FIELDS' => ['ID', 'NAME', 'LAST_NAME']]);
    while ($u = $rs->Fetch()) {
        if (eval_norm(trim($u['NAME'] . ' ' . $u['LAST_NAME'])) === eval_norm($fullName)) {
            return (int) $u['ID'];
        }
    }
    return null;
}

/** «Имя Фамилия» — так ответ на задание подписывается в результате Comments («ФИО (e-mail): …»). */
function eval_user_name(int $id): string
{
    $u = \CUser::GetByID($id)->Fetch();
    return $u ? trim($u['NAME'] . ' ' . $u['LAST_NAME']) : "#{$id}";
}

/** Значение поля запроса информации, которого нет в проверках задачи: по типу поля. */
function eval_default_value(array $field, int $userId): mixed
{
    return match ((string) ($field['Type'] ?? 'string')) {
        'date' => (new \Bitrix\Main\Type\Date())->toString(),
        'datetime' => (new \Bitrix\Main\Type\DateTime())->toString(),
        'int', 'double' => '1',
        'bool' => 'Y',
        'user' => 'user_' . $userId,
        'select' => (string) array_key_first((array) ($field['Options'] ?? [])),
        default => 'Оценка: значение по умолчанию',
    };
}

function eval_group_id(string $name): ?int
{
    $rs = \CGroup::GetList('id', 'asc', ['NAME' => $name]);
    while ($g = $rs->Fetch()) {
        if (eval_norm($g['NAME']) === eval_norm($name)) {
            return (int) $g['ID'];
        }
    }
    return null;
}

function eval_department_id(string $name): ?int
{
    $iblockId = (int) \COption::GetOptionInt('intranet', 'iblock_structure', 0);
    $rs = \CIBlockSection::GetList([], ['IBLOCK_ID' => $iblockId, 'NAME' => $name], false, ['ID', 'NAME']);
    while ($s = $rs->Fetch()) {
        if (eval_norm($s['NAME']) === eval_norm($name)) {
            return (int) $s['ID'];
        }
    }
    return null;
}
```

- [ ] **Шаг 5: подготовка стенда** — `tools/bpt-eval/stand/setup.php`

```php
<?php
// Подготовка стенда к оценке: группы, «Проекты» с полями-ролями, поля и стадии «Заявок», «Проект Альфа».
// Повторный запуск ничего не дублирует. Шаблоны «Заявок» не из оценки с автозапуском при создании или
// изменении — деактивируются; шаблоны роботов (AUTO_EXECUTE = 8) не трогаем.
$created = [];

// 1. Смарт-процессы
$ensureType = function (string $code, string $title, bool $stages) use (&$created) {
    if ($type = eval_type($code)) {
        return $type;
    }
    $class = \Bitrix\Crm\Service\Container::getInstance()->getDynamicTypeDataClass();
    $type = $class::createObject();
    foreach (['TITLE' => $title, 'NAME' => $code, 'CODE' => $code, 'IS_STAGES_ENABLED' => $stages ? 'Y' : 'N',
        'IS_CATEGORIES_ENABLED' => 'N', 'IS_BIZ_PROC_ENABLED' => 'Y', 'IS_AUTOMATION_ENABLED' => 'Y',
        'IS_SET_OPEN_PERMISSIONS' => 'Y'] as $k => $v) {
        $type->set($k, $v);
    }
    $result = $type->save();
    if (!$result->isSuccess()) {
        eval_fail("{$title}: " . implode('; ', $result->getErrorMessages()));
    }
    $created[] = "смарт-процесс {$title}";
    return $type;
};
$requestsType = $ensureType(EVAL_REQUESTS_CODE, 'Заявки', true);
$projectsType = $ensureType(EVAL_PROJECTS_CODE, 'Проекты', false);
$requests = eval_factory(EVAL_REQUESTS_CODE);
$projects = eval_factory(EVAL_PROJECTS_CODE);

// 2. Пользовательские поля
$ensureField = function (\Bitrix\Crm\Service\Factory $factory, string $suffix, string $label, string $userType, array $settings = []) use (&$created) {
    $entityId = $factory->getUserFieldEntityId();
    $name = 'UF_' . $entityId . '_' . $suffix;
    if (!\CUserTypeEntity::GetList([], ['ENTITY_ID' => $entityId, 'FIELD_NAME' => $name])->Fetch()) {
        $id = (new \CUserTypeEntity())->Add(['ENTITY_ID' => $entityId, 'FIELD_NAME' => $name, 'USER_TYPE_ID' => $userType,
            'MANDATORY' => 'N', 'MULTIPLE' => 'N', 'SHOW_IN_LIST' => 'Y', 'SETTINGS' => $settings,
            'EDIT_FORM_LABEL' => ['ru' => $label], 'LIST_COLUMN_LABEL' => ['ru' => $label], 'LIST_FILTER_LABEL' => ['ru' => $label]]);
        if (!$id) {
            eval_fail("поле {$label}: " . ($GLOBALS['APPLICATION']->GetException()?->GetString() ?? 'ошибка'));
        }
        $created[] = "поле {$label}";
    }
    return $name;
};
$ensureField($requests, 'AMOUNT', 'Сумма к оплате', 'double', ['PRECISION' => 2]);
$projectLink = $ensureField($requests, 'PROJECT', 'Проект', 'crm', ['DYNAMIC_' . $projects->getEntityTypeId() => 'Y']);
$ensureField($requests, 'INVOICE_NO', 'Номер счёта', 'string');
$ensureField($requests, 'PAID_ON', 'Дата оплаты', 'date');
$ensureField($requests, 'APPROVED_BY', 'Согласовано кем', 'employee');
$roleFields = [];
foreach (['PM' => 'Руководитель проекта', 'ESTIMATOR' => 'Сметчик', 'ACCOUNTANT' => 'Бухгалтер проекта'] as $suffix => $label) {
    $roleFields[$label] = $ensureField($projects, $suffix, $label, 'employee');
}

// 3. Стадии «Заявок»
$category = $requests->createDefaultCategoryIfNotExist();
$stagesEntity = $requests->getStagesEntityId($category->getId());
$prefix = 'DT' . $requests->getEntityTypeId() . '_' . $category->getId() . ':';
$existing = [];
foreach ($requests->getStages($category->getId()) as $stage) {
    $existing[eval_norm($stage->getName())] = true;
}
foreach ([['UC_REWORK', 'Доработка', 25], ['UC_CLIENT', 'Клиент', 35], ['UC_APPROVED', 'Согласовано', 36], ['UC_PAID', 'Оплачено', 37]] as [$code, $name, $sort]) {
    if (isset($existing[eval_norm($name)])) {
        continue;
    }
    $result = \Bitrix\Crm\StatusTable::add(['ENTITY_ID' => $stagesEntity, 'STATUS_ID' => $prefix . $code, 'NAME' => $name,
        'NAME_INIT' => $name, 'SORT' => $sort, 'SYSTEM' => false, 'COLOR' => \Bitrix\Crm\StatusTable::DEFAULT_PROCESS_COLOR,
        'CATEGORY_ID' => $category->getId()]);
    if (!$result->isSuccess()) {
        eval_fail("стадия {$name}: " . implode('; ', $result->getErrorMessages()));
    }
    $created[] = "стадия {$name}";
}

// 4. Группы пользователей и их состав
$groups = [];
foreach ([['Юристы', 'EVAL_LAWYERS', ['Юрист Тест']], ['Финансовый отдел', 'EVAL_FINANCE', ['Финансовый директор Тест']],
    ['Бухгалтеры', 'EVAL_ACCOUNTANTS', ['Бухгалтер Тест']]] as [$name, $stringId, $members]) {
    $groupId = eval_group_id($name);
    if (!$groupId) {
        $groupId = (int) (new \CGroup())->Add(['ACTIVE' => 'Y', 'NAME' => $name, 'STRING_ID' => $stringId]);
        if (!$groupId) {
            eval_fail("группа {$name} не создана");
        }
        $created[] = "группа {$name}";
    }
    foreach ($members as $fullName) {
        $userId = eval_user_id($fullName) ?? eval_fail("нет сотрудника «{$fullName}» — его заводит человек");
        \CUser::AppendUserGroup($userId, [$groupId]);
    }
    $groups[$name] = $groupId;
}

// 5. «Проект Альфа» с ролями
$projectId = null;
foreach ($projects->getItems(['filter' => ['=TITLE' => 'Проект Альфа']]) as $item) {
    $projectId = $item->getId();
}
if (!$projectId) {
    $item = $projects->createItem(['TITLE' => 'Проект Альфа']);
    foreach (['Руководитель проекта' => 'Руководитель проекта Тест', 'Сметчик' => 'Сметчик Тест', 'Бухгалтер проекта' => 'Бухгалтер Тест'] as $label => $fullName) {
        $item->set($roleFields[$label], eval_user_id($fullName) ?? eval_fail("нет сотрудника «{$fullName}»"));
    }
    $result = $projects->getAddOperation($item)->disableAllChecks()->launch();
    if (!$result->isSuccess()) {
        eval_fail('Проект Альфа: ' . implode('; ', $result->getErrorMessages()));
    }
    $projectId = $item->getId();
    $created[] = 'Проект Альфа';
}

// 6. Чужие шаблоны «Заявок» с автозапуском при создании (1) или изменении (2) мешают сценариям —
//    деактивировать. Роботы (8) не трогаем: они пусты, а их шаблоны создаёт сам портал.
$deactivated = [];
$rs = \CBPWorkflowTemplateLoader::GetList([], ['DOCUMENT_TYPE' => eval_document_type(EVAL_REQUESTS_CODE), 'ACTIVE' => 'Y'],
    false, false, ['ID', 'NAME', 'AUTO_EXECUTE']);
while ($t = $rs->Fetch()) {
    if (((int) $t['AUTO_EXECUTE'] & 3) !== 0 && !str_starts_with((string) $t['NAME'], 'EVAL ')) {
        \CBPWorkflowTemplateLoader::update($t['ID'], ['ACTIVE' => 'N']);
        $deactivated[] = (int) $t['ID'];
    }
}

eval_out(['types' => ['Заявки' => $requests->getEntityTypeId(), 'Проекты' => $projects->getEntityTypeId()],
    'project_link_field' => $projectLink, 'groups' => $groups, 'project' => ['id' => $projectId],
    'deactivated' => $deactivated, 'created' => $created]);
```

- [ ] **Шаг 6: данные для снимка** — `tools/bpt-eval/stand/snapshot.php`

```php
<?php
// Данные для снимка портала агентов: поля «Заявок» и «Проектов» (описания, как DOCUMENT_FIELDS экспорта),
// сотрудники (без администратора и ботов), группы и отделы, смарт-процессы. Названия → коды строит хост.
$describe = function (string $code): array {
    $out = [];
    foreach (\CBPRuntime::GetRuntime(true)->getDocumentService()->GetDocumentFields(eval_document_type($code), true) as $field => $info) {
        if (!str_ends_with(mb_strtoupper((string) $field), '_PRINTABLE') && !str_contains((string) $field, '.')) {
            $out[$field] = $info;
        }
    }
    return $out;
};
$users = [];
$rs = \CUser::GetList('ID', 'asc', ['ACTIVE' => 'Y'], ['FIELDS' => ['ID', 'NAME', 'LAST_NAME', 'EXTERNAL_AUTH_ID']]);
while ($u = $rs->Fetch()) {
    if ((int) $u['ID'] !== 1 && ($u['EXTERNAL_AUTH_ID'] ?? '') === '') {
        $users[trim($u['NAME'] . ' ' . $u['LAST_NAME'])] = 'user_' . $u['ID'];
    }
}
$groups = [];
foreach (['Юристы', 'Финансовый отдел', 'Бухгалтеры'] as $name) {
    $groups[$name] = 'group_g' . (eval_group_id($name) ?? eval_fail("нет группы {$name} — запустите prepare"));
}
$iblockId = (int) \COption::GetOptionInt('intranet', 'iblock_structure', 0);
$rs = \CIBlockSection::GetList(['LEFT_MARGIN' => 'ASC'], ['IBLOCK_ID' => $iblockId], false, ['ID', 'NAME']);
while ($s = $rs->Fetch()) {
    $groups[$s['NAME']] = 'group_d' . $s['ID'];
}
eval_out(['document_fields' => $describe(EVAL_REQUESTS_CODE),
    'related_document_fields' => ['Проекты' => $describe(EVAL_PROJECTS_CODE)],
    'users' => $users, 'groups' => $groups,
    'smart' => ['Заявки' => (string) eval_factory(EVAL_REQUESTS_CODE)->getEntityTypeId(),
        'Проекты' => (string) eval_factory(EVAL_PROJECTS_CODE)->getEntityTypeId()]]);
```

- [ ] **Шаг 7: CLI** — `tools/bpt-eval/eval.php` (команда `prepare`; остальные добавляют задачи 6–9)

```php
<?php
/**
 * Оценка навыка «БП по ТЗ» на типовых задачах. Команды:
 *   php tools/bpt-eval/eval.php prepare   --run=<прогон>
 *   php tools/bpt-eval/eval.php reference <задача|all> --run=<прогон>
 *   php tools/bpt-eval/eval.php check     <задача> --run=<прогон>
 *   php tools/bpt-eval/eval.php usage     <задача> --run=<прогон> --tokens=N --tools=N --ms=N
 *   php tools/bpt-eval/eval.php report    --run=<прогон>
 * Результаты — в work/eval/<прогон>/ (вне git). Стенд — BPT_EVAL_STAND (по умолчанию C:/docker/b24-test).
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
foreach (array_merge(glob("{$root}/tools/bpt/src/*.php") ?: [], glob(__DIR__ . '/src/*.php') ?: []) as $file) {
    require_once $file;
}

$args = array_slice($argv, 1);
$command = array_shift($args) ?? '';
$positional = [];
$opts = [];
foreach ($args as $arg) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/u', $arg, $m)) {
        $opts[$m[1]] = $m[2] ?? true;
    } else {
        $positional[] = $arg;
    }
}
$run = (string) ($opts['run'] ?? '');
if ($run === '' || !preg_match('/^[\w.-]+$/u', $run)) {
    fwrite(STDERR, "нужен --run=<прогон> (буквы, цифры, «-», «_», «.»)\n");
    exit(2);
}
$runDir = "{$root}/work/eval/{$run}";
@mkdir($runDir, 0777, true);

try {
    switch ($command) {
        case 'prepare':
            $stand = Stand::fromEnv();
            $setup = $stand->run('setup');
            $data = $stand->run('snapshot');
            $snapshot = Snapshot::fromBpt(['DOCUMENT_FIELDS' => $data['document_fields']], 'стенд')->toArray();
            $snapshot['users'] = $data['users'];
            $snapshot['groups'] = $data['groups'];
            $snapshot['smart'] = $data['smart'];
            $snapshot['related'] = [];
            foreach ($data['related_document_fields'] as $title => $fields) {
                // названия → коды — той же группировкой, что поля документа (одинаковые названия — с кодом)
                $snapshot['related'][$title] = ['fields' => Snapshot::fromBpt(['DOCUMENT_FIELDS' => $fields], $title)->section('field'),
                    'document_fields' => $fields];
            }
            file_put_contents("{$runDir}/portal.yaml", "# Снимок тестового стенда для оценки, прогон {$run}, "
                . date('Y-m-d H:i') . ". Настоящий, не синтетический.\n" . SpecReader::dump($snapshot));
            file_put_contents("{$runDir}/setup.json", json_encode($setup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            echo 'Стенд готов: ', ($setup['created'] ? implode(', ', $setup['created']) : 'всё уже было'), PHP_EOL;
            if ($setup['deactivated']) {
                echo 'Деактивированы чужие шаблоны с автозапуском: #', implode(', #', $setup['deactivated']), PHP_EOL;
            }
            echo "Снимок: {$runDir}/portal.yaml", PHP_EOL;
            exit(0);
        default:
            fwrite(STDERR, "неизвестная команда «{$command}»\n");
            exit(2);
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Ошибка: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
```

- [ ] **Шаг 8: тесты зелёные** (`php tools/bpt-eval/tests/run.php`).
- [ ] **Шаг 9: проверка на стенде** — `php tools/bpt-eval/eval.php prepare --run=pilot-1`. Ожидаем:
  «Стенд готов: смарт-процесс Проекты, поле Проект, …, группа Юристы, …, Проект Альфа»; второй запуск —
  «всё уже было»; в `work/eval/pilot-1/portal.yaml` есть `users` (8 сотрудников «… Тест», без администратора и
  бота), `groups` (3 группы + 5 отделов), `smart` (Заявки, Проекты), `related.Проекты.fields` с тремя ролями и
  их `document_fields` (`Type: user`), поле «Проект» в `fields`, стадии «Согласовано» и «Оплачено» в `stages`.
  Шаблон пилота #6 деактивирован (автозапуск при создании мешал бы сценариям); шаблоны роботов #11–#17 активны.
  Сверка ID и имён — только в `work/` (вне git).
- [ ] **Шаг 10: коммит** — «bpt-eval: подготовка стенда и снимок портала для агентов».

---

### Задача 6: Импорт, уборка и `check` без сценариев

**Файлы:** создать `tools/bpt-eval/stand/import.php`, `tools/bpt-eval/stand/cleanup.php`,
`tools/bpt-eval/src/CheckRunner.php`; изменить `tools/bpt-eval/eval.php` (команды `check`, `reference`).

**Интерфейсы:**
- `stand/import.php`: вход `{bpt_b64, name, auto_execute}` → `{ok: true, template_id, constants}` или
  `{ok: false, error}`.
- `stand/cleanup.php`: вход `{template_id}` → `{deactivated: bool, terminated: N}`.
- `CheckRunner::__construct(Stand $stand, string $root, string $run)`;
  `check(string $task, string $specFile, string $outDir): array` — пишет и возвращает `result.json`:
  `{task, run, spec, compile, compile_errors, import, import_error, template_id, constants_matched,
  constants_unmatched, scenarios, reason, category, note}`.
- Папка задачи находится по префиксу: `tasks/<задача>-*`.

- [ ] **Шаг 1:** `tools/bpt-eval/stand/import.php`

```php
<?php
// Импорт шаблона тем же методом, что кнопка «Импорт» в дизайнере и REST (ImportTemplate).
// Перед импортом: другие активные шаблоны оценки на «Заявках» деактивируются (изоляция задач).
$documentType = eval_document_type(EVAL_REQUESTS_CODE);
$rs = \CBPWorkflowTemplateLoader::GetList([], ['DOCUMENT_TYPE' => $documentType, 'ACTIVE' => 'Y'], false, false, ['ID', 'NAME']);
while ($t = $rs->Fetch()) {
    if (str_starts_with((string) $t['NAME'], 'EVAL ')) {
        \CBPWorkflowTemplateLoader::update($t['ID'], ['ACTIVE' => 'N']);
    }
}
try {
    $id = \CBPWorkflowTemplateLoader::ImportTemplate(0, $documentType, (int) eval_input('auto_execute', 0),
        (string) eval_input('name'), 'Оценка навыка: шаблон агента', base64_decode((string) eval_input('bpt_b64'), true));
} catch (\Throwable $e) {
    eval_out(['ok' => false, 'error' => $e->getMessage()]);
    exit(0);
}
if (!$id) {
    $ex = $GLOBALS['APPLICATION']->GetException();
    eval_out(['ok' => false, 'error' => $ex ? $ex->GetString() : 'импорт вернул пустой ID']);
    exit(0);
}
$tpl = \CBPWorkflowTemplateLoader::GetList([], ['ID' => $id], false, false, ['ID', 'CONSTANTS'])->Fetch();
eval_out(['ok' => true, 'template_id' => (int) $id, 'constants' => $tpl['CONSTANTS'] ?: []]);
```

- [ ] **Шаг 2:** `tools/bpt-eval/stand/cleanup.php`

```php
<?php
// Уборка после задачи: шаблон — неактивен, незавершённые процессы шаблона — остановлены.
$templateId = (int) eval_input('template_id');
\CBPWorkflowTemplateLoader::update($templateId, ['ACTIVE' => 'N']);
$db = \Bitrix\Main\Application::getConnection();
$terminated = 0;
foreach ($db->query("SELECT s.ID, s.DOCUMENT_ID FROM b_bp_workflow_state s JOIN b_bp_workflow_instance i ON i.ID = s.ID
    WHERE s.WORKFLOW_TEMPLATE_ID = {$templateId}")->fetchAll() as $wf) {
    $errors = [];
    \CBPDocument::TerminateWorkflow($wf['ID'], ['crm', \Bitrix\Crm\Integration\BizProc\Document\Dynamic::class,
        $wf['DOCUMENT_ID']], $errors, 'Оценка: уборка после задачи');
    $terminated++;
}
eval_out(['deactivated' => true, 'terminated' => $terminated]);
```

- [ ] **Шаг 3:** `tools/bpt-eval/src/CheckRunner.php`

```php
<?php
/**
 * Проверка одной задачи: пересборка спецификации с --strict → импорт на стенд → константы → сценарии
 * → уборка. Результат — result.json в папке задачи. Категорию провала (spec/tool/skill/harness)
 * человек ставит при разборе; автоматически пишется только причина.
 */

declare(strict_types=1);

final class CheckRunner
{
    public function __construct(private readonly Stand $stand, private readonly string $root, private readonly string $run)
    {
    }

    public function taskDir(string $task): string
    {
        $dirs = glob("{$this->root}/tools/bpt-eval/tasks/{$task}-*", GLOB_ONLYDIR) ?: [];
        if (count($dirs) !== 1) {
            throw new EvalException("задача {$task}: папка tasks/{$task}-* не найдена или не одна");
        }
        return $dirs[0];
    }

    public function check(string $task, string $specFile, string $outDir): array
    {
        $acceptance = Acceptance::load($this->taskDir($task) . '/acceptance.yaml');
        $roles = Roles::load("{$this->root}/tools/bpt-eval/roles.yaml");
        if ($missing = $roles->missing($acceptance->roleNames())) {
            throw new EvalException("{$task}: в roles.yaml нет ролей " . implode(', ', $missing));
        }
        @mkdir($outDir, 0777, true);
        $result = ['task' => $task, 'run' => $this->run, 'spec' => $specFile, 'compile' => 'skip', 'compile_errors' => [],
            'import' => 'skip', 'import_error' => '', 'template_id' => null, 'constants_matched' => [],
            'constants_unmatched' => [], 'scenarios' => [], 'reason' => '', 'category' => '', 'note' => ''];
        if (!is_file($specFile)) {
            if ($acceptance->scenarios()) {   // задача со сценариями без решения — провал, а не «0 из 0»
                $result['compile'] = 'fail';
                $result['reason'] = 'нет спецификации';
            }
            return $this->save($outDir, $result);
        }

        $snapshot = Snapshot::load("{$this->root}/work/eval/{$this->run}/portal.yaml");
        $compiled = (new Compiler(Catalog::load(), $snapshot, true))->compile(SpecReader::read($specFile));
        if ($compiled['errors']) {
            $result['compile'] = 'fail';
            $result['compile_errors'] = $compiled['errors'];
            $result['reason'] = 'сборка';
            return $this->save($outDir, $result);
        }
        $result['compile'] = 'ok';
        $bptFile = "{$outDir}/eval.bpt";
        BptFile::write($bptFile, $compiled['bpt'], null, true);

        $import = $this->stand->run('import', ['bpt_b64' => base64_encode((string) file_get_contents($bptFile)),
            'name' => "EVAL {$this->run} {$task}", 'auto_execute' => $acceptance->start() === 'create' ? 1 : 0]);
        if (!$import['ok']) {
            $result['import'] = 'fail';
            $result['import_error'] = $import['error'];
            $result['reason'] = 'импорт';
            return $this->save($outDir, $result);
        }
        $result['import'] = 'ok';
        $result['template_id'] = $import['template_id'];

        try {
            $constants = ConstantMatcher::match($import['constants'], $roles);
            $result['constants_matched'] = $constants['matched'];
            $result['constants_unmatched'] = $constants['unmatched'];
            if ($constants['unmatched']) {
                $result['reason'] = 'константы без сопоставления: ' . implode(', ', $constants['unmatched']);
                return $this->save($outDir, $result);
            }
            if ($acceptance->scenarios()) {
                try {
                    $run = $this->stand->run('runner', ['task' => $task, 'template_id' => $import['template_id'],
                        'acceptance' => $acceptance->toArray(), 'roles' => $roles->toArray(), 'constants' => $constants['matched']]);
                } catch (EvalException $e) {   // упал сам прогонщик — не против навыка: чиним и перегоняем
                    $result['reason'] = 'прогонщик: ' . $e->getMessage();
                    $result['category'] = 'harness';
                    return $this->save($outDir, $result);
                }
                $result['scenarios'] = $run['scenarios'];
                $failed = array_filter($run['scenarios'], fn ($s) => !$s['ok']);
                $result['reason'] = $failed ? 'сценарий: ' . implode(', ', array_column($failed, 'name')) : '';
            }
        } finally {
            $this->stand->run('cleanup', ['template_id' => $import['template_id']]);
        }
        return $this->save($outDir, $result);
    }

    private function save(string $outDir, array $result): array
    {
        file_put_contents("{$outDir}/result.json", json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $result;
    }
}
```

- [ ] **Шаг 4:** в `eval.php` добавить команды (перед `default:`):

```php
        case 'check':
            $task = (string) ($positional[0] ?? '');
            $result = (new CheckRunner(Stand::fromEnv(), $root, $run))->check($task,
                "{$runDir}/{$task}/process.bizproc.yaml", "{$runDir}/{$task}");
            printf("%s: сборка %s, импорт %s, сценарии %d/%d%s\n", $task, $result['compile'], $result['import'],
                count(array_filter($result['scenarios'], fn ($s) => $s['ok'])), count($result['scenarios']),
                $result['reason'] ? " — {$result['reason']}" : '');
            exit(0);
        case 'reference':
            $checker = new CheckRunner(Stand::fromEnv(), $root, $run);
            $tasks = ($positional[0] ?? 'all') === 'all'
                ? array_map(fn ($d) => explode('-', basename($d))[0], glob(__DIR__ . '/tasks/*', GLOB_ONLYDIR) ?: [])
                : [(string) $positional[0]];
            $failed = 0;
            foreach ($tasks as $task) {
                $reference = $checker->taskDir($task) . '/reference.bizproc.yaml';
                if (!is_file($reference)) {
                    echo "{$task}: эталона нет — пропуск\n";
                    continue;
                }
                $result = $checker->check($task, $reference, "{$runDir}/_reference/{$task}");
                $ok = $result['compile'] === 'ok' && $result['import'] === 'ok' && $result['reason'] === '';
                $failed += $ok ? 0 : 1;
                printf("%s: %s%s\n", $task, $ok ? 'эталон прошёл' : 'ЭТАЛОН НЕ ПРОШЁЛ', $result['reason'] ? " — {$result['reason']}" : '');
            }
            exit($failed ? 1 : 0);
```

- [ ] **Шаг 5: проверка на стенде (временная задача-дымок).** Положить во временную папку
  `tools/bpt-eval/tasks/T00-smoke/` файл `acceptance.yaml` c `task: T00`, `document: Заявки`,
  `checklist: [дымок]` и эталон — копию `work/bizproc/pilot-zayavki/process.bizproc.yaml` как есть
  (`{{group:Бухгалтерия}}` в новом снимке — отдел `group_d…`; константа «Руководитель отдела продаж»
  сопоставится с одноимённой ролью).
  `php tools/bpt-eval/eval.php reference T00 --run=pilot-1` → «T00: эталон прошёл»; в
  `work/eval/pilot-1/_reference/T00/result.json` — `compile: ok`, `import: ok`, `template_id`,
  `constants_matched: {sales_head: Руководитель отдела продаж}`; на стенде шаблон `EVAL pilot-1 T00` неактивен.
  Папку `T00-smoke` удалить, в git не добавлять.
- [ ] **Шаг 6: коммит** — «bpt-eval: импорт, уборка и проверка задачи без сценариев».

---

### Задача 7: Прогонщик сценариев — ядро (утверждение, стадия, процесс, история, уведомления) и T01

**Файлы:** создать `tools/bpt-eval/stand/runner.php`, `tools/bpt-eval/tasks/T01-summa/tz.md`,
`tools/bpt-eval/tasks/T01-summa/acceptance.yaml`, `tools/bpt-eval/tasks/T01-summa/reference.bizproc.yaml`.

**Интерфейсы:**
- Вход `runner`: `{task, template_id, acceptance, roles, constants: {код: роль}}`.
- Выход: `{scenarios: [{name, ok, item_id, steps: [{task_for, do, ok, detail}], checks: [{check, ok, expected, actual}]}]}`.
- Ответы на задания `approve`, `reject`, `review`, `provide` — по намерению шага (`$taskRequest`: утверждение,
  ознакомление, запрос информации с отклонением и без); проверки `stage`, `process`, `history_contains`,
  `notify`, `no_task_for`. Задачи модуля «Задачи» (`complete`) и проверки `fields`, `task_created`,
  `observers` — задача 8.
- Поля элемента: `item` задачи, поверх — `item` сценария (задача 1).

- [ ] **Шаг 1:** `tools/bpt-eval/stand/runner.php`

```php
<?php
// Сценарии задачи на стенде: элемент → задания по ролям → ответы → проверки наблюдаемого результата.
$task = (string) eval_input('task');
$templateId = (int) eval_input('template_id');
$acceptance = eval_input('acceptance');
$roles = eval_input('roles');
$factory = eval_factory(EVAL_REQUESTS_CODE);
$fields = eval_fields(EVAL_REQUESTS_CODE);
$entityTypeId = $factory->getEntityTypeId();
$db = \Bitrix\Main\Application::getConnection();
$projects = eval_factory(EVAL_PROJECTS_CODE);

// Проект элемента — для ролей «из карточки проекта»; сценарий может взять другой проект
$findProject = function (?string $title) use ($projects): ?int {
    if ($title === null) {
        return null;
    }
    foreach ($projects->getItems(['filter' => ['=TITLE' => $title]]) as $p) {
        return $p->getId();
    }
    eval_fail("нет проекта «{$title}» — запустите prepare");
};
$projectId = $findProject(isset($acceptance['item']['Проект']) ? (string) $acceptance['item']['Проект'] : null);

/** Роль → ID пользователей стенда (роли проекта — по проекту текущего сценария). */
$roleUsers = function (string $role) use ($roles, $projects, &$projectId): array {
    $spec = $roles[$role] ?? eval_fail("роль «{$role}» не описана");
    switch ($spec['kind']) {
        case 'user':
            return [eval_user_id($spec['target']) ?? eval_fail("нет сотрудника «{$spec['target']}»")];
        case 'group':
            $groupId = eval_group_id($spec['target']) ?? eval_fail("нет группы «{$spec['target']}»");
            return array_map('intval', \CGroup::GetGroupUser($groupId));
        case 'department':
            $depId = eval_department_id($spec['target']) ?? eval_fail("нет отдела «{$spec['target']}»");
            $ids = [];
            $rs = \CUser::GetList('ID', 'asc', ['ACTIVE' => 'Y', 'UF_DEPARTMENT' => $depId], ['FIELDS' => ['ID']]);
            while ($u = $rs->Fetch()) {
                $ids[] = (int) $u['ID'];
            }
            return $ids;
        case 'project_field':
            $projectId ?? eval_fail("роль «{$role}» из карточки проекта, а у элемента нет «Проект»");
            $code = eval_fields(EVAL_PROJECTS_CODE)[eval_norm($spec['target'])] ?? eval_fail("у «Проектов» нет поля «{$spec['target']}»");
            return array_map('intval', (array) $projects->getItem($projectId)->get($code));
    }
    return [];
};

// Константы-пользователи шаблона — по ролям
$constantsMap = (array) eval_input('constants', []);
if ($constantsMap) {
    $tpl = \CBPWorkflowTemplateLoader::GetList([], ['ID' => $templateId], false, false, ['ID', 'CONSTANTS'])->Fetch();
    $constants = $tpl['CONSTANTS'];
    foreach ($constantsMap as $code => $role) {
        $users = array_map(fn ($id) => 'user_' . $id, $roleUsers($role));
        $constants[$code]['Default'] = ($constants[$code]['Multiple'] ?? '0') === '1' ? $users : ($users[0] ?? '');
    }
    \CBPWorkflowTemplateLoader::update($templateId, ['CONSTANTS' => $constants]);
}

$stageName = function (string $stageId) use ($factory): string {
    foreach ($factory->getStages() as $stage) {
        if ($stage->getStatusId() === $stageId) {
            return $stage->getName();
        }
    }
    return $stageId;
};

/** Открытые задания процессов элемента (задания, где пользователь ещё не ответил). */
$openTasks = function (int $itemId) use ($entityTypeId, $db): array {
    $doc = "DYNAMIC_{$entityTypeId}_{$itemId}";
    return $db->query("SELECT t.ID, t.WORKFLOW_ID, t.ACTIVITY, t.ACTIVITY_NAME, t.NAME, tu.USER_ID
        FROM b_bp_task t JOIN b_bp_task_user tu ON tu.TASK_ID = t.ID
        JOIN b_bp_workflow_state s ON s.ID = t.WORKFLOW_ID
        WHERE s.DOCUMENT_ID = '{$doc}' AND t.STATUS = 0 AND tu.STATUS = 0 ORDER BY t.ID")->fetchAll();
};

/**
 * Ответ на задание по намерению шага, а не по устройству процесса: approve, review и provide —
 * положительный ответ на любое задание (утвердить, ознакомиться, отправить запрошенное), reject —
 * отказ, где он возможен. Разумный выбор механизма агентом так не штрафуется. Строка — причина отказа.
 */
$taskRequest = function (array $task, array $step, int $userId): array|string {
    $comment = $step['comment'] !== '' ? $step['comment'] : 'Оценка: ответ по сценарию';
    $request = ['task_comment' => $comment];
    $positive = $step['do'] !== 'reject';
    switch ($task['ACTIVITY']) {
        case 'ApproveActivity':
            $request[$positive ? 'approve' : 'nonapprove'] = 'Y';
            return $request;
        case 'ReviewActivity':
            return $positive ? $request : 'ознакомление нельзя отклонить';
        case 'RequestInformationActivity':
        case 'RequestInformationOptionalActivity':
            if (!$positive) {
                return $task['ACTIVITY'] === 'RequestInformationOptionalActivity'
                    ? $request + ['cancel' => true] : 'запрос информации нельзя отклонить';
            }
            $request['fields'] = ['task_comment' => $comment];
            $values = $step['values'];
            foreach ((array) ($task['PARAMETERS']['REQUEST'] ?? []) as $field) {
                $title = eval_norm((string) ($field['Title'] ?? $field['Name'] ?? ''));
                $value = null;
                foreach ($values as $key => $candidate) {
                    $label = eval_norm((string) $key);
                    if ($title !== '' && (str_contains($title, $label) || str_contains($label, $title))) {
                        $value = $candidate;
                        unset($values[$key]);
                        break;
                    }
                }
                // поле, которого нет в проверках (агент назвал его по-своему), — значение по типу
                $request['fields'][(string) $field['Name']] = $value ?? eval_default_value($field, $userId);
            }
            return $request;
    }
    return "неизвестный вид задания {$task['ACTIVITY']}";
};

$results = [];
foreach ($acceptance['scenarios'] as $scenario) {
    $since = (new \Bitrix\Main\Type\DateTime())->format('Y-m-d H:i:s');
    $out = ['name' => $scenario['name'], 'ok' => true, 'item_id' => null, 'steps' => [], 'checks' => []];

    // Элемент — от имени инициатора, если такая роль есть (автозапуск идёт от создателя)
    $itemFields = $scenario['item'] + $acceptance['item'];
    $projectId = $findProject(isset($itemFields['Проект']) ? (string) $itemFields['Проект'] : null);
    $data = ['TITLE' => "EVAL {$task}: {$scenario['name']}"];
    foreach ($itemFields as $name => $value) {
        if (eval_norm($name) === eval_norm('Название')) {
            continue;
        }
        $code = $fields[eval_norm($name)] ?? eval_fail("у «Заявок» нет поля «{$name}»");
        $data[$code] = eval_norm($name) === eval_norm('Проект') ? $projectId : $value;
    }
    $initiator = isset($roles['Инициатор']) ? $roleUsers('Инициатор')[0] : 1;
    $data['ASSIGNED_BY_ID'] = $initiator;
    $GLOBALS['USER']->Authorize($initiator);
    $item = $factory->createItem($data);
    $add = $factory->getAddOperation($item)->disableCheckAccess()->launch();   // автозапуск БП остаётся
    $GLOBALS['USER']->Authorize(1);
    if (!$add->isSuccess()) {
        $out['ok'] = false;
        $out['checks'][] = ['check' => 'создание элемента', 'ok' => false, 'expected' => 'создан', 'actual' => implode('; ', $add->getErrorMessages())];
        $results[] = $out;
        continue;
    }
    $itemId = $item->getId();
    $out['item_id'] = $itemId;
    $docId = ['crm', \Bitrix\Crm\Integration\BizProc\Document\Dynamic::class, "DYNAMIC_{$entityTypeId}_{$itemId}"];
    if ($acceptance['start'] === 'manual') {
        $errors = [];
        \CBPDocument::startWorkflow($templateId, $docId, $acceptance['parameters'], $errors);
        if ($errors) {
            $out['ok'] = false;
            $out['checks'][] = ['check' => 'ручной запуск', 'ok' => false, 'expected' => 'запущен', 'actual' => json_encode($errors, JSON_UNESCAPED_UNICODE)];
            $results[] = $out;
            continue;
        }
    }

    // Шаги
    foreach ($scenario['steps'] as $step) {
        $users = $roleUsers($step['task_for']);
        $found = null;
        for ($i = 0; $i < 10 && !$found; $i++) {
            foreach ($openTasks($itemId) as $t) {
                if (in_array((int) $t['USER_ID'], $users, true)
                    && ($step['title_contains'] === null || mb_stripos($t['NAME'], $step['title_contains']) !== false)) {
                    $found = $t;
                    break;
                }
            }
            if (!$found) {
                sleep(1);
            }
        }
        if (!$found) {
            $ok = $step['optional'];
            $out['ok'] = $out['ok'] && $ok;
            $open = array_map(fn ($t) => "#{$t['USER_ID']}: {$t['NAME']}", $openTasks($itemId));
            $out['steps'][] = ['task_for' => $step['task_for'], 'do' => $step['do'], 'ok' => $ok,
                'detail' => ($ok ? 'необязательный шаг: задания нет' : 'задания для роли нет') . ($open ? '; открыты: ' . implode(' | ', $open) : '; открытых заданий нет')];
            continue;
        }
        $full = \CBPTaskService::GetList([], ['ID' => $found['ID']], false, false,
            ['ID', 'WORKFLOW_ID', 'ACTIVITY', 'ACTIVITY_NAME', 'NAME', 'DESCRIPTION', 'PARAMETERS'])->Fetch();
        $where = "задание #{$found['ID']} «{$full['NAME']}» ({$full['ACTIVITY']}), пользователь #{$found['USER_ID']}";
        $request = $taskRequest($full, $step, (int) $found['USER_ID']);
        if (is_string($request)) {
            $out['ok'] = false;
            $out['steps'][] = ['task_for' => $step['task_for'], 'do' => $step['do'], 'ok' => false, 'detail' => "{$where}: {$request}"];
            continue;
        }
        \CBPActivity::IncludeActivityFile($full['ACTIVITY']);
        $class = 'CBP' . $full['ACTIVITY'];
        $errors = [];
        $posted = $class::PostTaskForm($full, (int) $found['USER_ID'], $request, $errors, eval_user_name((int) $found['USER_ID']));
        $out['ok'] = $out['ok'] && (bool) $posted;
        $out['steps'][] = ['task_for' => $step['task_for'], 'do' => $step['do'], 'ok' => (bool) $posted,
            'detail' => $posted ? $where : "{$where}: " . json_encode($errors, JSON_UNESCAPED_UNICODE)];
    }

    // Проверки
    $expect = $scenario['expect'];
    $running = fn () => (int) $db->query("SELECT COUNT(*) AS C FROM b_bp_workflow_state s JOIN b_bp_workflow_instance i ON i.ID = s.ID
        WHERE s.DOCUMENT_ID = 'DYNAMIC_{$entityTypeId}_{$itemId}' AND s.WORKFLOW_TEMPLATE_ID = {$templateId}")->fetch()['C'];
    if (($expect['process'] ?? null) === 'completed') {
        for ($i = 0; $i < 10 && $running() > 0; $i++) {
            sleep(1);
        }
    }
    $check = function (string $name, bool $ok, mixed $expected, mixed $actual) use (&$out) {
        $out['checks'][] = ['check' => $name, 'ok' => $ok, 'expected' => $expected, 'actual' => $actual];
        $out['ok'] = $out['ok'] && $ok;
    };
    $item = $factory->getItem($itemId);
    foreach ($expect as $key => $value) {
        switch ($key) {
            case 'stage':
                $actual = $stageName($item->getStageId());
                $check('стадия', eval_norm($actual) === eval_norm($value), $value, $actual);
                break;
            case 'process':
                $actual = $running() > 0 ? 'running' : 'completed';
                $check('процесс', $actual === $value, $value, $actual);
                break;
            case 'history_contains':
                // «история» в ТЗ — запись в историю CRM или комментарий в карточке: заказчики зовут так оба
                $texts = array_merge(
                    array_column($db->query("SELECT e.EVENT_TEXT_1 FROM b_crm_event e JOIN b_crm_event_relations r ON r.EVENT_ID = e.ID
                        WHERE r.ENTITY_TYPE = 'DYNAMIC_{$entityTypeId}' AND r.ENTITY_ID = {$itemId}")->fetchAll(), 'EVENT_TEXT_1'),
                    array_column($db->query("SELECT t.COMMENT FROM b_crm_timeline t JOIN b_crm_timeline_bind b ON b.OWNER_ID = t.ID
                        WHERE b.ENTITY_TYPE_ID = {$entityTypeId} AND b.ENTITY_ID = {$itemId}")->fetchAll(), 'COMMENT'));
                foreach ((array) $value as $fragment) {
                    $hit = array_filter($texts, fn ($t) => mb_stripos(strip_tags((string) $t), $fragment) !== false);
                    $check("история: «{$fragment}»", (bool) $hit, $fragment, $hit ? 'есть' : 'нет; записей: ' . count($texts));
                }
                break;
            case 'notify':
                foreach ((array) $value as $n) {
                    $users = implode(',', $roleUsers($n['to'])) ?: '0';
                    // уведомление (чат уведомлений получателя, TYPE = S) или сообщение ему в личный чат (TYPE = P)
                    $messages = array_column($db->query("SELECT m.MESSAGE FROM b_im_message m JOIN b_im_chat c ON c.ID = m.CHAT_ID
                        WHERE m.DATE_CREATE >= '{$since}' AND (
                            (c.TYPE = 'S' AND c.AUTHOR_ID IN ({$users}))
                            OR (c.TYPE = 'P' AND m.AUTHOR_ID NOT IN ({$users})
                                AND c.ID IN (SELECT r.CHAT_ID FROM b_im_relation r WHERE r.USER_ID IN ({$users}))))")->fetchAll(), 'MESSAGE');
                    $contains = $n['contains'] ?? null;
                    $hit = array_filter($messages, fn ($m) => $contains === null || mb_stripos(strip_tags((string) $m), $contains) !== false);
                    $check("уведомление: {$n['to']}" . ($contains ? " «{$contains}»" : ''), (bool) $hit, $contains ?? 'есть',
                        $hit ? 'есть' : 'нет; уведомлений роли: ' . count($messages));
                }
                break;
            case 'no_task_for':
                foreach ((array) $value as $role) {
                    $users = implode(',', $roleUsers($role)) ?: '0';
                    $count = (int) $db->query("SELECT COUNT(*) AS C FROM b_bp_task t JOIN b_bp_task_user tu ON tu.TASK_ID = t.ID
                        JOIN b_bp_workflow_state s ON s.ID = t.WORKFLOW_ID
                        WHERE s.DOCUMENT_ID = 'DYNAMIC_{$entityTypeId}_{$itemId}' AND tu.USER_ID IN ({$users})")->fetch()['C'];
                    $check("нет задания: {$role}", $count === 0, 0, $count);
                }
                break;
        }
    }
    $results[] = $out;
}
eval_out(['scenarios' => $results]);
```

- [ ] **Шаг 2: задача T01** — `tools/bpt-eval/tasks/T01-summa/tz.md`

```markdown
Нужен бизнес-процесс для смарт-процесса «Заявки». Когда заявка создаётся, бухгалтерия должна утвердить
сумму заявки. Если утвердили — перевести заявку на стадию «Клиент» и записать в историю комментарий
согласующего. Если отклонили — вернуть на стадию «Доработка» и уведомить руководителя отдела продаж.
```

`tools/bpt-eval/tasks/T01-summa/acceptance.yaml`

```yaml
task: T01
document: Заявки
start: create
item:
  Название: Заявка T01
  Сумма к оплате: 150000
  Проект: Проект Альфа
scenarios:
  - name: утвердили
    steps:
      - {task_for: Бухгалтерия, do: approve, comment: Сумма соответствует договору}
    expect:
      stage: Клиент
      history_contains: [Сумма соответствует договору]
      process: completed
  - name: отклонили
    steps:
      - {task_for: Бухгалтерия, do: reject, comment: Нет подтверждающих документов}
    expect:
      stage: Доработка
      notify: [{to: Руководитель отдела продаж}]
      process: completed
checklist:
  - Бухгалтерия — группа пользователей или отдел
  - «История» — запись в историю CRM или комментарий в карточке
  - Повторное согласование после доработки
  - {text: Срок на утверждение, required: false}
```

`tools/bpt-eval/tasks/T01-summa/reference.bizproc.yaml`

```yaml
# Эталон T01: проверяет сами сценарии; агенту не показывается.
bizproc: 1
name: EVAL T01 эталон
steps:
  - approve:
      id: accounting
      Users: ["{{group:Бухгалтеры}}"]
      ApproveType: any
      CommentRequired: "Y"
      Name: "Утвердите сумму заявки «{=Document:TITLE}»"
      Description: "Сумма: {=Document:{{field:Сумма к оплате}} > printable}"
      on_yes:
        - crm_event:
            EventText: "Сумма утверждена. Комментарий: {=@accounting:Comments}"
            EventUser: ["{=@accounting:LastApprover}"]
        - change_stage: {TargetStatus: "{{stage:Клиент}}"}
      on_no:
        - notify:
            MessageSite: "Бухгалтерия отклонила заявку «{=Document:TITLE}»: {=@accounting:Comments}"
            MessageUserFrom: ["{=@accounting:LastApprover}"]
            MessageUserTo: ["{{user:Руководитель отдела продаж Тест}}"]
        - change_stage: {TargetStatus: "{{stage:Доработка}}"}
```

- [ ] **Шаг 3: эталон проходит** — `php tools/bpt-eval/eval.php reference T01 --run=pilot-1` → «T01: эталон прошёл».
  Если нет — чинить прогонщик (не эталон под прогонщик), пока `result.json` не покажет 2/2 сценария.
- [ ] **Шаг 4: тесты хоста зелёные**; `php tools/bpt/tests/run.php --corpus=…` зелёные.
- [ ] **Шаг 5: коммит** — «bpt-eval: прогонщик сценариев (ядро) и задача T01».

---

### Задача 8: Прогонщик — задачи модуля «Задачи» и проверки полей; T04 и T10

**Файлы:** изменить `tools/bpt-eval/stand/runner.php`; создать `tools/bpt-eval/tasks/T04-parallel/*`,
`tools/bpt-eval/tasks/T10-email/{tz.md,acceptance.yaml}`.

**Интерфейсы:** действие `complete` (закрыть задачу модуля «Задачи», назначенную роли после старта
сценария); проверки `fields`, `task_created`, `observers`. Ответы на задания БП уже есть (задача 7).

- [ ] **Шаг 1:** в `runner.php` — ветка для задач модуля «Задачи» (у `complete` нет задания БП), сразу после
  `$users = $roleUsers($step['task_for']);`:

```php
        if ($step['do'] === 'complete') {
            $taskRow = null;
            for ($i = 0; $i < 10 && !$taskRow; $i++) {
                $taskRow = \Bitrix\Tasks\Internals\TaskTable::getList(['select' => ['ID', 'RESPONSIBLE_ID', 'TITLE'],
                    'filter' => ['@RESPONSIBLE_ID' => $users, '>=CREATED_DATE' => new \Bitrix\Main\Type\DateTime($since, 'Y-m-d H:i:s'),
                        '!=STATUS' => 5], 'order' => ['ID' => 'DESC'], 'limit' => 1])->fetch() ?: null;
                $taskRow ?? sleep(1);
            }
            $ok = $taskRow !== null;
            if ($ok) {
                \CTaskItem::getInstance((int) $taskRow['ID'], (int) $taskRow['RESPONSIBLE_ID'])->complete();
            }
            $out['ok'] = $out['ok'] && ($ok || $step['optional']);
            $out['steps'][] = ['task_for' => $step['task_for'], 'do' => 'complete', 'ok' => $ok || $step['optional'],
                'detail' => $ok ? "задача #{$taskRow['ID']} «{$taskRow['TITLE']}» закрыта" : 'задачи для роли нет'];
            continue;
        }
```

- [ ] **Шаг 2:** в `runner.php`, в `switch ($key)` проверок добавить:

```php
            case 'fields':
                foreach ((array) $value as $name => $want) {
                    $code = $fields[eval_norm((string) $name)] ?? null;
                    $actual = $code === null ? '(нет поля)' : $item->get($code);
                    if ($actual instanceof \Bitrix\Main\Type\Date) {
                        $actual = $actual->toString();   // формат сайта, как вводит пользователь: 01.10.2026
                    }
                    if (is_string($want) && isset($roles[$want])) {        // значение — роль: сравниваем сотрудников
                        $ok = (bool) array_intersect(array_map('intval', (array) $actual), $roleUsers($want));
                    } else {
                        $ok = eval_norm(is_array($actual) ? implode(',', $actual) : (string) $actual) === eval_norm((string) $want);
                    }
                    $check("поле «{$name}»", $ok, $want, $actual);
                }
                break;
            case 'task_created':
                foreach ((array) $value as $t) {
                    $rows = [];
                    for ($i = 0; $i < 10 && !$rows; $i++) {   // задачу ставит процесс — ждём до 10 секунд
                        $rows = array_filter(\Bitrix\Tasks\Internals\TaskTable::getList(['select' => ['ID', 'TITLE', 'DEADLINE'],
                            'filter' => ['@RESPONSIBLE_ID' => $roleUsers($t['responsible']),
                                '>=CREATED_DATE' => new \Bitrix\Main\Type\DateTime($since, 'Y-m-d H:i:s')]])->fetchAll(),
                            fn ($r) => !isset($t['title_contains']) || mb_stripos($r['TITLE'], $t['title_contains']) !== false);
                        $rows ?: sleep(1);
                    }
                    $ok = (bool) $rows;
                    $actual = $ok ? implode(' | ', array_map(fn ($r) => $r['TITLE'] . ' до ' . ($r['DEADLINE'] ? $r['DEADLINE']->format('Y-m-d') : '—'), $rows)) : 'задачи нет';
                    if ($ok && isset($t['deadline_workdays'])) {
                        $day = new \DateTimeImmutable('today');
                        for ($n = 0; $n < (int) $t['deadline_workdays'];) {
                            $day = $day->modify('+1 day');
                            $n += (int) $day->format('N') <= 5 ? 1 : 0;
                        }
                        $ok = (bool) array_filter($rows, function ($r) use ($day) {
                            if (!$r['DEADLINE']) {
                                return false;
                            }
                            $diff = abs((new \DateTimeImmutable($r['DEADLINE']->format('Y-m-d')))->diff($day)->days);
                            return $diff <= 1;   // допуск: праздники производственного календаря
                        });
                    }
                    $check("задача: {$t['responsible']}", $ok, $t, $actual);
                }
                break;
            case 'observers':
                $observers = array_map('intval', (array) $item->get('OBSERVERS'));
                foreach ((array) $value as $role) {
                    $check("наблюдатель: {$role}", (bool) array_intersect($observers, $roleUsers($role)), $role, $observers);
                }
                break;
```

- [ ] **Шаг 3: задача T04** — `tools/bpt-eval/tasks/T04-parallel/tz.md`

```markdown
Для «Заявок»: при создании заявку одновременно согласуют юрист и бухгалтер проекта (он указан в карточке
проекта, к которому привязана заявка). Если оба согласовали — стадия «Согласовано». Если кто-то отказал —
вернуть на «Доработку» и сообщить инициатору причину.
```

`tools/bpt-eval/tasks/T04-parallel/acceptance.yaml`

```yaml
task: T04
document: Заявки
start: create
item:
  Название: Заявка T04
  Сумма к оплате: 250000
  Проект: Проект Альфа
scenarios:
  - name: оба согласовали
    steps:
      - {task_for: Юристы, do: approve, comment: Юридически чисто}
      - {task_for: Бухгалтер проекта, do: approve, comment: Сумма верна}
    expect: {stage: Согласовано, process: completed}
  - name: юрист отказал
    steps:
      - {task_for: Юристы, do: reject, comment: Нет доверенности}
      - {task_for: Бухгалтер проекта, do: approve, comment: Сумма верна, optional: true}
    expect:
      stage: Доработка
      notify: [{to: Инициатор, contains: Нет доверенности}]
      process: completed
  - name: бухгалтер отказал
    steps:
      - {task_for: Бухгалтер проекта, do: reject, comment: Сумма не совпадает со счётом}
      - {task_for: Юристы, do: approve, comment: Юридически чисто, optional: true}
    expect:
      stage: Доработка
      notify: [{to: Инициатор, contains: Сумма не совпадает со счётом}]
      process: completed
checklist:
  - Ждать ли второго согласующего после первого отказа
  - Юрист — группа «Юристы» или конкретный сотрудник
  - Повторное согласование после доработки
  - {text: Срок на согласование, required: false}
```

`tools/bpt-eval/tasks/T04-parallel/reference.bizproc.yaml`

```yaml
# Эталон T04: параллельно юрист и бухгалтер проекта; роли — из карточки проекта.
bizproc: 1
name: EVAL T04 эталон
variables:
  lawyer_ok:     {Name: Юрист согласовал, Type: bool, Default: "N"}
  accountant_ok: {Name: Бухгалтер согласовал, Type: bool, Default: "N"}
  reason:        {Name: Причина отказа, Type: text}
steps:
  - get_smart_item:
      id: project
      title: Роли из карточки проекта
      DynamicTypeId: "{{smart:Проекты}}"
      ReturnFields: ["{{field:Проекты/Бухгалтер проекта}}"]
      DynamicFilterFields:
        items: [[{object: Document, field: ID, operator: "=", value: "{=Document:{{field:Проект}}}"}, AND]]
  - parallel:
      branches:
        - - approve:
              id: lawyer
              Users: ["{{group:Юристы}}"]
              ApproveType: any
              Name: "Согласуйте заявку «{=Document:TITLE}» (юрист)"
              on_yes: [{set_var: {VariableValue: {lawyer_ok: "Y"}}}]
              on_no: [{set_var: {VariableValue: {reason: "{=@lawyer:Comments}"}}}]
        - - approve:
              id: accountant
              Users: ["{=@project:{{field:Проекты/Бухгалтер проекта}}}"]
              ApproveType: any
              Name: "Согласуйте заявку «{=Document:TITLE}» (бухгалтер проекта)"
              on_yes: [{set_var: {VariableValue: {accountant_ok: "Y"}}}]
              on_no: [{set_var: {VariableValue: {reason: "{=@accountant:Comments}"}}}]
  - if:
      branches:
        - title: Оба согласовали
          when: {propertyvariablecondition: [[lawyer_ok, "=", "Y", "0"], [accountant_ok, "=", "Y", "0"]]}
          steps: [{change_stage: {TargetStatus: "{{stage:Согласовано}}"}}]
        - title: Отказ
          else: true
          steps:
            - notify:
                MessageSite: "Заявка «{=Document:TITLE}» возвращена на доработку: {=Variable:reason}"
                MessageType: "4"
                MessageUserFrom: ["{=Document:ASSIGNED_BY_ID}"]
                MessageUserTo: ["{=Document:CREATED_BY}"]
            - change_stage: {TargetStatus: "{{stage:Доработка}}"}
```

- [ ] **Шаг 4: задача T10** — `tools/bpt-eval/tasks/T10-email/tz.md`

```markdown
Когда заявка переходит на стадию «Согласовано», отправьте клиенту письмо на e-mail: номер заявки и что
она согласована.
```

`tools/bpt-eval/tasks/T10-email/acceptance.yaml`

```yaml
task: T10
document: Заявки
checklist:
  - Отправить письмо на e-mail этим набором действий нельзя — действия нет в каталоге
  - Предложена замена или обходной путь
  - Откуда брать e-mail клиента — в «Заявках» нет клиента
  - {text: Как запускать процесс при переходе на стадию, required: false}
```

- [ ] **Шаг 5: эталоны пилотной тройки проходят** —
  `php tools/bpt-eval/eval.php reference all --run=pilot-1` → T01 и T04 «эталон прошёл», T10 «эталона
  нет — пропуск». Падает сценарий — чинить прогонщик, а не подгонять эталон. Импорт отклонил
  `get_smart_item` — разобрать текст ошибки ядра (`ValidateProperties`), поправить каталог и сборщик
  по TDD (отдельный коммит в `tools/bpt`), затем повторить. Задание бухгалтеру проекта не нашлось —
  проверить в `work/eval/pilot-1/_reference/T04/eval.bpt`, что `DynamicEntityFields` заполнен (задача 4),
  и в журнале процесса — что «Получить информацию» нашло «Проект Альфа».
- [ ] **Шаг 6: коммит** — «bpt-eval: прогонщик — ознакомление, запрос, задачи, поля, наблюдатели; задачи T04, T10».

---

### Задача 9: Агенты, чек-лист и отчёт; пилотный прогон

**Файлы:** создать `tools/bpt-eval/AGENT_PROMPT.md`, `tools/bpt-eval/CHECKLIST_PROMPT.md`,
`tools/bpt-eval/README.md`; изменить `tools/bpt-eval/eval.php` (команды `usage`, `report`).

- [ ] **Шаг 1:** `tools/bpt-eval/AGENT_PROMPT.md`

```markdown
# Задание агенту (подставить {{TASK}}, {{TZ}}, {{RUN}})

Ты работаешь в репозитории C:\OSPanel\home\bitrix-best-practices. Сначала прочитай навык
.claude/skills/building-bizproc-templates/SKILL.md и работай по нему.

Задача от менеджера проекта:

«{{TZ}}»

Снимок портала — work/eval/{{RUN}}/portal.yaml. Это снимок настоящего тестового стенда (коробка), свой
снимок делать не нужно.

Результат положи в work/eval/{{RUN}}/{{TASK}}/ (вместо work/bizproc/<слаг>/ из навыка): process.bizproc.yaml,
process.bpt, schema.md, README.md.

Ограничения:
- не запускай Docker и не обращайся к стенду;
- не меняй файлы репозитория вне этой папки, не делай git-коммитов;
- уточнить у заказчика нельзя — допущения и вопросы запиши в README.

В конце — краткий отчёт: что сделал, допущения, вопросы.
```

`tools/bpt-eval/CHECKLIST_PROMPT.md`

```markdown
# Задание проверяющему чек-лист (подставить {{README_PATH}}, {{ITEMS}}, {{OUT}})

Прочитай README решения: {{README_PATH}}. Для каждого пункта ниже ответь, поднят ли он в README —
как вопрос заказчику, допущение или принятое решение. Засчитывай только явное упоминание по смыслу;
цитата из README обязательна (до 200 символов, дословно), без цитаты — raised: false.

Пункты:
{{ITEMS}}

Запиши в {{OUT}} только JSON:
{"items": [{"text": "<пункт как есть>", "raised": true, "quote": "<цитата>"}]}
Больше ничего не делай и не меняй.
```

- [ ] **Шаг 2:** в `eval.php` добавить команды:

```php
        case 'usage':
            $task = (string) ($positional[0] ?? '');
            $file = "{$runDir}/agents.json";
            $agents = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
            $agents[$task] = ['tokens' => (int) ($opts['tokens'] ?? 0), 'tool_uses' => (int) ($opts['tools'] ?? 0),
                'duration_ms' => (int) ($opts['ms'] ?? 0)];
            file_put_contents($file, json_encode($agents, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            exit(0);
        case 'report':
            $report = Report::fromRunDir($runDir, __DIR__ . '/tasks');
            file_put_contents("{$runDir}/report.md", $report->toMarkdown());
            echo $report->toMarkdown();
            exit(0);
```

- [ ] **Шаг 3:** `tools/bpt-eval/README.md` — назначение, команды (`prepare`, `reference`, `check`, `usage`,
  `report`), порядок прогона (prepare → reference all → агенты по `AGENT_PROMPT.md` по 3 параллельно →
  check по каждой задаче → чек-лист по `CHECKLIST_PROMPT.md` → usage → report → разбор категорий в
  `result.json` → report), где лежат результаты (`work/eval/<прогон>/`, вне git), ограничения (облако,
  REST, роботы не входят; один прогон на задачу — порог грубый).
- [ ] **Шаг 4: тесты хоста и `tools/bpt` зелёные; коммит** — «bpt-eval: задания агентам, отчёт, README».
- [ ] **Шаг 5: пилотный прогон (оркестратор — эта сессия).**
  1. `php tools/bpt-eval/eval.php prepare --run=pilot-1`.
  2. Три фоновых субагента (general-purpose) — T01, T04, T10, задание из `AGENT_PROMPT.md` с подстановкой
     `tz.md`. По уведомлению о завершении: `php tools/bpt-eval/eval.php usage <задача> --run=pilot-1
     --tokens=… --tools=… --ms=…`.
  3. `php tools/bpt-eval/eval.php check <задача> --run=pilot-1` для каждой задачи.
  4. Проверяющий чек-лист — субагент с `model: haiku`, задание из `CHECKLIST_PROMPT.md`,
     `{{OUT}}` = `work/eval/pilot-1/<задача>/checklist.json`.
  5. Разбор провалов: в `result.json` проставить `category` (`spec`|`tool`|`skill`|`harness`) и `note`.
     `harness` — починить и перегнать `check` без повторного агента.
  6. `php tools/bpt-eval/eval.php report --run=pilot-1` → `work/eval/pilot-1/report.md`.
- [ ] **Шаг 6: контрольная точка с человеком** — показать отчёт пилота; решить, что правим до основного
  прогона (навык, утилита, прогонщик). Исправления — отдельными задачами по TDD (навык — по
  superpowers:writing-skills), с повторным `check` упавших задач.

---

### Задача 10: Остальные задачи T02, T03, T05–T09 с эталонами

**Файлы:** создать по три файла (`tz.md`, `acceptance.yaml`, `reference.bizproc.yaml`) в
`tools/bpt-eval/tasks/T02-po-summe/`, `T03-posledovatelno/`, `T05-dorabotka/`, `T06-oznakomlenie/`,
`T07-zapros/`, `T08-zadacha/`, `T09-polya/`.

**Интерфейсы:** формат — задача 1 (`item` сценария — поверх `item` задачи), роли — `roles.yaml`
(задача 2), ответы на задания — по намерению (задача 7), снимок с `related` (задача 4). ТЗ — по таблице
DESIGN.md §4; сценарии проверяют только однозначное, остальное — в чек-листе. Эталоны нарочно разные по
механизму: T03 — отказы в каждой ветке, T05 — цикл со счётчиком, T06 — комментарий в карточке вместо
записи в историю (так прогонщик проверяет оба пути «истории»).

Порядок для каждой задачи: три файла → `php tools/bpt-eval/eval.php reference <задача> --run=main-1` →
«эталон прошёл». Не прошёл — чинить прогонщик или формат, а не подгонять эталон под прогонщик.
Импорт отклонён — ошибка ядра → каталог и сборщик по TDD, отдельным коммитом в `tools/bpt`.

- [ ] **Шаг 1: T02 — до 100 тысяч руководитель проекта, выше — финансовый директор**

`tz.md`:

```markdown
Для «Заявок»: при создании заявку согласует руководитель проекта, если сумма до 100 тысяч, а если
больше — финансовый директор. Согласовали — стадия «Согласовано», отказали — «Доработка».
```

`acceptance.yaml`:

```yaml
task: T02
document: Заявки
start: create
item:
  Сумма к оплате: 50000
  Проект: Проект Альфа
scenarios:
  - name: до 100 тысяч — руководитель проекта утвердил
    steps:
      - {task_for: Руководитель проекта, do: approve, comment: В пределах бюджета проекта}
    expect:
      stage: Согласовано
      no_task_for: [Финансовый отдел]
      process: completed
  - name: больше 100 тысяч — финансовый директор отклонил
    item: {Сумма к оплате: 500000}
    steps:
      - {task_for: Финансовый отдел, do: reject, comment: Превышен лимит квартала}
    expect:
      stage: Доработка
      no_task_for: [Руководитель проекта]
      process: completed
checklist:
  - Граница — ровно 100 000 согласует руководитель проекта или финансовый директор
  - Что делать, если сумма не заполнена
  - Повторное согласование после доработки
  - {text: Финансовый директор — конкретный сотрудник или группа «Финансовый отдел», required: false}
```

Роль «Финансовый отдел» — группа, в ней один финансовый директор: подходит и выбор сотрудника, и выбор группы.

`reference.bizproc.yaml`:

```yaml
# Эталон T02: условие по сумме; руководитель — из карточки проекта, выше порога — группа.
bizproc: 1
name: EVAL T02 эталон
steps:
  - if:
      title: Сумма до 100 000?
      branches:
        - title: До 100 000 включительно
          when: {fieldcondition: [["{{field:Сумма к оплате}}", "<=", "100000", "0"]]}
          steps:
            - get_smart_item:
                id: project
                title: Руководитель из карточки проекта
                DynamicTypeId: "{{smart:Проекты}}"
                ReturnFields: ["{{field:Проекты/Руководитель проекта}}"]
                DynamicFilterFields:
                  items: [[{object: Document, field: ID, operator: "=", value: "{=Document:{{field:Проект}}}"}, AND]]
            - approve:
                id: pm
                Users: ["{=@project:{{field:Проекты/Руководитель проекта}}}"]
                ApproveType: any
                Name: "Согласуйте заявку «{=Document:TITLE}» на {=Document:{{field:Сумма к оплате}}}"
                on_yes: [{change_stage: {TargetStatus: "{{stage:Согласовано}}"}}]
                on_no: [{change_stage: {TargetStatus: "{{stage:Доработка}}"}}]
        - title: Больше 100 000
          else: true
          steps:
            - approve:
                id: finance
                Users: ["{{group:Финансовый отдел}}"]
                ApproveType: any
                Name: "Согласуйте заявку «{=Document:TITLE}» на {=Document:{{field:Сумма к оплате}}}"
                on_yes: [{change_stage: {TargetStatus: "{{stage:Согласовано}}"}}]
                on_no: [{change_stage: {TargetStatus: "{{stage:Доработка}}"}}]
```

`php tools/bpt-eval/eval.php reference T02 --run=main-1` → «T02: эталон прошёл».

- [ ] **Шаг 2: T03 — последовательно юрист, руководитель проекта, генеральный директор**

`tz.md`:

```markdown
Заявку после создания согласуют по очереди: юрист, затем руководитель проекта, затем генеральный
директор. Все согласовали — стадия «Согласовано». Если кто-то отказал — вернуть на «Доработку» и
написать инициатору причину отказа.
```

`acceptance.yaml`:

```yaml
task: T03
document: Заявки
start: create
item:
  Сумма к оплате: 300000
  Проект: Проект Альфа
scenarios:
  - name: все согласовали
    steps:
      - {task_for: Юристы, do: approve, comment: Юридически чисто}
      - {task_for: Руководитель проекта, do: approve, comment: В бюджете проекта}
      - {task_for: Генеральный директор, do: approve, comment: Согласовано}
    expect:
      stage: Согласовано
      process: completed
  - name: руководитель проекта отказал
    steps:
      - {task_for: Юристы, do: approve, comment: Юридически чисто}
      - {task_for: Руководитель проекта, do: reject, comment: Смета не согласована с заказчиком}
    expect:
      stage: Доработка
      notify: [{to: Инициатор, contains: Смета не согласована с заказчиком}]
      no_task_for: [Генеральный директор]
      process: completed
checklist:
  - Кому писать причину — создателю заявки или ответственному
  - Сообщать ли об отказе тем, кто уже согласовал
  - Повторное согласование после доработки
  - {text: Юрист — группа «Юристы» или конкретный сотрудник, required: false}
```

`reference.bizproc.yaml`:

```yaml
# Эталон T03: вложенные утверждения; отказ на любом шаге — причина инициатору и «Доработка».
bizproc: 1
name: EVAL T03 эталон
steps:
  - get_smart_item:
      id: project
      title: Руководитель из карточки проекта
      DynamicTypeId: "{{smart:Проекты}}"
      ReturnFields: ["{{field:Проекты/Руководитель проекта}}"]
      DynamicFilterFields:
        items: [[{object: Document, field: ID, operator: "=", value: "{=Document:{{field:Проект}}}"}, AND]]
  - approve:
      id: lawyer
      Users: ["{{group:Юристы}}"]
      ApproveType: any
      Name: "Согласуйте заявку «{=Document:TITLE}» (юрист)"
      on_yes:
        - approve:
            id: pm
            Users: ["{=@project:{{field:Проекты/Руководитель проекта}}}"]
            ApproveType: any
            Name: "Согласуйте заявку «{=Document:TITLE}» (руководитель проекта)"
            on_yes:
              - approve:
                  id: ceo
                  Users: ["{{user:Генеральный директор Тест}}"]
                  ApproveType: any
                  Name: "Согласуйте заявку «{=Document:TITLE}» (генеральный директор)"
                  on_yes: [{change_stage: {TargetStatus: "{{stage:Согласовано}}"}}]
                  on_no:
                    - notify:
                        MessageSite: "Заявка «{=Document:TITLE}» на доработке. Генеральный директор: {=@ceo:Comments}"
                        MessageType: "4"
                        MessageUserFrom: ["{=Document:ASSIGNED_BY_ID}"]
                        MessageUserTo: ["{=Document:CREATED_BY}"]
                    - change_stage: {TargetStatus: "{{stage:Доработка}}"}
            on_no:
              - notify:
                  MessageSite: "Заявка «{=Document:TITLE}» на доработке. Руководитель проекта: {=@pm:Comments}"
                  MessageType: "4"
                  MessageUserFrom: ["{=Document:ASSIGNED_BY_ID}"]
                  MessageUserTo: ["{=Document:CREATED_BY}"]
              - change_stage: {TargetStatus: "{{stage:Доработка}}"}
      on_no:
        - notify:
            MessageSite: "Заявка «{=Document:TITLE}» на доработке. Юрист: {=@lawyer:Comments}"
            MessageType: "4"
            MessageUserFrom: ["{=Document:ASSIGNED_BY_ID}"]
            MessageUserTo: ["{=Document:CREATED_BY}"]
        - change_stage: {TargetStatus: "{{stage:Доработка}}"}
```

`php tools/bpt-eval/eval.php reference T03 --run=main-1` → «T03: эталон прошёл».

- [ ] **Шаг 3: T05 — доработка в цикле, после трёх отказов «Провал»**

`tz.md`:

```markdown
Заявку после создания согласует руководитель проекта. Если он отказал — инициатор описывает, что
исправил, и заявка снова уходит руководителю проекта. После трёх отказов — стадия «Провал».
Согласовал — стадия «Согласовано».
```

`acceptance.yaml`:

```yaml
task: T05
document: Заявки
start: create
item:
  Сумма к оплате: 120000
  Проект: Проект Альфа
scenarios:
  - name: согласовано со второго раза
    steps:
      - {task_for: Руководитель проекта, do: reject, comment: Нет сметы}
      - {task_for: Инициатор, do: provide, comment: Исправил, values: {Что исправлено: Приложил смету}}
      - {task_for: Руководитель проекта, do: approve, comment: Теперь всё есть}
    expect:
      stage: Согласовано
      process: completed
  - name: три отказа
    steps:
      - {task_for: Руководитель проекта, do: reject, comment: Нет сметы}
      - {task_for: Инициатор, do: provide, comment: Исправил, values: {Что исправлено: Приложил смету}}
      - {task_for: Руководитель проекта, do: reject, comment: Смета неполная}
      - {task_for: Инициатор, do: provide, comment: Исправил, values: {Что исправлено: Дополнил смету}}
      - {task_for: Руководитель проекта, do: reject, comment: Всё ещё неполная}
    expect:
      stage: Провал
      process: completed
checklist:
  - Кто описывает исправления — создатель заявки или ответственный
  - Как считать попытки — три отказа подряд или всего
  - Что делать, если инициатор не ответил на запрос
  - {text: Где хранить описание исправлений — в поле заявки или только в задании, required: false}
```

`reference.bizproc.yaml`:

```yaml
# Эталон T05: цикл «согласование → доработка» со счётчиком отказов.
bizproc: 1
name: EVAL T05 эталон
variables:
  rejections: {Name: Отказов, Type: int, Default: "0"}
  approved:   {Name: Согласовано, Type: bool, Default: "N"}
  fixes:      {Name: Что исправлено, Type: text}
steps:
  - get_smart_item:
      id: project
      title: Руководитель из карточки проекта
      DynamicTypeId: "{{smart:Проекты}}"
      ReturnFields: ["{{field:Проекты/Руководитель проекта}}"]
      DynamicFilterFields:
        items: [[{object: Document, field: ID, operator: "=", value: "{=Document:{{field:Проект}}}"}, AND]]
  - while:
      title: Пока не согласовано и отказов меньше трёх
      when: {propertyvariablecondition: [[approved, "=", "N", "0"], [rejections, "<", "3", "0"]]}
      steps:
        - approve:
            id: pm
            Users: ["{=@project:{{field:Проекты/Руководитель проекта}}}"]
            ApproveType: any
            Name: "Согласуйте заявку «{=Document:TITLE}»"
            Description: "Исправления инициатора: {=Variable:fixes}"
            on_yes: [{set_var: {VariableValue: {approved: "Y"}}}]
            on_no:
              - set_var: {VariableValue: {rejections: "={=Variable:rejections} + 1"}}
              - if:
                  title: Ещё есть попытки?
                  branches:
                    - title: Да — на доработку
                      when: {propertyvariablecondition: [[rejections, "<", "3", "0"]]}
                      steps:
                        - request_info:
                            id: fix
                            Users: ["{=Document:CREATED_BY}"]
                            Name: "Доработайте заявку «{=Document:TITLE}»: {=@pm:Comments}"
                            RequestedInformation:
                              - {Name: fixes, Title: Что исправлено, Type: text, Required: true}
                    - title: Нет — третий отказ
                      else: true
                      steps: []
  - if:
      title: Итог
      branches:
        - title: Согласовано
          when: {propertyvariablecondition: [[approved, "=", "Y", "0"]]}
          steps: [{change_stage: {TargetStatus: "{{stage:Согласовано}}"}}]
        - title: Три отказа
          else: true
          steps: [{change_stage: {TargetStatus: "{{stage:Провал}}"}}]
```

Коды переменных — не `y`/`n`/`on`/`off`: YAML 1.1 читает такие ключи как логические значения.
`php tools/bpt-eval/eval.php reference T05 --run=main-1` → «T05: эталон прошёл».

- [ ] **Шаг 4: T06 — ознакомление юридического отдела**

`tz.md`:

```markdown
Когда создаётся заявка, юридический отдел должен с ней ознакомиться. В историю заявки запишите,
кто ознакомился.
```

`acceptance.yaml`:

```yaml
task: T06
document: Заявки
start: create
item:
  Сумма к оплате: 80000
  Проект: Проект Альфа
scenarios:
  - name: юрист ознакомился
    steps:
      - {task_for: Юридический отдел, do: review, comment: Ознакомлен}
    expect:
      history_contains: [Юрист Тест]
      process: completed
checklist:
  - Ознакомиться должны все сотрудники отдела или достаточно одного
  - «История» — запись в историю CRM или комментарий в карточке
  - {text: Срок на ознакомление, required: false}
```

«Юрист Тест» — имя из снимка стенда (синтетический сотрудник, не персональные данные).

`reference.bizproc.yaml`:

```yaml
# Эталон T06: ознакомление отдела; кто ознакомился — комментарием в карточке (второй путь «истории»).
bizproc: 1
name: EVAL T06 эталон
steps:
  - review:
      id: review
      Users: ["{{group:Юридический отдел}}"]
      ApproveType: any
      Name: "Ознакомьтесь с заявкой «{=Document:TITLE}»"
  - timeline_comment:
      CommentText: "С заявкой ознакомился: {=@review:LastReviewer > printable}"
      CommentUser: ["{=@review:LastReviewer}"]
```

`php tools/bpt-eval/eval.php reference T06 --run=main-1` → «T06: эталон прошёл».

- [ ] **Шаг 5: T07 — номер счёта и дата оплаты от бухгалтера проекта**

`tz.md`:

```markdown
Когда создаётся заявка, бухгалтер проекта вносит номер счёта и дату оплаты. Их нужно записать в поля
заявки и перевести заявку на стадию «Оплачено».
```

`acceptance.yaml`:

```yaml
task: T07
document: Заявки
start: create
item:
  Сумма к оплате: 90000
  Проект: Проект Альфа
scenarios:
  - name: бухгалтер внёс данные
    steps:
      - task_for: Бухгалтер проекта
        do: provide
        comment: Оплачено
        values: {Номер счёта: СЧ-17, Дата оплаты: "01.10.2026"}
    expect:
      fields: {Номер счёта: СЧ-17, Дата оплаты: "01.10.2026"}
      stage: Оплачено
      process: completed
checklist:
  - Обязательны ли номер счёта и дата оплаты
  - Что делать, если бухгалтер не может внести данные или отказывается
  - {text: Срок на внесение данных, required: false}
```

`reference.bizproc.yaml`:

```yaml
# Эталон T07: запрос информации у бухгалтера проекта → поля заявки → «Оплачено».
bizproc: 1
name: EVAL T07 эталон
variables:
  invoice_no: {Name: Номер счёта, Type: string}
  paid_on:    {Name: Дата оплаты, Type: date}
steps:
  - get_smart_item:
      id: project
      title: Бухгалтер из карточки проекта
      DynamicTypeId: "{{smart:Проекты}}"
      ReturnFields: ["{{field:Проекты/Бухгалтер проекта}}"]
      DynamicFilterFields:
        items: [[{object: Document, field: ID, operator: "=", value: "{=Document:{{field:Проект}}}"}, AND]]
  - request_info:
      id: payment
      Users: ["{=@project:{{field:Проекты/Бухгалтер проекта}}}"]
      Name: "Внесите номер счёта и дату оплаты по заявке «{=Document:TITLE}»"
      RequestedInformation:
        - {Name: invoice_no, Title: Номер счёта, Type: string, Required: true}
        - {Name: paid_on, Title: Дата оплаты, Type: date, Required: true}
  - set_field:
      FieldValue:
        "{{field:Номер счёта}}": "{=Variable:invoice_no}"
        "{{field:Дата оплаты}}": "{=Variable:paid_on}"
  - change_stage: {TargetStatus: "{{stage:Оплачено}}"}
```

`php tools/bpt-eval/eval.php reference T07 --run=main-1` → «T07: эталон прошёл».

- [ ] **Шаг 6: T08 — задача сметчику со сроком три рабочих дня**

`tz.md`:

```markdown
При создании заявки поставьте сметчику проекта задачу «Подготовить смету» со сроком три рабочих дня.
```

`acceptance.yaml`:

```yaml
task: T08
document: Заявки
start: create
item:
  Сумма к оплате: 70000
  Проект: Проект Альфа
scenarios:
  - name: задача поставлена
    steps: []
    expect:
      task_created: [{responsible: Сметчик, title_contains: смет, deadline_workdays: 3}]
checklist:
  - Кто постановщик задачи
  - Ждать ли выполнения задачи и что потом делать с заявкой
  - {text: Три рабочих дня от момента создания или до конца третьего рабочего дня, required: false}
```

`reference.bizproc.yaml`:

```yaml
# Эталон T08: задача сметчику из карточки проекта, срок — через три рабочих дня.
bizproc: 1
name: EVAL T08 эталон
steps:
  - get_smart_item:
      id: project
      title: Сметчик из карточки проекта
      DynamicTypeId: "{{smart:Проекты}}"
      ReturnFields: ["{{field:Проекты/Сметчик}}"]
      DynamicFilterFields:
        items: [[{object: Document, field: ID, operator: "=", value: "{=Document:{{field:Проект}}}"}, AND]]
  - task:
      id: estimate
      Fields:
        TITLE: "Подготовить смету по заявке «{=Document:TITLE}»"
        DESCRIPTION: "Сумма заявки: {=Document:{{field:Сумма к оплате}}}"
        CREATED_BY: "{=Document:CREATED_BY}"
        RESPONSIBLE_ID: "{=@project:{{field:Проекты/Сметчик}}}"
        DEADLINE: "=addworkdays({=System:Now}, 3)"
```

`php tools/bpt-eval/eval.php reference T08 --run=main-1` → «T08: эталон прошёл».

- [ ] **Шаг 7: T09 — «Согласовано кем» и руководитель в наблюдателях**

`tz.md`:

```markdown
Заявку утверждает руководитель проекта. После утверждения запишите в поле «Согласовано кем», кто
утвердил, и добавьте руководителя в наблюдатели заявки.
```

`acceptance.yaml`:

```yaml
task: T09
document: Заявки
start: create
item:
  Сумма к оплате: 60000
  Проект: Проект Альфа
scenarios:
  - name: руководитель проекта утвердил
    steps:
      - {task_for: Руководитель проекта, do: approve, comment: Согласовано}
    expect:
      fields: {Согласовано кем: Руководитель проекта}
      observers: [Руководитель проекта]
      process: completed
checklist:
  - Какого руководителя добавить в наблюдатели — руководителя проекта или руководителя отдела
  - Что делать при отказе
  - {text: Добавлять к наблюдателям или заменять их, required: false}
```

`reference.bizproc.yaml`:

```yaml
# Эталон T09: после утверждения — поле «Согласовано кем» и руководитель проекта в наблюдателях.
bizproc: 1
name: EVAL T09 эталон
steps:
  - get_smart_item:
      id: project
      title: Руководитель из карточки проекта
      DynamicTypeId: "{{smart:Проекты}}"
      ReturnFields: ["{{field:Проекты/Руководитель проекта}}"]
      DynamicFilterFields:
        items: [[{object: Document, field: ID, operator: "=", value: "{=Document:{{field:Проект}}}"}, AND]]
  - approve:
      id: pm
      Users: ["{=@project:{{field:Проекты/Руководитель проекта}}}"]
      ApproveType: any
      Name: "Утвердите заявку «{=Document:TITLE}»"
      on_yes:
        - set_field:
            FieldValue: {"{{field:Согласовано кем}}": "{=@pm:LastApprover}"}
        - observers:
            ActionOnObservers: add
            Observers: ["{=@project:{{field:Проекты/Руководитель проекта}}}"]
      on_no:
        - change_stage: {TargetStatus: "{{stage:Доработка}}"}
```

`php tools/bpt-eval/eval.php reference T09 --run=main-1` → «T09: эталон прошёл».

- [ ] **Шаг 8: все эталоны разом** — `php tools/bpt-eval/eval.php reference all --run=main-1` → девять
  «эталон прошёл» и «T10: эталона нет — пропуск»; код выхода 0.
- [ ] **Шаг 9: коммит** — «bpt-eval: задачи T02, T03, T05–T09 с эталонами».

---

### Задача 11: Реальные ТЗ, основной прогон, итоги

- [ ] **Шаг 1:** реальные ТЗ команды (3–5) — по одной папке `tasks/R1-…` … `tasks/R5-…`: `tz.md`
  (обезличенный пересказ), `acceptance.yaml`, эталон. В git — только обезличенные; сомнительное — в
  `work/eval/` без коммита. Эталоны проходят (`reference`).
- [ ] **Шаг 2: основной прогон** `main-1`: `prepare` → `reference all` → агенты по всем задачам, кроме
  пилотных (по 3 параллельно) → `check` → чек-лист → `usage` → разбор категорий → `report`.
- [ ] **Шаг 3:** исправления по слабым местам — отдельными коммитами по TDD; упавшие задачи — повторно
  (`main-2`), отчёт «до/после».
- [ ] **Шаг 4:** итог — в `wiki/modules/bizproc/pattern-bizproc-ai-assisted-generation.md` (шаг 8: доля,
  порог, слабые места, дата, прогон), запись в `log.md`, при достижении порога — предложение перевести
  паттерн из черновика; страница-паттерн «Роли из карточки проекта» по практике команды и результатам.
  Коммит.
