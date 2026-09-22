<?php
// Подготовка стенда к оценке: группы, «Проекты» с полями-ролями, поля и стадии «Заявок», «Проект Альфа».
// Повторный запуск ничего не дублирует. Шаблоны «Заявок» не из оценки с автозапуском при создании или
// изменении — деактивируются; шаблоны роботов (AUTO_EXECUTE = 8) не трогаем.
$created = [];

// 1. Смарт-процессы
$ensureType = function (string $code, string $title, bool $stages) use (&$created) {
    if ($type = eval_type($code)) {
        return $type;
    }
    $class = \Bitrix\Crm\Service\Container::getInstance()->getDynamicTypeDataClass();
    $type = $class::createObject();
    foreach (['TITLE' => $title, 'NAME' => $code, 'CODE' => $code, 'IS_STAGES_ENABLED' => $stages ? 'Y' : 'N',
        'IS_CATEGORIES_ENABLED' => 'N', 'IS_BIZ_PROC_ENABLED' => 'Y', 'IS_AUTOMATION_ENABLED' => 'Y',
        'IS_SET_OPEN_PERMISSIONS' => 'Y'] as $k => $v) {
        $type->set($k, $v);
    }
    $result = $type->save();
    if (!$result->isSuccess()) {
        eval_fail("{$title}: " . implode('; ', $result->getErrorMessages()));
    }
    $created[] = "смарт-процесс {$title}";
    return $type;
};
$requestsType = $ensureType(EVAL_REQUESTS_CODE, 'Заявки', true);
$projectsType = $ensureType(EVAL_PROJECTS_CODE, 'Проекты', false);

// 1a. Фича «Наблюдатели» нужна проверке observers (T09) — по умолчанию у типа выключена, включаем
//     один раз, идемпотентно (создание нового типа её не задаёт — см. $ensureType)
if (!$requestsType->getIsObserversEnabled()) {
    $requestsType->setIsObserversEnabled(true);
    $result = $requestsType->save();
    if (!$result->isSuccess()) {
        eval_fail('Заявки: наблюдатели: ' . implode('; ', $result->getErrorMessages()));
    }
    $created[] = 'наблюдатели у «Заявок»';
}

$requests = eval_factory(EVAL_REQUESTS_CODE);
$projects = eval_factory(EVAL_PROJECTS_CODE);

// 2. Пользовательские поля
$ensureField = function (\Bitrix\Crm\Service\Factory $factory, string $suffix, string $label, string $userType, array $settings = []) use (&$created) {
    $entityId = $factory->getUserFieldEntityId();
    $name = 'UF_' . $entityId . '_' . $suffix;
    if (!\CUserTypeEntity::GetList([], ['ENTITY_ID' => $entityId, 'FIELD_NAME' => $name])->Fetch()) {
        $id = (new \CUserTypeEntity())->Add(['ENTITY_ID' => $entityId, 'FIELD_NAME' => $name, 'USER_TYPE_ID' => $userType,
            'MANDATORY' => 'N', 'MULTIPLE' => 'N', 'SHOW_IN_LIST' => 'Y', 'SETTINGS' => $settings,
            'EDIT_FORM_LABEL' => ['ru' => $label], 'LIST_COLUMN_LABEL' => ['ru' => $label], 'LIST_FILTER_LABEL' => ['ru' => $label]]);
        if (!$id) {
            eval_fail("поле {$label}: " . ($GLOBALS['APPLICATION']->GetException()?->GetString() ?? 'ошибка'));
        }
        $created[] = "поле {$label}";
    }
    return $name;
};
$ensureField($requests, 'AMOUNT', 'Сумма к оплате', 'double', ['PRECISION' => 2]);
$projectLink = $ensureField($requests, 'PROJECT', 'Проект', 'crm', ['DYNAMIC_' . $projects->getEntityTypeId() => 'Y']);
$ensureField($requests, 'INVOICE_NO', 'Номер счёта', 'string');
$ensureField($requests, 'PAID_ON', 'Дата оплаты', 'date');
$ensureField($requests, 'APPROVED_BY', 'Согласовано кем', 'employee');
$roleFields = [];
foreach (['PM' => 'Руководитель проекта', 'ESTIMATOR' => 'Сметчик', 'ACCOUNTANT' => 'Бухгалтер проекта'] as $suffix => $label) {
    $roleFields[$label] = $ensureField($projects, $suffix, $label, 'employee');
}

// 3. Стадии «Заявок»
$category = $requests->createDefaultCategoryIfNotExist();
$stagesEntity = $requests->getStagesEntityId($category->getId());
$prefix = 'DT' . $requests->getEntityTypeId() . '_' . $category->getId() . ':';
$existing = [];
foreach ($requests->getStages($category->getId()) as $stage) {
    $existing[eval_norm($stage->getName())] = true;
}
foreach ([['UC_REWORK', 'Доработка', 25], ['UC_CLIENT', 'Клиент', 35], ['UC_APPROVED', 'Согласовано', 36], ['UC_PAID', 'Оплачено', 37]] as [$code, $name, $sort]) {
    if (isset($existing[eval_norm($name)])) {
        continue;
    }
    $result = \Bitrix\Crm\StatusTable::add(['ENTITY_ID' => $stagesEntity, 'STATUS_ID' => $prefix . $code, 'NAME' => $name,
        'NAME_INIT' => $name, 'SORT' => $sort, 'SYSTEM' => false, 'COLOR' => \Bitrix\Crm\StatusTable::DEFAULT_PROCESS_COLOR,
        'CATEGORY_ID' => $category->getId()]);
    if (!$result->isSuccess()) {
        eval_fail("стадия {$name}: " . implode('; ', $result->getErrorMessages()));
    }
    $created[] = "стадия {$name}";
}

// 4. Группы пользователей и их состав
$groups = [];
foreach ([['Юристы', 'EVAL_LAWYERS', ['Юрист Тест']], ['Финансовый отдел', 'EVAL_FINANCE', ['Финансовый директор Тест']],
    ['Бухгалтеры', 'EVAL_ACCOUNTANTS', ['Бухгалтер Тест']]] as [$name, $stringId, $members]) {
    $groupId = eval_group_id($name);
    if (!$groupId) {
        $groupId = (int) (new \CGroup())->Add(['ACTIVE' => 'Y', 'NAME' => $name, 'STRING_ID' => $stringId]);
        if (!$groupId) {
            eval_fail("группа {$name} не создана");
        }
        $created[] = "группа {$name}";
    }
    foreach ($members as $fullName) {
        $userId = eval_user_id($fullName) ?? eval_fail("нет сотрудника «{$fullName}» — его заводит человек");
        \CUser::AppendUserGroup($userId, [$groupId]);
    }
    $groups[$name] = $groupId;
}

// 5. «Проект Альфа» с ролями
$projectId = null;
foreach ($projects->getItems(['filter' => ['=TITLE' => 'Проект Альфа']]) as $item) {
    $projectId = $item->getId();
}
if (!$projectId) {
    $item = $projects->createItem(['TITLE' => 'Проект Альфа']);
    foreach (['Руководитель проекта' => 'Руководитель проекта Тест', 'Сметчик' => 'Сметчик Тест', 'Бухгалтер проекта' => 'Бухгалтер Тест'] as $label => $fullName) {
        $item->set($roleFields[$label], eval_user_id($fullName) ?? eval_fail("нет сотрудника «{$fullName}»"));
    }
    $result = $projects->getAddOperation($item)->disableAllChecks()->launch();
    if (!$result->isSuccess()) {
        eval_fail('Проект Альфа: ' . implode('; ', $result->getErrorMessages()));
    }
    $projectId = $item->getId();
    $created[] = 'Проект Альфа';
}

// 6. Чужие шаблоны «Заявок» с автозапуском при создании (1) или изменении (2) мешают сценариям —
//    деактивировать. Роботы (8) не трогаем: они пусты, а их шаблоны создаёт сам портал.
$deactivated = [];
$rs = \CBPWorkflowTemplateLoader::GetList([], ['DOCUMENT_TYPE' => eval_document_type(EVAL_REQUESTS_CODE), 'ACTIVE' => 'Y'],
    false, false, ['ID', 'NAME', 'AUTO_EXECUTE']);
while ($t = $rs->Fetch()) {
    if (((int) $t['AUTO_EXECUTE'] & 3) !== 0 && !str_starts_with((string) $t['NAME'], 'EVAL ')) {
        \CBPWorkflowTemplateLoader::update($t['ID'], ['ACTIVE' => 'N']);
        $deactivated[] = (int) $t['ID'];
    }
}

eval_out(['types' => ['Заявки' => $requests->getEntityTypeId(), 'Проекты' => $projects->getEntityTypeId()],
    'project_link_field' => $projectLink, 'groups' => $groups, 'project' => ['id' => $projectId],
    'deactivated' => $deactivated, 'created' => $created]);
