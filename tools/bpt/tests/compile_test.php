<?php
/** Тесты сборщика: спецификация → дерево действий. minimalSpec() — в tests/support.php. */

declare(strict_types=1);

test('Сборщик: один шаг превращается в дерево', function () {
    $r = (new Compiler(Catalog::load()))->compile(minimalSpec());
    assertSame([], $r['errors']);
    $root = $r['bpt']['TEMPLATE'][0];
    assertSame('SequentialWorkflowActivity', $root['Type']);
    assertSame('Bizproc Automation template', $root['Properties']['Title']);
    assertSame(2, $r['bpt']['VERSION']);
    $step = $root['Children'][0];
    assertSame('CrmChangeStatusActivity', $step['Type']);
    assertSame('DT1000_10:CLIENT', $step['Properties']['TargetStatus']);
    assertSame([], $step['Properties']['ModifiedBy']);              // значение по умолчанию
    assertSame('Сменить стадию', $step['Properties']['Title']);     // заголовок по умолчанию
    assertSame('', $step['Properties']['EditorComment']);
    assertSame('Y', $step['Activated']);
    assertSame([], $step['Children']);
});

test('Сборщик: имена стабильны между прогонами', function () {
    $a = (new Compiler(Catalog::load()))->compile(minimalSpec());
    $b = (new Compiler(Catalog::load()))->compile(minimalSpec());
    assertSame($a['bpt'], $b['bpt']);
    assertTrue((bool) preg_match('/^A\d+_\d+_\d+_\d+$/', $a['bpt']['TEMPLATE'][0]['Children'][0]['Name']),
        'формат имени действия');
});

test('Сборщик: служебные ключи', function () {
    $r = (new Compiler(Catalog::load()))->compile(minimalSpec([[
        'change_stage' => ['id' => 'st', 'title' => 'Свой заголовок', 'off' => true,
                           'comment' => 'заметка', 'TargetStatus' => 'DT1000_10:CLIENT'],
    ]]));
    assertSame([], $r['errors']);
    $step = $r['bpt']['TEMPLATE'][0]['Children'][0];
    assertSame('Свой заголовок', $step['Properties']['Title']);
    assertSame('заметка', $step['Properties']['EditorComment']);
    assertSame('N', $step['Activated']);
});

test('Сборщик: имя действия можно задать явно', function () {
    $r = (new Compiler(Catalog::load()))->compile(minimalSpec([[
        'change_stage' => ['name' => 'A1_2_3_4', 'TargetStatus' => 'DT1000_10:CLIENT'],
    ]]));
    assertSame('A1_2_3_4', $r['bpt']['TEMPLATE'][0]['Children'][0]['Name']);
});

test('Сборщик: переменные и константы нормализуются', function () {
    $spec = minimalSpec();
    $spec['variables'] = ['amount' => ['Name' => 'Сумма', 'Type' => 'double', 'Required' => true, 'Multiple' => false]];
    $spec['constants'] = ['link' => ['Name' => 'Ссылка', 'Type' => 'string', 'Default' => 'https://example.com']];
    $r = (new Compiler(Catalog::load()))->compile($spec);
    assertSame([], $r['errors']);
    assertSame('1', $r['bpt']['VARIABLES']['amount']['Required']);
    assertSame('0', $r['bpt']['VARIABLES']['amount']['Multiple']);
    assertSame('https://example.com', $r['bpt']['CONSTANTS']['link']['Default']);
});

test('Сборщик: значения из YAML приводятся к строкам', function () {
    $r = (new Compiler(Catalog::load()))->compile(minimalSpec([[
        'task' => ['Fields' => ['TITLE' => 'Задача', 'RESPONSIBLE_ID' => 'user_42', 'PRIORITY' => 1],
                   'HoldToClose' => false],
    ]]));
    assertSame([], $r['errors']);
    $props = $r['bpt']['TEMPLATE'][0]['Children'][0]['Properties'];
    assertSame('1', $props['Fields']['PRIORITY']);
    assertSame('N', $props['HoldToClose']);
    assertSame('Y', $props['AUTO_LINK_TO_CRM_ENTITY']);   // значение по умолчанию
});

test('Сборщик: неизвестный тип и свойство — ошибки', function () {
    $c = new Compiler(Catalog::load());
    assertTrue((bool) $c->compile(minimalSpec([['нет_такого' => []]]))['errors'], 'неизвестный тип');
    $errors = implode(' ', $c->compile(minimalSpec([['change_stage' => ['TargetStatus' => 'X', 'Выдумка' => 1]]]))['errors']);
    assertTrue(str_contains($errors, 'Выдумка'), "неизвестное свойство: {$errors}");
    assertTrue(str_contains($errors, 'steps[0]'), "путь до шага: {$errors}");
});

test('Сборщик: нет обязательного свойства', function () {
    $errors = implode(' ', (new Compiler(Catalog::load()))->compile(minimalSpec([['change_stage' => []]]))['errors']);
    assertTrue(str_contains($errors, 'TargetStatus'), "обязательное свойство: {$errors}");
});

test('Сборщик: запрещённое действие не собирается', function () {
    $errors = implode(' ', (new Compiler(Catalog::load()))->compile(minimalSpec([['php_code' => ['ExecuteCode' => 'echo 1;']]]))['errors']);
    assertTrue(str_contains($errors, 'PHP-кода'), "запрещённое действие: {$errors}");
});

test('Сборщик: шаг должен быть объектом с одним действием', function () {
    $errors = implode(' ', (new Compiler(Catalog::load()))->compile(minimalSpec([['a' => [], 'b' => []]]))['errors']);
    assertTrue(str_contains($errors, 'одним действием'), "форма шага: {$errors}");
});

test('Сборщик: условие с ветками', function () {
    $r = (new Compiler(Catalog::load()))->compile(minimalSpec([['if' => ['branches' => [
        ['title' => 'Да', 'when' => ['fieldcondition' => [['UF_X', '!empty', '', '0']]],
         'steps' => [['change_stage' => ['TargetStatus' => 'DT1000_10:CLIENT']]]],
        ['title' => 'Иначе', 'else' => true, 'steps' => []],
    ]]]]));
    assertSame([], $r['errors']);
    $if = $r['bpt']['TEMPLATE'][0]['Children'][0];
    assertSame('IfElseActivity', $if['Type']);
    assertSame('IfElseBranchActivity', $if['Children'][0]['Type']);
    assertSame([['UF_X', '!empty', '', '0']], $if['Children'][0]['Properties']['fieldcondition']);
    assertSame('Да', $if['Children'][0]['Properties']['Title']);
    assertSame('1', $if['Children'][1]['Properties']['truecondition']);
    assertSame('CrmChangeStatusActivity', $if['Children'][0]['Children'][0]['Type']);   // без обёртки
});

test('Сборщик: параллель, цикл и блок оборачиваются в последовательности', function () {
    $step = [['change_stage' => ['TargetStatus' => 'DT1000_10:CLIENT']]];
    $r = (new Compiler(Catalog::load()))->compile(minimalSpec([
        ['parallel' => ['branches' => [$step, ['title' => 'Вторая', 'steps' => $step]]]],
        ['while' => ['when' => ['fieldcondition' => [['UF_X', 'empty', '', '0']]], 'steps' => $step]],
        ['block' => ['title' => 'Блок', 'steps' => $step]],
    ]));
    assertSame([], $r['errors']);
    [$parallel, $loop, $block] = $r['bpt']['TEMPLATE'][0]['Children'];
    assertSame('SequenceActivity', $parallel['Children'][0]['Type']);
    assertSame(2, count($parallel['Children']));
    assertSame('Automation sequence', $parallel['Children'][0]['Properties']['Title']);   // kind: robots
    assertSame('Вторая', $parallel['Children'][1]['Properties']['Title']);
    assertSame([['UF_X', 'empty', '', '0']], $loop['Properties']['fieldcondition']);
    assertSame(1, count($loop['Children']));
    assertSame('SequenceActivity', $loop['Children'][0]['Type']);
    assertSame('Блок', $block['Properties']['Title']);
    assertSame('CrmChangeStatusActivity', $block['Children'][0]['Children'][0]['Type']);
});

test('Сборщик: заголовки последовательностей зависят от вида шаблона', function () {
    $spec = minimalSpec([['block' => ['title' => 'Блок', 'steps' => []]]]);
    $spec['kind'] = 'designer';
    $r = (new Compiler(Catalog::load()))->compile($spec);
    assertSame('Последовательность действий',
        $r['bpt']['TEMPLATE'][0]['Children'][0]['Children'][0]['Properties']['Title']);
    assertSame('Тест', $r['bpt']['TEMPLATE'][0]['Properties']['Title']);   // корень дизайнера — имя процесса
});

test('Сборщик: действие с двумя исходами', function () {
    $r = (new Compiler(Catalog::load()))->compile(minimalSpec([['approve' => [
        'id' => 'ap', 'Users' => ['user_42'], 'Name' => 'Согласование',
        'on_yes' => [['change_stage' => ['TargetStatus' => 'DT1000_10:CLIENT']]],
        'on_no' => [],
    ]]]));
    assertSame([], $r['errors']);
    $approve = $r['bpt']['TEMPLATE'][0]['Children'][0];
    assertSame(2, count($approve['Children']));
    assertSame('SequenceActivity', $approve['Children'][0]['Type']);
    assertSame('CrmChangeStatusActivity', $approve['Children'][0]['Children'][0]['Type']);
    assertSame([], $approve['Children'][1]['Children']);
});

test('Сборщик: ошибки вложенности', function () {
    $c = new Compiler(Catalog::load());
    $cases = [
        [['if' => ['branches' => [['steps' => []]]]], 'две ветки'],
        [['while' => ['steps' => []]], 'нет условия'],
        [['change_stage' => ['TargetStatus' => 'X', 'on_yes' => []]], 'вложенных шагов'],
        [['if' => ['branches' => [['when' => ['fieldcondition' => []], 'else' => true, 'steps' => []], ['steps' => []]]]], 'вместе'],
    ];
    foreach ($cases as [$step, $needle]) {
        $errors = implode(' ', $c->compile(minimalSpec([$step]))['errors']);
        assertTrue(str_contains($errors, $needle), "ожидали «{$needle}», получили: {$errors}");
    }
});

test('Сборщик: пустое DOCUMENT_FIELDS — предупреждение', function () {
    $r = (new Compiler(Catalog::load()))->compile(minimalSpec());
    assertSame([], $r['bpt']['DOCUMENT_FIELDS']);
    assertTrue(str_contains(implode(' ', $r['warnings']), 'DOCUMENT_FIELDS'), 'предупреждение про поля документа');
});
