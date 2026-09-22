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
