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

test('Каталог: обязательность и допустимые значения — как в проверке ядра', function () {
    $c = Catalog::load();
    // IMNotifyActivity::ValidateProperties требует отправителя (bizproc 26.1075.0, стенд 2026-09-22)
    assertTrue(in_array('MessageUserFrom', $c->requiredProps('IMNotifyActivity'), true), 'отправитель уведомления обязателен');
    assertTrue(!isset($c->defaults('IMNotifyActivity')['MessageUserFrom']), 'у обязательного отправителя нет значения по умолчанию');
    // ApproveActivity::ValidateProperties принимает только any, all, vote
    assertSame(['all', 'any', 'vote'], $c->allowedValues('ApproveActivity', 'ApproveType'));
    assertSame(null, $c->allowedValues('ApproveActivity', 'Name'));
});

test('Каталог: варианты значений с расшифровкой и поведение — по курсу 57 и ядру', function () {
    $c = Catalog::load();
    // Урок 3771, константы CBPTaskDelegationType
    assertSame(['0', '1', '2'], array_map('strval', array_keys($c->options('ApproveActivity', 'DelegationType'))));
    assertSame(['s', 'm', 'h', 'd'], array_keys($c->options('ReviewActivity', 'TimeoutDurationType')));
    // «Запрашивать пояснение»: у утверждения четыре варианта, у ознакомления два
    assertSame(['N', 'Y', 'YA', 'YR'], array_keys($c->options('ApproveActivity', 'CommentRequired')));
    assertSame(['N', 'Y'], array_keys($c->options('ReviewActivity', 'CommentRequired')));
    // Урок 20768: наблюдателей можно и заменить
    assertTrue(isset($c->options('CrmSetObserverField', 'ActionOnObservers')['replace']), 'replace у наблюдателей');
    // Урок 3862, константы модуля im: 2 — персонализированное, 4 — от системы
    assertSame([2, 4], array_keys($c->options('IMNotifyActivity', 'MessageType')));
    // Проверка ядра остаётся в values, смысл — в options
    assertSame(['all', 'any', 'vote'], array_keys($c->options('ApproveActivity', 'ApproveType')));
    assertTrue(str_contains((string) $c->note('ApproveActivity'), 'ветка «нет»'), 'по истечении срока — отклонение');
    assertTrue(str_contains((string) $c->note('CrmChangeStatusActivity'), 'завершается'), 'смена стадии завершает процесс');
    assertSame(null, $c->options('ApproveActivity', 'Name'));
    assertSame(null, $c->note('SequenceActivity'));
});

test('Каталог: операторы условий — список у всех трёх свойств', function () {
    $c = Catalog::load();
    // fieldcondition/propertyvariablecondition/mixedcondition — общий $conditions, проверяем через
    // оба узла, где он используется (branch, loop), чтобы регенерация каталога не расцепила список
    foreach (['IfElseBranchActivity', 'WhileActivity'] as $type) {
        foreach (['fieldcondition', 'propertyvariablecondition', 'mixedcondition'] as $prop) {
            $note = (string) $c->note($type, $prop);
            assertTrue(str_contains($note, '`<=`'), "$type.$prop: есть оператор <=");
            assertTrue(str_contains($note, '`contain`'), "$type.$prop: есть оператор contain");
            assertTrue(str_contains($note, '`between`'), "$type.$prop: есть оператор between");
            assertTrue(str_contains($note, '`modified`'), "$type.$prop: есть оператор modified");
        }
    }
    // Оговорки по типу поля — не потерять при переформулировке
    $note = (string) $c->note('IfElseBranchActivity', 'fieldcondition');
    assertTrue(str_contains($note, 'int/double/date/datetime/time'), 'between ограничен типами поля');
    assertTrue(str_contains($note, 'только у fieldcondition'), 'modified — только у fieldcondition');
});

test('Каталог: заголовки по умолчанию — как ставит дизайнер', function () {
    $c = Catalog::load();
    assertSame('Ознакомление с документом', $c->title('ReviewActivity'));
    assertSame('Запрос доп.информации (с отклонением)', $c->title('RequestInformationOptionalActivity'));
    assertSame('Получить информацию об элементе CRM', $c->title('CrmGetDynamicInfoActivity'));
    assertTrue(!isset($c->entry('ApproveActivity')['title_guess']), 'заголовок утверждения подтверждён');
});

test('Каталог: обязательность — как в проверке импорта на стенде', function () {
    $c = Catalog::load();
    // validateTemplate на стенде (bizproc 26.1075.0, 2026-09-22): без этого импорт не проходит
    assertTrue(in_array('MessageUserFrom', $c->requiredProps('ImMessageActivity'), true), 'отправитель сообщения в чат');
    assertSame(['TITLE', 'CREATED_BY', 'RESPONSIBLE_ID|FLOW_ID'], $c->requiredKeys('Task2Activity', 'Fields'));
    assertSame([['TimeoutTime', 'TimeoutDuration']], $c->requiredAny('RobotDelayActivity'));
    assertTrue(!in_array('TimeoutTime', $c->requiredProps('RobotDelayActivity'), true), 'время паузы — одно из двух');
    assertSame([], $c->requiredKeys('SetFieldActivity', 'FieldValue'));
    assertSame([], $c->requiredAny('SetFieldActivity'));
});

test('Каталог: результаты — из корпуса и по курсу', function () {
    $c = Catalog::load();
    $all = $c->results('ApproveActivity');
    assertTrue(in_array('IsTimeout', $all, true) && in_array('Comments', $all, true), 'все результаты утверждения');
    assertSame(['ErrorMessage'], $c->results('SetFieldActivity'));
    assertSame(['UF_X', 'Document'], $c->results('CrmGetDynamicInfoActivity', ['ReturnFields' => ['UF_X']]));
    assertSame([], $c->results('SequenceActivity'));
});

test('Каталог: таблица значений и поведения для вики', function () {
    $md = Catalog::load()->notesToMarkdown();
    assertTrue(str_contains($md, '`YR` — только при отклонении'), 'варианты с расшифровкой');
    assertTrue(str_contains($md, 'обязательные ключи: `TITLE`, `CREATED_BY`'), 'обязательные ключи');
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
