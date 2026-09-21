<?php
/** Тесты каталога действий. */

declare(strict_types=1);

test('Каталог: алиас и тип', function () {
    $c = Catalog::load();
    assertSame('ApproveActivity', $c->resolveType('approve'));
    assertSame('ApproveActivity', $c->resolveType('ApproveActivity'));
    assertThrows(fn () => $c->resolveType('нет_такого'), 'неизвестное действие', 'неизвестный алиас');
});

test('Каталог: формы вложенности', function () {
    $c = Catalog::load();
    assertSame('waiting-branches', $c->shape('ApproveActivity'));
    assertSame('waiting-branches', $c->shape('RequestInformationOptionalActivity'));
    assertSame('waiting', $c->shape('ReviewActivity'));
    assertSame('leaf', $c->shape('SetFieldActivity'));
    assertSame('ifelse', $c->shape('IfElseActivity'));
    assertSame('parallel', $c->shape('ParallelActivity'));
    assertSame('loop', $c->shape('WhileActivity'));
    assertSame('block', $c->shape('EmptyBlockActivity'));
});

test('Каталог: значения по умолчанию и обязательные свойства', function () {
    $c = Catalog::load();
    assertSame([], $c->defaults('SetFieldActivity')['ModifiedBy']);
    assertSame('N', $c->defaults('SetFieldActivity')['MergeMultipleFields']);
    assertSame('', $c->defaults('SetFieldActivity')['EditorComment']);      // общее свойство
    assertTrue(!isset($c->defaults('SetFieldActivity')['FieldValue']), 'обязательное свойство без значения по умолчанию');
    assertSame(['FieldValue'], $c->requiredProps('SetFieldActivity'));
    assertSame('Изменение документа', $c->title('SetFieldActivity'));
    assertSame(['Comments', 'LastApprover'], $c->returns('ApproveActivity'));
    // Для «получить информацию» список результатов задаётся свойством действия
    assertSame(['UF_X'], $c->returns('CrmGetDynamicInfoActivity', ['ReturnFields' => ['UF_X']]));
    assertSame(['TITLE'], $c->returns('CrmGetRelationsInfoActivity', ['ParentEntityFields' => ['TITLE' => ['Name' => 'Название']]]));
});

test('Каталог: частные значения заданий перекрывают общие', function () {
    // У ознакомления своя подпись поля комментария, у утверждения — общая
    assertSame('Комментарий', Catalog::load()->defaults('ReviewActivity')['CommentLabelMessage']);
    assertSame('Пояснение', Catalog::load()->defaults('ApproveActivity')['CommentLabelMessage']);
});

test('Каталог: типы свойств', function () {
    $c = Catalog::load();
    assertSame('map', $c->propType('SetFieldActivity', 'FieldValue'));
    assertSame('portal-id', $c->propType('CrmChangeStatusActivity', 'TargetStatus'));
    assertSame('portal-id', $c->propType('StartWorkflowActivity', 'TemplateId'));
    assertSame('text', $c->propType('SetFieldActivity', 'Title'));           // из общих свойств
    assertSame(null, $c->propType('SetFieldActivity', 'Выдумка'));
});

test('Каталог: нормализация значений из YAML', function () {
    $c = Catalog::load();
    $props = $c->normalizeProps('Task2Activity', [
        'HoldToClose' => false,
        'Fields'      => ['PRIORITY' => 1, 'TASK_CONTROL' => true, 'TAG_NAMES' => ['срочно']],
    ]);
    assertSame('N', $props['HoldToClose']);
    assertSame('1', $props['Fields']['PRIORITY']);
    assertSame('Y', $props['Fields']['TASK_CONTROL']);
    assertSame(['срочно'], $props['Fields']['TAG_NAMES']);
    $approve = $c->normalizeProps('ApproveActivity', ['DelegationType' => 1, 'ShowComment' => true, 'Users' => ['user_42']]);
    assertSame('1', $approve['DelegationType']);
    assertSame('Y', $approve['ShowComment']);
    assertSame(['user_42'], $approve['Users']);
});

test('Каталог: нормализация описаний полей', function () {
    assertSame('1', Catalog::normalizeDefinition(['Name' => 'Сумма', 'Type' => 'double', 'Required' => true])['Required']);
    assertSame('0', Catalog::normalizeDefinition(['Name' => 'Сумма', 'Multiple' => false])['Multiple']);
    assertSame('Сумма', Catalog::normalizeDefinition(['Name' => 'Сумма'])['Name']);
});

test('Каталог: описания в списках нормализуются', function () {
    $props = Catalog::load()->normalizeProps('RequestInformationActivity', [
        'RequestedInformation' => [['Name' => 'amount', 'Title' => 'Сумма', 'Type' => 'double', 'Required' => true]],
    ]);
    assertSame('1', $props['RequestedInformation'][0]['Required']);
});

test('Каталог: PHP-код запрещён', function () {
    $c = Catalog::load();
    assertTrue($c->isForbidden('CodeActivity'), 'CodeActivity должен быть запрещён');
    assertTrue(!$c->isForbidden('SetFieldActivity'), 'обычное действие не запрещено');
});

test('Каталог: покрытие и вывод', function () {
    $c = Catalog::load();
    assertSame(25, count($c->types()));   // 24 типа из корпуса + запрещённый CodeActivity
    assertTrue(str_contains($c->toMarkdown(), 'SetFieldActivity'), 'таблица содержит типы');
    assertTrue(str_contains($c->toMarkdown(), 'set_field'), 'таблица содержит алиасы');
});
