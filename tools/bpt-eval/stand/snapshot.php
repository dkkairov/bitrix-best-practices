<?php
// Данные для снимка портала агентов: поля «Заявок» и «Проектов» (описания, как DOCUMENT_FIELDS экспорта),
// сотрудники (без администратора и ботов), группы и отделы, смарт-процессы. Названия → коды строит хост.
$describe = function (string $code): array {
    $out = [];
    foreach (\CBPRuntime::GetRuntime(true)->getDocumentService()->GetDocumentFields(eval_document_type($code), true) as $field => $info) {
        if (!str_ends_with(mb_strtoupper((string) $field), '_PRINTABLE') && !str_contains((string) $field, '.')) {
            $out[$field] = $info;
        }
    }
    return $out;
};
$users = [];
$rs = \CUser::GetList('ID', 'asc', ['ACTIVE' => 'Y'], ['FIELDS' => ['ID', 'NAME', 'LAST_NAME', 'EXTERNAL_AUTH_ID']]);
while ($u = $rs->Fetch()) {
    if ((int) $u['ID'] !== 1 && ($u['EXTERNAL_AUTH_ID'] ?? '') === '') {
        $users[trim($u['NAME'] . ' ' . $u['LAST_NAME'])] = 'user_' . $u['ID'];
    }
}
$groups = [];
foreach (['Юристы', 'Финансовый отдел', 'Бухгалтеры'] as $name) {
    $groups[$name] = 'group_g' . (eval_group_id($name) ?? eval_fail("нет группы {$name} — запустите prepare"));
}
$iblockId = (int) \COption::GetOptionInt('intranet', 'iblock_structure', 0);
$rs = \CIBlockSection::GetList(['LEFT_MARGIN' => 'ASC'], ['IBLOCK_ID' => $iblockId], false, ['ID', 'NAME']);
while ($s = $rs->Fetch()) {
    $groups[$s['NAME']] = 'group_d' . $s['ID'];
}
eval_out(['document_fields' => $describe(EVAL_REQUESTS_CODE),
    'related_document_fields' => ['Проекты' => $describe(EVAL_PROJECTS_CODE)],
    'users' => $users, 'groups' => $groups,
    'smart' => ['Заявки' => (string) eval_factory(EVAL_REQUESTS_CODE)->getEntityTypeId(),
        'Проекты' => (string) eval_factory(EVAL_PROJECTS_CODE)->getEntityTypeId()]]);
