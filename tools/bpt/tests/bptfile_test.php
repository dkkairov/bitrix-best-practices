<?php
/** Тесты чтения и записи .bpt. */

declare(strict_types=1);

test('BptFile: чтение фикстуры', function () {
    $bpt = BptFile::read(fixtureBpt());
    assertSame(2, $bpt['data']['VERSION']);
    assertSame('SequentialWorkflowActivity', $bpt['data']['TEMPLATE'][0]['Type']);
});

test('BptFile: JSON туда-обратно без потерь', function () {
    $bpt = BptFile::read(fixtureBpt());
    assertSame($bpt['serialized'], serialize(BptFile::fromJson(BptFile::toJson($bpt['data']))));
});

test('BptFile: запись и повторное чтение', function () {
    $out = tmpPath('.bpt');
    BptFile::write($out, fixtureData());
    assertSame(fixtureData(), BptFile::read($out)['data']);
    assertThrows(fn () => BptFile::write($out, fixtureData()), 'уже существует', 'перезапись без --force');
});

test('BptFile: файл с объектом отклоняется', function () {
    $path = tmpPath('.bpt');
    file_put_contents($path, gzcompress('a:2:{s:8:"TEMPLATE";a:1:{i:0;a:1:{s:4:"Type";s:1:"X";}}s:4:"EVIL";O:11:"ArrayObject":0:{}}', 9));
    assertThrows(fn () => BptFile::read($path), 'сериализованный объект', 'объект должен отклоняться');
});

test('BptFile: не шаблон и мусор отклоняются', function () {
    $notTemplate = tmpPath('.bpt');
    file_put_contents($notTemplate, gzcompress(serialize(['foo' => 1]), 9));
    assertThrows(fn () => BptFile::read($notTemplate), 'не шаблон БП', 'массив без TEMPLATE');
    $garbage = tmpPath('.bpt');
    file_put_contents($garbage, random_bytes(200));
    assertThrows(fn () => BptFile::read($garbage), 'распаковать', 'повреждённый файл');
});

test('BptFile: windows-1251 читается и пишется обратно', function () {
    $convert = function (mixed $v) use (&$convert): mixed {
        if (is_string($v)) {
            return mb_convert_encoding($v, 'Windows-1251', 'UTF-8');
        }
        if (!is_array($v)) {
            return $v;
        }
        $out = [];
        foreach ($v as $k => $item) {
            $out[is_string($k) ? mb_convert_encoding($k, 'Windows-1251', 'UTF-8') : $k] = $convert($item);
        }
        return $out;
    };
    $path = tmpPath('.bpt');
    file_put_contents($path, gzcompress(serialize($convert(fixtureData())), 9));
    assertThrows(fn () => BptFile::read($path), 'charset', 'без --charset должна быть подсказка');
    assertSame(fixtureData(), BptFile::read($path, 'windows-1251')['data']);
});

test('Analyzer: отчёт по фикстуре', function () {
    $report = (new Analyzer())->analyze('sample.bpt', BptFile::read(fixtureBpt()));
    assertSame('Bizproc Automation template', $report['root_title']);
    assertSame(5, $report['nodes']);
    assertSame([], $report['errors']);
    assertSame(['DT1000_10:CLIENT'], $report['portal_bindings']['stages']);
});

test('Analyzer: висячая ссылка и запрещённое действие — ошибки', function () {
    $data = fixtureData();
    $data['TEMPLATE'][0]['Children'][0]['Children'][0]['Children'][0]['Properties']['Title'] = '{=A9_9_9_9:Comments}';
    $data['TEMPLATE'][0]['Children'][0]['Children'][1]['Children'][] = [
        'Type' => 'CodeActivity', 'Name' => 'A1_2_3_4', 'Activated' => 'Y', 'Node' => null,
        'Properties' => ['Title' => 'Код'], 'Children' => [],
    ];
    $report = (new Analyzer())->analyze('sample.bpt', ['data' => $data, 'serialized' => null, 'compressed' => null]);
    $errors = implode(' ', $report['errors']);
    assertTrue(str_contains($errors, 'A9_9_9_9'), "висячая ссылка: {$errors}");
    assertTrue(str_contains($errors, 'CodeActivity'), "запрещённое действие: {$errors}");
});
