<?php
/** Тесты разбора .bpt в спецификацию. */

declare(strict_types=1);

test('Разбор: собранное разбирается обратно и собирается так же', function () {
    $spec = minimalSpec([
        ['approve' => ['id' => 'ap', 'Users' => ['user_42'], 'Name' => 'Согласование',
                       'on_yes' => [['crm_event' => ['EventText' => 'Готово: {=@ap:Comments}']]],
                       'on_no'  => [['change_stage' => ['TargetStatus' => 'DT1000_10:NEW']]]]],
        ['if' => ['branches' => [
            ['title' => 'Да', 'when' => ['fieldcondition' => [['UF_X', '!empty', '', '0']]],
             'steps' => [['set_field' => ['FieldValue' => ['UF_X' => 'Y']]]]],
            ['title' => 'Иначе', 'else' => true, 'steps' => []],
        ]]],
        ['block' => ['title' => 'Блок', 'steps' => [['delay' => ['TimeoutTime' => '={=System:Now}']]]]],
    ]);
    $compiler = new Compiler(Catalog::load());
    $first = $compiler->compile($spec);
    assertSame([], $first['errors']);
    $back = (new Decompiler(Catalog::load(), null, true))->decompile($first['bpt']);
    $second = $compiler->compile($back['spec']);
    assertSame([], $second['errors'], 'повторная сборка без ошибок');
    assertSame($first['bpt']['TEMPLATE'], $second['bpt']['TEMPLATE'], 'дерево совпадает');
});

test('Разбор: значения по умолчанию опускаются', function () {
    $r = (new Decompiler(Catalog::load()))->decompile(BptFile::read(fixtureBpt())['data']);
    $step = $r['spec']['steps'][0]['parallel']['branches'][0]['steps'][0];
    assertTrue(isset($step['change_stage']['TargetStatus']), 'значимое свойство на месте');
    assertTrue(!isset($step['change_stage']['ModifiedBy']), 'ModifiedBy по умолчанию не пишется');
    assertTrue(!isset($step['change_stage']['title']), 'заголовок по умолчанию не пишется');
    assertTrue(!isset($r['spec']['kind']), 'вида шаблона в спецификации нет');
    assertTrue(!isset($r['spec']['root_title']), 'заголовок корня по умолчанию не пишется');
    // «Automation sequence» — не значение по умолчанию, поэтому сохраняется у ветки явно
    assertSame('Automation sequence', $r['spec']['steps'][0]['parallel']['branches'][1]['title'] ?? null);
});

test('Разбор: свой заголовок корня сохраняется в root_title', function () {
    $data = fixtureData();
    $data['TEMPLATE'][0]['Properties']['Title'] = 'Последовательный бизнес-процесс';
    $r = (new Decompiler(Catalog::load()))->decompile($data);
    assertSame('Последовательный бизнес-процесс', $r['spec']['root_title']);
    $again = (new Compiler(Catalog::load()))->compile($r['spec']);
    assertSame('Последовательный бизнес-процесс', $again['bpt']['TEMPLATE'][0]['Properties']['Title']);
});

test('Разбор: висячая ссылка сохраняется и предупреждает', function () {
    $data = fixtureData();
    $data['TEMPLATE'][0]['Children'][0]['Children'][0]['Children'][0]['Properties']['Title'] = 'Ссылка {=A9_9_9_9:Comments}';
    $r = (new Decompiler(Catalog::load()))->decompile($data);
    assertTrue(str_contains(implode(' ', $r['warnings']), 'A9_9_9_9'), 'предупреждение о висячей ссылке');
});

test('Разбор: со снимком идентификаторы становятся плейсхолдерами', function () {
    $snapshot = Snapshot::fromBpt(BptFile::read(fixtureBpt())['data']);
    $r = (new Decompiler(Catalog::load(), $snapshot))->decompile(BptFile::read(fixtureBpt())['data']);
    $step = $r['spec']['steps'][0]['parallel']['branches'][0]['steps'][0];
    assertSame('{{stage:Общая/Клиент}}', $step['change_stage']['TargetStatus']);
});

test('Разбор: неизвестный тип и свойство не теряются', function () {
    $data = fixtureData();
    $data['TEMPLATE'][0]['Children'][0]['Children'][1]['Children'][] = [
        'Type' => 'НовоеДействие', 'Name' => 'A1_2_3_4', 'Activated' => 'Y', 'Node' => null,
        'Properties' => ['Что' => 'значение', 'Title' => 'Новое'], 'Children' => [],
    ];
    $data['TEMPLATE'][0]['Children'][0]['Children'][0]['Children'][0]['Properties']['Выдумка'] = 'x';
    $r = (new Decompiler(Catalog::load()))->decompile($data);
    $text = implode(' ', $r['warnings']);
    assertTrue(str_contains($text, 'НовоеДействие'), "новый тип: {$text}");
    assertTrue(str_contains($text, 'Выдумка'), "свойство вне каталога: {$text}");
    $step = $r['spec']['steps'][0]['parallel']['branches'][0]['steps'][0];
    assertSame(['Выдумка' => 'x'], $step['change_stage']['raw']);
});
