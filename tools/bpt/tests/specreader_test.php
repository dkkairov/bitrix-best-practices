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

test('SpecReader: n/off/yes как коды — понятная ошибка, а не тихая потеря', function () {
    if (!SpecReader::hasYaml()) {
        return;
    }
    // YAML 1.1: n, y, on, off, yes, no, true, false — булевы. PHP кладёт их в ключи 0 и 1,
    // поэтому «n» и «off» сливаются в один ключ: одна переменная молча затирает другую.
    $yaml = "bizproc: 1
name: Тест
variables:
  n:
    Type: string
  off:
    Type: string
steps: []
";
    assertThrows(fn () => SpecReader::parse($yaml, 'yaml'), 'кавычк', 'код-булево — ошибка с подсказкой');
    assertThrows(fn () => SpecReader::parse($yaml, 'yaml'), 'строка 4', 'ошибка называет строку');
});

test('SpecReader: законные числовые ключи снимка не считаются ошибкой', function () {
    if (!SpecReader::hasYaml()) {
        return;
    }
    // В снимке портала воронки нумерованные: «8: Общая воронка». Ключ 0 или 1 у воронки
    // тоже возможен — придираться к числам нельзя, только к булевым словам в тексте.
    $yaml = "bizproc: 1
name: Тест
funnels:
  0: Общая воронка
  1: Вторая
steps: []
";
    $spec = SpecReader::parse($yaml, 'yaml');
    assertSame(['Общая воронка', 'Вторая'], array_values($spec['funnels']));
});

test('SpecReader: слово-ключ внутри блочного текста — не ошибка', function () {
    if (!SpecReader::hasYaml()) {
        return;
    }
    // Внутри «EventText: |» лежит текст заказчика: строка «no: ...» там не ключ спецификации.
    $yaml = "bizproc: 1
name: Тест
steps:
  - crm_event:
      EventText: |
        on: включено
        no: выключено
";
    $spec = SpecReader::parse($yaml, 'yaml');
    assertTrue(str_contains($spec['steps'][0]['crm_event']['EventText'], 'включено'), 'блочный текст прочитан');
});

test('SpecReader: код в кавычках работает — это и есть способ записать n', function () {
    if (!SpecReader::hasYaml()) {
        return;
    }
    $yaml = "bizproc: 1
name: Тест
variables:
  \"n\":
    Type: string
steps: []
";
    assertSame(['n'], array_keys(SpecReader::parse($yaml, 'yaml')['variables']));
});

test('SpecReader: код-булево в поточном стиле тоже ловится', function () {
    if (!SpecReader::hasYaml()) {
        return;
    }
    // {n: ...} — тот же дефект, что и блочный ключ: код становится ключом 0.
    $yaml = "bizproc: 1\nname: Тест\nvariables: {n: {Type: string}, amount: {Type: double}}\nsteps: []\n";
    assertThrows(fn () => SpecReader::parse($yaml, 'yaml'), 'кавычк', 'поточный стиль');
});

test('SpecReader: блочный скаляр списком — не ложное срабатывание', function () {
    if (!SpecReader::hasYaml()) {
        return;
    }
    // «- |» вводит блочный скаляр без ключа перед ним; строки внутри — текст, а не ключи.
    $yaml = "bizproc: 1\nname: Тест\nnotes:\n  - |\n    no: это текст, а не ключ\nsteps: []\n";
    $spec = SpecReader::parse($yaml, 'yaml');
    assertTrue(str_contains($spec['notes'][0], 'это текст'), 'блочный скаляр прочитан');
});
