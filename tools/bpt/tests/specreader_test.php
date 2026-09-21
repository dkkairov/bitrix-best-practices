<?php
/** Тесты чтения спецификаций. */

declare(strict_types=1);

test('SpecReader: YAML и JSON дают одинаковую структуру', function () {
    $json = '{"bizproc":1,"name":"Тест","steps":[{"change_stage":{"TargetStatus":"{{stage:Клиент}}"}}]}';
    assertSame(['bizproc' => 1, 'name' => 'Тест', 'steps' => [['change_stage' => ['TargetStatus' => '{{stage:Клиент}}']]]],
        SpecReader::parse($json, 'json'));
    if (!SpecReader::hasYaml()) {
        return;   // без расширения yaml проверяем только JSON
    }
    $yaml = "bizproc: 1\nname: Тест\nsteps:\n  - change_stage: {TargetStatus: \"{{stage:Клиент}}\"}\n";
    assertSame(SpecReader::parse($json, 'json'), SpecReader::parse($yaml, 'yaml'));
});

test('SpecReader: понятная ошибка на битом тексте', function () {
    assertThrows(fn () => SpecReader::parse('{не json', 'json'), 'разобрать JSON', 'битый JSON');
    if (SpecReader::hasYaml()) {
        assertThrows(fn () => SpecReader::parse("steps:\n  - [не закрыт\n", 'yaml'), 'разобрать YAML', 'битый YAML');
    }
});

test('SpecReader: формат определяется по файлу', function () {
    $json = tmpPath('.json');
    file_put_contents($json, '{"bizproc":1,"name":"Тест","steps":[]}');
    assertSame('Тест', SpecReader::read($json)['name']);
    if (!SpecReader::hasYaml()) {
        return;
    }
    $yaml = tmpPath('.yaml');
    file_put_contents($yaml, "bizproc: 1\nname: Тест\nsteps: []\n");
    assertSame('Тест', SpecReader::read($yaml)['name']);
});

test('SpecReader: запись читается обратно', function () {
    $spec = minimalSpec([['approve' => ['id' => 'ap', 'Users' => ['user_42'], 'Name' => 'Согласование',
        'ShowComment' => 'Y', 'on_yes' => [], 'on_no' => []]]]);
    $text = SpecReader::dump($spec);
    assertSame($spec, SpecReader::parse($text, SpecReader::hasYaml() ? 'yaml' : 'json'));
    // Ловушка YAML: Y и N должны сохраниться строками
    assertTrue(str_contains($text, '"Y"') || str_contains($text, "'Y'") || !SpecReader::hasYaml(),
        "значение Y должно быть в кавычках: {$text}");
});

test('SpecReader: спецификация должна быть объектом с шагами', function () {
    assertThrows(fn () => SpecReader::parse('[1,2,3]', 'json'), 'спецификация', 'список вместо объекта');
});
