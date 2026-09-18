<?php
/** Тесты снимка портала и плейсхолдеров. */

declare(strict_types=1);

function examplePortal(): string
{
    return dirname(__DIR__) . '/examples/example.portal.yaml';
}

test('Снимок: извлечение полей и стадий из .bpt', function () {
    $snapshot = Snapshot::fromBpt(BptFile::read(fixtureBpt())['data']);
    assertSame('DT1000_10:CLIENT', $snapshot->resolve('stage', 'Общая/Клиент'));
    assertSame('DT1000_10:CLIENT', $snapshot->resolve('stage', 'Клиент'));   // краткое название
    assertSame('TITLE', $snapshot->resolve('field', 'Название'));
    assertSame('UF_CRM_7_1700000000001', $snapshot->resolve('field', 'Сумма к оплате'));
    assertTrue($snapshot->documentFields() !== [], 'DOCUMENT_FIELDS сохраняются в снимке');
});

test('Снимок: подстановка в значениях и ключах', function () {
    if (!SpecReader::hasYaml()) {
        return;
    }
    $snapshot = Snapshot::load(examplePortal());
    $errors = [];
    $value = $snapshot->substitute([
        'FieldValue' => ['{{field:Сумма к оплате}}' => 'x'],
        'Users'      => ['{{group:Бухгалтерия}}', '{{user:Иванов Иван}}'],
        'Text'       => 'Ссылка на стадию {{stage:Общая/Клиент}} и тип DYNAMIC_{{smart:Заявки}}',
    ], 'steps[0]', $errors);
    assertSame([], $errors);
    assertSame(['UF_CRM_7_1700000000001' => 'x'], $value['FieldValue']);
    assertSame(['group_g7', 'user_42'], $value['Users']);
    assertSame('Ссылка на стадию DT1000_10:CLIENT и тип DYNAMIC_1000', $value['Text']);
});

test('Снимок: понятные ошибки', function () {
    if (!SpecReader::hasYaml()) {
        return;
    }
    $snapshot = Snapshot::load(examplePortal());
    $errors = [];
    $snapshot->substitute('{{field:Нет такого}}', 'steps[1]', $errors);
    $text = implode(' ', $errors);
    assertTrue(str_contains($text, 'Нет такого'), "название в ошибке: {$text}");
    assertTrue(str_contains($text, 'steps[1]'), "путь до шага в ошибке: {$text}");

    $errors = [];
    $snapshot->substitute('{{выдумка:Что-то}}', 'steps[2]', $errors);
    assertTrue(str_contains(implode(' ', $errors), 'выдумка'), 'неизвестный вид плейсхолдера');
});

test('Снимок: неоднозначное название — ошибка со списком', function () {
    $snapshot = Snapshot::fromArray(['fields' => ['Сумма' => 'UF_A']], 'память');
    $snapshot2 = Snapshot::fromArray(['stages' => ['Продажи/Новая' => 'C1:NEW', 'Сервис/Новая' => 'C2:NEW']], 'память');
    assertSame('UF_A', $snapshot->resolve('field', 'Сумма'));
    assertThrows(fn () => $snapshot2->resolve('stage', 'Новая'), 'Продажи/Новая', 'неоднозначное краткое название');
});

test('Снимок: обратный поиск названия по значению', function () {
    $snapshot = Snapshot::fromBpt(BptFile::read(fixtureBpt())['data']);
    assertSame('Общая/Клиент', $snapshot->nameFor('stage', 'DT1000_10:CLIENT'));
    assertSame('Сумма к оплате', $snapshot->nameFor('field', 'UF_CRM_7_1700000000001'));
    assertSame(null, $snapshot->nameFor('field', 'UF_НЕТ'));
});

test('Сборщик: плейсхолдеры подставляются, без снимка — ошибка', function () {
    if (!SpecReader::hasYaml()) {
        return;
    }
    $spec = minimalSpec([['change_stage' => ['TargetStatus' => '{{stage:Общая/Клиент}}']]]);
    $withSnapshot = (new Compiler(Catalog::load(), Snapshot::load(examplePortal())))->compile($spec);
    assertSame([], $withSnapshot['errors']);
    assertSame('DT1000_10:CLIENT',
        $withSnapshot['bpt']['TEMPLATE'][0]['Children'][0]['Properties']['TargetStatus']);

    $without = (new Compiler(Catalog::load()))->compile($spec);
    assertTrue(str_contains(implode(' ', $without['errors']), '--portal'), 'подсказка про снимок портала');
});

test('Сборщик: DOCUMENT_FIELDS берутся из снимка', function () {
    $snapshot = Snapshot::fromBpt(BptFile::read(fixtureBpt())['data']);
    $r = (new Compiler(Catalog::load(), $snapshot))->compile(minimalSpec());
    assertTrue(isset($r['bpt']['DOCUMENT_FIELDS']['STAGE_ID']), 'поля документа перенесены из снимка');
    assertSame([], array_values(array_filter($r['warnings'], fn ($w) => str_contains($w, 'без DOCUMENT_FIELDS'))));
});

test('Сборщик: --strict делает «сырые» ID ошибкой', function () {
    $spec = minimalSpec([['change_stage' => ['TargetStatus' => 'DT1000_10:CLIENT']]]);
    $normal = (new Compiler(Catalog::load()))->compile($spec);
    assertSame([], $normal['errors']);
    $strict = (new Compiler(Catalog::load(), null, true))->compile($spec);
    assertTrue(str_contains(implode(' ', $strict['errors']), 'сырые'), 'в строгом режиме — ошибка');
});
