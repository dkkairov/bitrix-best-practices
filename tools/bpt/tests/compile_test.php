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
    assertSame('Последовательность действий', $parallel['Children'][0]['Properties']['Title']);
    assertSame('Вторая', $parallel['Children'][1]['Properties']['Title']);
    assertSame([['UF_X', 'empty', '', '0']], $loop['Properties']['fieldcondition']);
    assertSame(1, count($loop['Children']));
    assertSame('SequenceActivity', $loop['Children'][0]['Type']);
    assertSame('Блок', $block['Properties']['Title']);
    assertSame('CrmChangeStatusActivity', $block['Children'][0]['Children'][0]['Type']);
});

test('Сборщик: заголовок корня — как у дизайнера, свой — через root_title', function () {
    $spec = minimalSpec();
    $r = (new Compiler(Catalog::load()))->compile($spec);
    assertSame('Bizproc Automation template', $r['bpt']['TEMPLATE'][0]['Properties']['Title']);
    assertTrue(!str_contains(implode(' ', $r['warnings']), 'kind'), 'без kind нет предупреждения');

    $spec['root_title'] = 'Последовательный бизнес-процесс';   // так корень назван в старых шаблонах
    $r = (new Compiler(Catalog::load()))->compile($spec);
    assertSame('Последовательный бизнес-процесс', $r['bpt']['TEMPLATE'][0]['Properties']['Title']);
});

test('Сборщик: устаревший ключ kind — только предупреждение', function () {
    $spec = minimalSpec();
    $spec['kind'] = 'robots';
    $r = (new Compiler(Catalog::load()))->compile($spec);
    assertSame([], $r['errors']);
    assertTrue(str_contains(implode(' ', $r['warnings']), 'kind'), 'предупреждение про kind');
    assertSame('Bizproc Automation template', $r['bpt']['TEMPLATE'][0]['Properties']['Title']);
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

test('Сборщик: ссылка на результат шага', function () {
    $r = (new Compiler(Catalog::load()))->compile(minimalSpec([
        ['approve' => ['id' => 'ap', 'Users' => ['user_42'], 'Name' => 'Согласование', 'on_yes' => [], 'on_no' => []]],
        ['crm_event' => ['EventType' => 'INFO', 'EventText' => 'Комментарий: {=@ap:Comments > printable}']],
    ]));
    assertSame([], $r['errors']);
    $children = $r['bpt']['TEMPLATE'][0]['Children'];
    $name = $children[0]['Name'];
    assertSame("Комментарий: {={$name}:Comments > printable}", $children[1]['Properties']['EventText']);
});

test('Сборщик: битая ссылка и повтор id — ошибки', function () {
    $c = new Compiler(Catalog::load());
    $errors = implode(' ', $c->compile(minimalSpec([['crm_event' => ['EventText' => '{=@нет:Comments}']]]))['errors']);
    assertTrue(str_contains($errors, 'нет'), "ссылка на несуществующий шаг: {$errors}");
    $dup = [
        ['change_stage' => ['id' => 'x', 'TargetStatus' => 'DT1000_10:NEW']],
        ['change_stage' => ['id' => 'x', 'TargetStatus' => 'DT1000_10:CLIENT']],
    ];
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

test('Сборщик: результаты «получить информацию» берутся из свойства', function () {
    $r = (new Compiler(Catalog::load()))->compile(minimalSpec([
        ['get_smart_item' => ['id' => 'item', 'DynamicTypeId' => '1000', 'ReturnFields' => ['UF_X']]],
        ['crm_event' => ['EventText' => 'Поле: {=@item:UF_X}']],
    ]));
    assertSame([], $r['errors']);
    // Предупреждения о «сырых» ID портала здесь ожидаемы, а вот про результат — нет
    assertSame([], array_values(array_filter($r['warnings'], fn ($w) => str_contains($w, 'не возвращает'))));
});

test('Сборщик: необъявленная переменная ловится анализатором', function () {
    $errors = implode(' ', (new Compiler(Catalog::load()))->compile(minimalSpec([
        ['crm_event' => ['EventText' => '{=Variable:призрак}']],
    ]))['errors']);
    assertTrue(str_contains($errors, 'призрак'), "необъявленная переменная: {$errors}");
});

test('Сборщик: объявленная переменная ошибок не даёт', function () {
    $spec = minimalSpec([['crm_event' => ['EventText' => '{=Variable:amount}']]]);
    $spec['variables'] = ['amount' => ['Name' => 'Сумма', 'Type' => 'double']];
    assertSame([], (new Compiler(Catalog::load()))->compile($spec)['errors']);
});

test('Пример: собирается, разбирается и рисуется', function () {
    if (!SpecReader::hasYaml()) {
        return;   // пример написан на YAML
    }
    $catalog = Catalog::load();
    $snapshot = Snapshot::load(dirname(__DIR__) . '/examples/example.portal.yaml');
    $spec = SpecReader::read(dirname(__DIR__) . '/examples/invoice-approval.bizproc.yaml');
    $built = (new Compiler($catalog, $snapshot, true))->compile($spec);   // строгий режим: «сырых» ID быть не должно
    assertSame([], $built['errors']);

    $approve = $built['bpt']['TEMPLATE'][0]['Children'][1];
    assertSame('ApproveActivity', $approve['Type']);
    assertSame(['group_g7'], $approve['Properties']['Users']);
    $yes = $approve['Children'][0]['Children'];
    assertTrue(str_contains($yes[0]['Properties']['EventText'], "{={$approve['Name']}:Comments}"), 'ссылка на шаг');
    assertSame('DT1000_10:CLIENT', $yes[1]['Properties']['TargetStatus']);   // смена стадии — последней

    $back = (new Decompiler($catalog, $snapshot, true))->decompile($built['bpt']);
    $again = (new Compiler($catalog, $snapshot))->compile($back['spec']);
    assertSame([], $again['errors']);
    assertSame($built['bpt']['TEMPLATE'], $again['bpt']['TEMPLATE'], 'разбор и повторная сборка');

    assertTrue(str_contains((new Mermaid($catalog))->render($built['bpt']), 'Согласование бухгалтерией'), 'схема');
});

test('Сборщик: пустое DOCUMENT_FIELDS — предупреждение', function () {
    $r = (new Compiler(Catalog::load()))->compile(minimalSpec());
    assertSame([], $r['bpt']['DOCUMENT_FIELDS']);
    assertTrue(str_contains(implode(' ', $r['warnings']), 'DOCUMENT_FIELDS'), 'предупреждение про поля документа');
});
