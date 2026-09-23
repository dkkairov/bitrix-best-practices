<?php
/** Тесты ролей и сопоставления констант. */

declare(strict_types=1);

function rolesData(): array
{
    return [
        'Юристы' => ['group' => 'Юристы'],
        'Руководитель проекта' => ['project_field' => 'Руководитель проекта'],
        'Руководитель отдела продаж' => ['user' => 'Руководитель отдела продаж Тест'],
        'Юридический отдел' => ['department' => 'Юридический отдел'],
    ];
}

test('Роли: виды и цели', function () {
    $r = Roles::fromArray(rolesData(), 'roles.yaml');
    assertSame('project_field', $r->kind('Руководитель проекта'));
    assertSame('Юристы', $r->target('Юристы'));
    assertSame(['Сметчик'], $r->missing(['Юристы', 'Сметчик']));
    assertSame(['kind' => 'department', 'target' => 'Юридический отдел'], $r->toArray()['Юридический отдел']);
});

test('Роли: ошибки формата', function () {
    assertThrows(fn () => Roles::fromArray(['Юристы' => ['group' => 'Юристы', 'user' => 'X']], 'r'), 'ровно один', 'два вида');
    assertThrows(fn () => Roles::fromArray(['Юристы' => ['team' => 'Юристы']], 'r'), 'team', 'неизвестный вид');
});

test('Константы: сопоставление по названию', function () {
    $roles = Roles::fromArray(rolesData(), 'roles.yaml');
    $result = ConstantMatcher::match([
        'pm'    => ['Name' => 'Руководитель проекта', 'Type' => 'user'],
        'sales' => ['Name' => 'Руководитель отдела продаж (уведомление)', 'Type' => 'user'],
        'boss'  => ['Name' => 'Директор филиала', 'Type' => 'user'],
        'sum'   => ['Name' => 'Порог суммы', 'Type' => 'double'],
        // название важнее описания: в описании упомянуты юристы, но константа — про руководителя продаж
        'head'  => ['Name' => 'Руководитель отдела продаж', 'Description' => 'Сообщить, если юристы отказали', 'Type' => 'user'],
        'who'   => ['Name' => 'Кому сообщить', 'Description' => 'Руководитель проекта из карточки', 'Type' => 'user'],
    ], $roles);
    assertSame(['pm' => 'Руководитель проекта', 'sales' => 'Руководитель отдела продаж',
        'head' => 'Руководитель отдела продаж', 'who' => 'Руководитель проекта'], $result['matched']);
    assertSame(['boss'], $result['unmatched']);
});

test('Константы: заполненный Default не требует сопоставления', function () {
    $roles = Roles::fromArray(rolesData(), 'roles.yaml');
    $result = ConstantMatcher::match([
        // роли нет, но Default уже заполнен агентом (например, из снимка портала) — шаблон и так
        // рабочий, сопоставление не нужно: константа не попадает ни в matched, ни в unmatched
        'fin_director' => ['Name' => 'Финансовый директор', 'Type' => 'user', 'Default' => 'user_42'],
        // роли нет, Default — пустая строка: запускать процесс не с чем, нужно сопоставление
        'boss' => ['Name' => 'Директор филиала', 'Type' => 'user', 'Default' => ''],
        // роли нет, Default вовсе не задан — тоже нужно сопоставление
        'boss2' => ['Name' => 'Директор филиала', 'Type' => 'user'],
        // Multiple-константа, все элементы Default пустые — тоже пусто
        'multi' => ['Name' => 'Директор филиала', 'Type' => 'user', 'Multiple' => '1', 'Default' => ['']],
        // роль нашлась и Default уже заполнен — всё равно matched: прогонщик подставит роль поверх
        'pm' => ['Name' => 'Руководитель проекта', 'Type' => 'user', 'Default' => 'user_7'],
    ], $roles);
    assertSame(['pm' => 'Руководитель проекта'], $result['matched']);
    assertSame(['boss', 'boss2', 'multi'], $result['unmatched']);
});
