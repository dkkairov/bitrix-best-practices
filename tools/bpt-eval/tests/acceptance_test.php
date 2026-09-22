<?php
/** Тесты формата проверок acceptance.yaml. */

declare(strict_types=1);

function acceptanceData(): array
{
    return [
        'task' => 'T04', 'document' => 'Заявки',
        'item' => ['Название' => 'Заявка T04', 'Проект' => 'Проект Альфа'],
        'scenarios' => [[
            'name' => 'юрист отказал',
            'steps' => [
                ['task_for' => 'Юристы', 'do' => 'reject', 'comment' => 'Нет доверенности'],
                ['task_for' => 'Бухгалтер проекта', 'do' => 'approve', 'optional' => true],
            ],
            'expect' => ['stage' => 'Доработка', 'notify' => [['to' => 'Инициатор', 'contains' => 'доверенност']]],
        ]],
        'checklist' => ['Ждать ли второго согласующего', ['text' => 'Срок', 'required' => false]],
    ];
}

test('Проверки: разбор и значения по умолчанию', function () {
    $a = Acceptance::fromArray(acceptanceData(), 'T04');
    assertSame('create', $a->start());
    assertSame([], $a->parameters());
    assertSame([], $a->scenarios()[0]['item']);   // поля элемента сценария — только поверх item задачи
    $data = acceptanceData();
    $data['scenarios'][0]['item'] = ['Сумма к оплате' => 500000];
    assertSame(['Сумма к оплате' => 500000], Acceptance::fromArray($data, 'T02')->scenarios()[0]['item']);
    $step = $a->scenarios()[0]['steps'][0];
    assertSame(['task_for' => 'Юристы', 'do' => 'reject', 'comment' => 'Нет доверенности',
        'values' => [], 'title_contains' => null, 'optional' => false], $step);
    assertTrue($a->scenarios()[0]['steps'][1]['optional'], 'optional читается');
    assertSame([['text' => 'Ждать ли второго согласующего', 'required' => true],
        ['text' => 'Срок', 'required' => false]], $a->checklist());
});

test('Проверки: все роли задачи', function () {
    assertSame(['Бухгалтер проекта', 'Инициатор', 'Юристы'], Acceptance::fromArray(acceptanceData(), 'T04')->roleNames());
});

test('Проверки: ошибки формата — с путём', function () {
    $data = acceptanceData();
    $data['scenarios'][0]['steps'][0]['do'] = 'approv';
    assertThrows(fn () => Acceptance::fromArray($data, 'T04'), 'scenarios[0].steps[0].do', 'неизвестное действие');
    $data = acceptanceData();
    $data['scenarios'][0]['steps'][] = ['task_for' => 'Бухгалтер проекта', 'do' => 'provide'];
    assertThrows(fn () => Acceptance::fromArray($data, 'T04'), 'values', 'provide без values');
    $data = acceptanceData();
    $data['scenarios'][0]['expect']['цвет'] = 'синий';
    assertThrows(fn () => Acceptance::fromArray($data, 'T04'), 'expect.цвет', 'неизвестная проверка');
    $data = acceptanceData();
    $data['start'] = 'сразу';
    assertThrows(fn () => Acceptance::fromArray($data, 'T04'), 'start', 'неизвестный запуск');
});

test('Проверки: задача только с чек-листом', function () {
    $a = Acceptance::fromArray(['task' => 'T10', 'document' => 'Заявки', 'checklist' => ['Нет действия']], 'T10');
    assertSame([], $a->scenarios());
    assertThrows(fn () => Acceptance::fromArray(['task' => 'T10', 'document' => 'Заявки'], 'T10'),
        'нет ни сценариев, ни чек-листа', 'пустая задача');
});

test('Проверки: загрузка из файла', function () {
    $path = tmpPath('.yaml');
    file_put_contents($path, SpecReader::dump(acceptanceData()));
    assertSame('T04', Acceptance::load($path)->task());
});
