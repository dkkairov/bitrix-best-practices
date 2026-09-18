<?php
/** Тесты схемы процесса. */

declare(strict_types=1);

test('Схема: узлы, ветки и отключённые шаги', function () {
    $r = (new Compiler(Catalog::load()))->compile(minimalSpec([
        ['approve' => ['id' => 'ap', 'Users' => ['user_42'], 'Name' => 'Согласование',
                       'on_yes' => [['change_stage' => ['TargetStatus' => 'DT1000_10:CLIENT']]],
                       'on_no'  => []]],
        ['change_stage' => ['off' => true, 'title' => 'Выключенный шаг', 'TargetStatus' => 'DT1000_10:NEW']],
    ]));
    assertSame([], $r['errors']);
    $mmd = (new Mermaid(Catalog::load()))->render($r['bpt']);
    assertTrue(str_contains($mmd, '```mermaid'), 'блок mermaid');
    assertTrue(str_contains($mmd, 'flowchart TD'), 'тип схемы');
    // На схеме показывается заголовок действия (Title), здесь он взят из каталога
    assertTrue(str_contains($mmd, 'Утверждение документа'), 'заголовок задания');
    assertTrue(str_contains($mmd, '|да|'), 'подпись ветки «да»');
    assertTrue(str_contains($mmd, 'Сменить стадию'), 'узел смены стадии');
    assertTrue(str_contains($mmd, 'class n'), 'класс для отключённого шага');
    assertTrue(str_contains($mmd, 'Старт') && str_contains($mmd, 'Конец'), 'начало и конец');
});

test('Схема: условие, параллель, цикл и блок', function () {
    $step = [['change_stage' => ['TargetStatus' => 'DT1000_10:CLIENT']]];
    $r = (new Compiler(Catalog::load()))->compile(minimalSpec([
        ['if' => ['title' => 'Проверка', 'branches' => [
            ['title' => 'Да', 'when' => ['fieldcondition' => [['UF_X', '!empty', '', '0']]], 'steps' => $step],
            ['title' => 'Нет', 'else' => true, 'steps' => []],
        ]]],
        ['parallel' => ['branches' => [$step, $step]]],
        ['while' => ['when' => ['fieldcondition' => [['UF_X', 'empty', '', '0']]], 'steps' => $step]],
        ['block' => ['title' => 'Согласование', 'steps' => $step]],
    ]));
    assertSame([], $r['errors']);
    $mmd = (new Mermaid(Catalog::load()))->render($r['bpt']);
    assertTrue(str_contains($mmd, '{"Проверка"}'), 'условие — ромб');
    assertTrue(str_contains($mmd, '|Да|') && str_contains($mmd, '|Нет|'), 'подписи веток условия');
    assertTrue(str_contains($mmd, 'Слияние'), 'слияние параллельных веток');
    assertTrue(str_contains($mmd, '|повтор|'), 'обратная стрелка цикла');
    assertTrue(str_contains($mmd, 'subgraph'), 'блок — подграф');
});

test('Схема: кавычки и длинные заголовки не ломают разметку', function () {
    $long = str_repeat('очень длинный заголовок ', 10);
    $r = (new Compiler(Catalog::load()))->compile(minimalSpec([
        ['change_stage' => ['title' => "Стадия \"Клиент\" [важно]", 'TargetStatus' => 'DT1000_10:CLIENT']],
        ['change_stage' => ['title' => $long, 'TargetStatus' => 'DT1000_10:NEW']],
    ]));
    $mmd = (new Mermaid(Catalog::load()))->render($r['bpt']);
    assertTrue(!str_contains($mmd, '"Клиент"'), 'кавычки заменены');
    assertTrue(!str_contains($mmd, '[важно]'), 'квадратные скобки заменены');
    assertTrue(str_contains($mmd, '…'), 'длинный заголовок обрезан');
});

test('Схема: строится для фикстуры', function () {
    $mmd = (new Mermaid(Catalog::load()))->render(BptFile::read(fixtureBpt())['data']);
    assertTrue(str_contains($mmd, 'Параллельное выполнение'), 'узел параллели');
    assertTrue(substr_count($mmd, '-->') >= 3, 'есть связи');
});
