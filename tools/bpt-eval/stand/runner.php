<?php
// Сценарии задачи на стенде: элемент → задания по ролям → ответы → проверки наблюдаемого результата.
$task = (string) eval_input('task');
$templateId = (int) eval_input('template_id');
$acceptance = eval_input('acceptance');
$roles = eval_input('roles');
$factory = eval_factory(EVAL_REQUESTS_CODE);
$fields = eval_fields(EVAL_REQUESTS_CODE);
$entityTypeId = $factory->getEntityTypeId();
$db = \Bitrix\Main\Application::getConnection();
$projects = eval_factory(EVAL_PROJECTS_CODE);

// Проект элемента — для ролей «из карточки проекта»; сценарий может взять другой проект
$findProject = function (?string $title) use ($projects): ?int {
    if ($title === null) {
        return null;
    }
    foreach ($projects->getItems(['filter' => ['=TITLE' => $title]]) as $p) {
        return $p->getId();
    }
    eval_fail("нет проекта «{$title}» — запустите prepare");
};
$projectId = $findProject(isset($acceptance['item']['Проект']) ? (string) $acceptance['item']['Проект'] : null);

/** Роль → ID пользователей стенда (роли проекта — по проекту текущего сценария). */
$roleUsers = function (string $role) use ($roles, $projects, &$projectId): array {
    $spec = $roles[$role] ?? eval_fail("роль «{$role}» не описана");
    switch ($spec['kind']) {
        case 'user':
            return [eval_user_id($spec['target']) ?? eval_fail("нет сотрудника «{$spec['target']}»")];
        case 'group':
            $groupId = eval_group_id($spec['target']) ?? eval_fail("нет группы «{$spec['target']}»");
            return array_map('intval', \CGroup::GetGroupUser($groupId));
        case 'department':
            $depId = eval_department_id($spec['target']) ?? eval_fail("нет отдела «{$spec['target']}»");
            $ids = [];
            $rs = \CUser::GetList('ID', 'asc', ['ACTIVE' => 'Y', 'UF_DEPARTMENT' => $depId], ['FIELDS' => ['ID']]);
            while ($u = $rs->Fetch()) {
                $ids[] = (int) $u['ID'];
            }
            return $ids;
        case 'project_field':
            $projectId ?? eval_fail("роль «{$role}» из карточки проекта, а у элемента нет «Проект»");
            $code = eval_fields(EVAL_PROJECTS_CODE)[eval_norm($spec['target'])] ?? eval_fail("у «Проектов» нет поля «{$spec['target']}»");
            return array_map('intval', (array) $projects->getItem($projectId)->get($code));
    }
    return [];
};

// Константы-пользователи шаблона — по ролям
$constantsMap = (array) eval_input('constants', []);
if ($constantsMap) {
    $tpl = \CBPWorkflowTemplateLoader::GetList([], ['ID' => $templateId], false, false, ['ID', 'CONSTANTS'])->Fetch();
    $constants = $tpl['CONSTANTS'];
    foreach ($constantsMap as $code => $role) {
        $users = array_map(fn ($id) => 'user_' . $id, $roleUsers($role));
        $constants[$code]['Default'] = ($constants[$code]['Multiple'] ?? '0') === '1' ? $users : ($users[0] ?? '');
    }
    \CBPWorkflowTemplateLoader::update($templateId, ['CONSTANTS' => $constants]);
}

$stageName = function (string $stageId) use ($factory): string {
    foreach ($factory->getStages() as $stage) {
        if ($stage->getStatusId() === $stageId) {
            return $stage->getName();
        }
    }
    return $stageId;
};

/** Открытые задания процессов элемента (задания, где пользователь ещё не ответил). */
$openTasks = function (int $itemId) use ($entityTypeId, $db): array {
    $doc = "DYNAMIC_{$entityTypeId}_{$itemId}";
    return $db->query("SELECT t.ID, t.WORKFLOW_ID, t.ACTIVITY, t.ACTIVITY_NAME, t.NAME, tu.USER_ID
        FROM b_bp_task t JOIN b_bp_task_user tu ON tu.TASK_ID = t.ID
        JOIN b_bp_workflow_state s ON s.ID = t.WORKFLOW_ID
        WHERE s.DOCUMENT_ID = '{$doc}' AND t.STATUS = 0 AND tu.STATUS = 0 ORDER BY t.ID")->fetchAll();
};

/**
 * Ответ на задание по намерению шага, а не по устройству процесса: approve, review и provide —
 * положительный ответ на любое задание (утвердить, ознакомиться, отправить запрошенное), reject —
 * отказ, где он возможен. Разумный выбор механизма агентом так не штрафуется. Строка — причина отказа.
 */
$taskRequest = function (array $task, array $step, int $userId): array|string {
    $comment = $step['comment'] !== '' ? $step['comment'] : 'Оценка: ответ по сценарию';
    $request = ['task_comment' => $comment];
    $positive = $step['do'] !== 'reject';
    switch ($task['ACTIVITY']) {
        case 'ApproveActivity':
            $request[$positive ? 'approve' : 'nonapprove'] = 'Y';
            return $request;
        case 'ReviewActivity':
            return $positive ? $request : 'ознакомление нельзя отклонить';
        case 'RequestInformationActivity':
        case 'RequestInformationOptionalActivity':
            if (!$positive) {
                return $task['ACTIVITY'] === 'RequestInformationOptionalActivity'
                    ? $request + ['cancel' => true] : 'запрос информации нельзя отклонить';
            }
            $request['fields'] = ['task_comment' => $comment];
            $values = $step['values'];
            foreach ((array) ($task['PARAMETERS']['REQUEST'] ?? []) as $field) {
                $title = eval_norm((string) ($field['Title'] ?? $field['Name'] ?? ''));
                $value = null;
                foreach ($values as $key => $candidate) {
                    $label = eval_norm((string) $key);
                    if ($title !== '' && (str_contains($title, $label) || str_contains($label, $title))) {
                        $value = $candidate;
                        unset($values[$key]);
                        break;
                    }
                }
                // поле, которого нет в проверках (агент назвал его по-своему), — значение по типу
                $request['fields'][(string) $field['Name']] = $value ?? eval_default_value($field, $userId);
            }
            return $request;
    }
    return "неизвестный вид задания {$task['ACTIVITY']}";
};

$results = [];
foreach ($acceptance['scenarios'] as $scenario) {
    $since = (new \Bitrix\Main\Type\DateTime())->format('Y-m-d H:i:s');
    $out = ['name' => $scenario['name'], 'ok' => true, 'item_id' => null, 'steps' => [], 'checks' => []];

    // Элемент — от имени инициатора, если такая роль есть (автозапуск идёт от создателя)
    $itemFields = $scenario['item'] + $acceptance['item'];
    $projectId = $findProject(isset($itemFields['Проект']) ? (string) $itemFields['Проект'] : null);
    $data = ['TITLE' => "EVAL {$task}: {$scenario['name']}"];
    foreach ($itemFields as $name => $value) {
        if (eval_norm($name) === eval_norm('Название')) {
            continue;
        }
        $code = $fields[eval_norm($name)] ?? eval_fail("у «Заявок» нет поля «{$name}»");
        $data[$code] = eval_norm($name) === eval_norm('Проект') ? $projectId : $value;
    }
    $initiator = isset($roles['Инициатор']) ? $roleUsers('Инициатор')[0] : 1;
    $data['ASSIGNED_BY_ID'] = $initiator;
    $GLOBALS['USER']->Authorize($initiator);
    $item = $factory->createItem($data);
    $add = $factory->getAddOperation($item)->disableCheckAccess()->launch();   // автозапуск БП остаётся
    $GLOBALS['USER']->Authorize(1);
    if (!$add->isSuccess()) {
        $out['ok'] = false;
        $out['checks'][] = ['check' => 'создание элемента', 'ok' => false, 'expected' => 'создан', 'actual' => implode('; ', $add->getErrorMessages())];
        $results[] = $out;
        continue;
    }
    $itemId = $item->getId();
    $out['item_id'] = $itemId;
    $docId = ['crm', \Bitrix\Crm\Integration\BizProc\Document\Dynamic::class, "DYNAMIC_{$entityTypeId}_{$itemId}"];
    if ($acceptance['start'] === 'manual') {
        $errors = [];
        \CBPDocument::startWorkflow($templateId, $docId, $acceptance['parameters'], $errors);
        if ($errors) {
            $out['ok'] = false;
            $out['checks'][] = ['check' => 'ручной запуск', 'ok' => false, 'expected' => 'запущен', 'actual' => json_encode($errors, JSON_UNESCAPED_UNICODE)];
            $results[] = $out;
            continue;
        }
    }

    // Шаги
    foreach ($scenario['steps'] as $step) {
        $users = $roleUsers($step['task_for']);
        $found = null;
        for ($i = 0; $i < 10 && !$found; $i++) {
            foreach ($openTasks($itemId) as $t) {
                if (in_array((int) $t['USER_ID'], $users, true)
                    && ($step['title_contains'] === null || mb_stripos($t['NAME'], $step['title_contains']) !== false)) {
                    $found = $t;
                    break;
                }
            }
            if (!$found) {
                sleep(1);
            }
        }
        if (!$found) {
            $ok = $step['optional'];
            $out['ok'] = $out['ok'] && $ok;
            $open = array_map(fn ($t) => "#{$t['USER_ID']}: {$t['NAME']}", $openTasks($itemId));
            $out['steps'][] = ['task_for' => $step['task_for'], 'do' => $step['do'], 'ok' => $ok,
                'detail' => ($ok ? 'необязательный шаг: задания нет' : 'задания для роли нет') . ($open ? '; открыты: ' . implode(' | ', $open) : '; открытых заданий нет')];
            continue;
        }
        $full = \CBPTaskService::GetList([], ['ID' => $found['ID']], false, false,
            ['ID', 'WORKFLOW_ID', 'ACTIVITY', 'ACTIVITY_NAME', 'NAME', 'DESCRIPTION', 'PARAMETERS'])->Fetch();
        $where = "задание #{$found['ID']} «{$full['NAME']}» ({$full['ACTIVITY']}), пользователь #{$found['USER_ID']}";
        $request = $taskRequest($full, $step, (int) $found['USER_ID']);
        if (is_string($request)) {
            $out['ok'] = false;
            $out['steps'][] = ['task_for' => $step['task_for'], 'do' => $step['do'], 'ok' => false, 'detail' => "{$where}: {$request}"];
            continue;
        }
        \CBPActivity::IncludeActivityFile($full['ACTIVITY']);
        $class = 'CBP' . $full['ACTIVITY'];
        $errors = [];
        $posted = $class::PostTaskForm($full, (int) $found['USER_ID'], $request, $errors, eval_user_name((int) $found['USER_ID']));
        $out['ok'] = $out['ok'] && (bool) $posted;
        $out['steps'][] = ['task_for' => $step['task_for'], 'do' => $step['do'], 'ok' => (bool) $posted,
            'detail' => $posted ? $where : "{$where}: " . json_encode($errors, JSON_UNESCAPED_UNICODE)];
    }

    // Проверки
    $expect = $scenario['expect'];
    $running = fn () => (int) $db->query("SELECT COUNT(*) AS C FROM b_bp_workflow_state s JOIN b_bp_workflow_instance i ON i.ID = s.ID
        WHERE s.DOCUMENT_ID = 'DYNAMIC_{$entityTypeId}_{$itemId}' AND s.WORKFLOW_TEMPLATE_ID = {$templateId}")->fetch()['C'];
    if (($expect['process'] ?? null) === 'completed') {
        for ($i = 0; $i < 10 && $running() > 0; $i++) {
            sleep(1);
        }
    }
    $check = function (string $name, bool $ok, mixed $expected, mixed $actual) use (&$out) {
        $out['checks'][] = ['check' => $name, 'ok' => $ok, 'expected' => $expected, 'actual' => $actual];
        $out['ok'] = $out['ok'] && $ok;
    };
    $item = $factory->getItem($itemId);
    foreach ($expect as $key => $value) {
        switch ($key) {
            case 'stage':
                $actual = $stageName($item->getStageId());
                $check('стадия', eval_norm($actual) === eval_norm($value), $value, $actual);
                break;
            case 'process':
                $actual = $running() > 0 ? 'running' : 'completed';
                $check('процесс', $actual === $value, $value, $actual);
                break;
            case 'history_contains':
                // «история» в ТЗ — запись в историю CRM или комментарий в карточке: заказчики зовут так оба
                $texts = array_merge(
                    array_column($db->query("SELECT e.EVENT_TEXT_1 FROM b_crm_event e JOIN b_crm_event_relations r ON r.EVENT_ID = e.ID
                        WHERE r.ENTITY_TYPE = 'DYNAMIC_{$entityTypeId}' AND r.ENTITY_ID = {$itemId}")->fetchAll(), 'EVENT_TEXT_1'),
                    array_column($db->query("SELECT t.COMMENT FROM b_crm_timeline t JOIN b_crm_timeline_bind b ON b.OWNER_ID = t.ID
                        WHERE b.ENTITY_TYPE_ID = {$entityTypeId} AND b.ENTITY_ID = {$itemId}")->fetchAll(), 'COMMENT'));
                foreach ((array) $value as $fragment) {
                    $hit = array_filter($texts, fn ($t) => mb_stripos(strip_tags((string) $t), $fragment) !== false);
                    $check("история: «{$fragment}»", (bool) $hit, $fragment, $hit ? 'есть' : 'нет; записей: ' . count($texts));
                }
                break;
            case 'notify':
                foreach ((array) $value as $n) {
                    $users = implode(',', $roleUsers($n['to'])) ?: '0';
                    // уведомление (чат уведомлений получателя, TYPE = S) или сообщение ему в личный чат (TYPE = P)
                    $messages = array_column($db->query("SELECT m.MESSAGE FROM b_im_message m JOIN b_im_chat c ON c.ID = m.CHAT_ID
                        WHERE m.DATE_CREATE >= '{$since}' AND (
                            (c.TYPE = 'S' AND c.AUTHOR_ID IN ({$users}))
                            OR (c.TYPE = 'P' AND m.AUTHOR_ID NOT IN ({$users})
                                AND c.ID IN (SELECT r.CHAT_ID FROM b_im_relation r WHERE r.USER_ID IN ({$users}))))")->fetchAll(), 'MESSAGE');
                    $contains = $n['contains'] ?? null;
                    $hit = array_filter($messages, fn ($m) => $contains === null || mb_stripos(strip_tags((string) $m), $contains) !== false);
                    $check("уведомление: {$n['to']}" . ($contains ? " «{$contains}»" : ''), (bool) $hit, $contains ?? 'есть',
                        $hit ? 'есть' : 'нет; уведомлений роли: ' . count($messages));
                }
                break;
            case 'no_task_for':
                foreach ((array) $value as $role) {
                    $users = implode(',', $roleUsers($role)) ?: '0';
                    $count = (int) $db->query("SELECT COUNT(*) AS C FROM b_bp_task t JOIN b_bp_task_user tu ON tu.TASK_ID = t.ID
                        JOIN b_bp_workflow_state s ON s.ID = t.WORKFLOW_ID
                        WHERE s.DOCUMENT_ID = 'DYNAMIC_{$entityTypeId}_{$itemId}' AND tu.USER_ID IN ({$users})")->fetch()['C'];
                    $check("нет задания: {$role}", $count === 0, 0, $count);
                }
                break;
        }
    }
    $results[] = $out;
}
eval_out(['scenarios' => $results]);
