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
