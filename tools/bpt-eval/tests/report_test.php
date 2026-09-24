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

test('Отчёт: проверяющий обернул пункт в кавычки — пункт всё равно засчитан', function () {
    // Проверяющий — модель: кавычки вокруг текста и внутри него у него «плавают».
    // Сверка побуквенно давала «не пройдена» у задачи с полным чек-листом (прогон main-7).
    $acc = Acceptance::fromArray(['task' => 'T06', 'document' => 'Заявки',
        'scenarios' => [['name' => 's', 'steps' => [], 'expect' => ['stage' => 'Клиент']]],
        'checklist' => ['«История» — запись в историю CRM']], 'T06');
    $checklists = ['T06' => ['items' => [['text' => '«„История" — запись в историю CRM»', 'raised' => true, 'quote' => '…']]]];
    $report = Report::fromData(['T06' => ['task' => 'T06', 'compile' => 'ok', 'import' => 'ok',
        'scenarios' => [['ok' => true]], 'reason' => '', 'category' => '', 'note' => '']], $checklists, ['T06' => $acc], [], 'r');
    assertTrue($report->rows()['T06']['passed'], 'кавычки не мешают сопоставлению');
});

test('Отчёт: сценарии не прогнаны — задача не пройдена', function () {
    $acc = Acceptance::fromArray(['task' => 'T05', 'document' => 'Заявки',
        'scenarios' => [['name' => 's', 'steps' => [], 'expect' => ['stage' => 'Клиент']]]], 'T05');
    $report = Report::fromData(['T05' => ['task' => 'T05', 'compile' => 'ok', 'import' => 'ok', 'scenarios' => [],
        'reason' => 'константы без сопоставления: boss', 'category' => '', 'note' => '']], [], ['T05' => $acc], [], 'r');
    assertTrue(!$report->rows()['T05']['passed'], 'нет результатов сценариев — не пройдена');
});

test('Отчёт: битый result.json — EvalException с путём к файлу', function () {
    $runDir = tmpPath('');
    mkdir("{$runDir}/T01", 0777, true);
    $file = "{$runDir}/T01/result.json";
    file_put_contents($file, '{испорченный json');
    try {
        assertThrows(fn () => Report::fromRunDir($runDir, $runDir), $file, 'ошибка называет путь к битому файлу');
    } finally {
        unlink($file);
        rmdir("{$runDir}/T01");
        rmdir($runDir);
    }
});

test('Отчёт: битый checklist.json — не роняет отчёт, задача не пройдена, note называет причину', function () {
    $runDir = tmpPath('');
    $tasksDir = tmpPath('');
    mkdir("{$runDir}/T99", 0777, true);
    mkdir("{$tasksDir}/T99-fake", 0777, true);
    file_put_contents("{$tasksDir}/T99-fake/acceptance.yaml", SpecReader::dump([
        'task' => 'T99', 'document' => 'Заявки',
        'scenarios' => [['name' => 's', 'steps' => [], 'expect' => ['stage' => 'Клиент']]],
        'checklist' => ['Обязательный пункт'],
    ]));
    file_put_contents("{$runDir}/T99/result.json", json_encode(['task' => 'T99', 'compile' => 'ok',
        'import' => 'ok', 'scenarios' => [['ok' => true]], 'reason' => '', 'category' => '', 'note' => '']));
    file_put_contents("{$runDir}/T99/checklist.json", '{испорченный json');
    try {
        $row = Report::fromRunDir($runDir, $tasksDir)->rows()['T99'];
        assertTrue(!$row['passed'], 'обязательный пункт чек-листа не мог быть поднят — задача не пройдена');
        assertSame('1/1', $row['scenarios'], 'сценарии сами по себе не пострадали');
        assertSame('0/1', $row['checklist'], 'битый чек-лист — 0 поднятых, а не сбой');
        assertTrue(str_contains($row['note'], 'чек-лист не разобран'), 'note называет причину');
    } finally {
        unlink("{$runDir}/T99/result.json");
        unlink("{$runDir}/T99/checklist.json");
        rmdir("{$runDir}/T99");
        rmdir($runDir);
        unlink("{$tasksDir}/T99-fake/acceptance.yaml");
        rmdir("{$tasksDir}/T99-fake");
        rmdir($tasksDir);
    }
});

test('Отчёт: checklist.json нет вовсе — «чек-лист не проверен», задача видна, но не в доле', function () {
    $runDir = tmpPath('');
    $tasksDir = tmpPath('');
    mkdir("{$runDir}/T98", 0777, true);
    mkdir("{$tasksDir}/T98-fake", 0777, true);
    file_put_contents("{$tasksDir}/T98-fake/acceptance.yaml", SpecReader::dump([
        'task' => 'T98', 'document' => 'Заявки',
        'scenarios' => [['name' => 's', 'steps' => [], 'expect' => ['stage' => 'Клиент']]],
        'checklist' => ['Обязательный пункт'],
    ]));
    file_put_contents("{$runDir}/T98/result.json", json_encode(['task' => 'T98', 'compile' => 'ok',
        'import' => 'ok', 'scenarios' => [['ok' => true]], 'reason' => '', 'category' => '', 'note' => '']));
    // checklist.json нарочно не создаём — шаг «чек-лист» из README.md как будто пропустили
    try {
        $report = Report::fromRunDir($runDir, $tasksDir);
        $row = $report->rows()['T98'];
        assertTrue(!$row['passed'], 'обязательный пункт чек-листа не подтверждён — задача не пройдена');
        assertSame('1/1', $row['scenarios'], 'сценарии сами по себе не пострадали');
        assertTrue(str_contains($row['note'], 'чек-лист не проверен'), 'note называет причину');
        assertTrue(!$row['counted'], 'как harness: задача не учитывается в доле пройденных');
        assertSame(0.0, $report->passRate(), 'единственная задача прогона — вне доли, знаменатель пуст');
        assertTrue(str_contains($report->toMarkdown(), '| T98 |'), 'задача всё равно видна в таблице отчёта');
        assertTrue(str_contains($report->toMarkdown(), 'Не учтено задач без данных чек-листа: 1'), 'причина исключения из доли названа');
    } finally {
        unlink("{$runDir}/T98/result.json");
        rmdir("{$runDir}/T98");
        rmdir($runDir);
        unlink("{$tasksDir}/T98-fake/acceptance.yaml");
        rmdir("{$tasksDir}/T98-fake");
        rmdir($tasksDir);
    }
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
