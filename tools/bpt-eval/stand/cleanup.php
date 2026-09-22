<?php
// Уборка после задачи: шаблон — неактивен, незавершённые процессы шаблона — остановлены.
// terminated считает только реально остановленные (по возврату TerminateWorkflow, а не по факту
// вызова) — недобитый процесс предыдущей EVAL-задачи иначе мог бы молча остаться действовать.
$templateId = (int) eval_input('template_id');
\CBPWorkflowTemplateLoader::update($templateId, ['ACTIVE' => 'N']);
$db = \Bitrix\Main\Application::getConnection();
$terminated = 0;
$errors = [];
foreach ($db->query("SELECT s.ID, s.DOCUMENT_ID FROM b_bp_workflow_state s JOIN b_bp_workflow_instance i ON i.ID = s.ID
    WHERE s.WORKFLOW_TEMPLATE_ID = {$templateId}")->fetchAll() as $wf) {
    $wfErrors = [];
    $ok = \CBPDocument::TerminateWorkflow($wf['ID'], ['crm', \Bitrix\Crm\Integration\BizProc\Document\Dynamic::class,
        $wf['DOCUMENT_ID']], $wfErrors, 'Оценка: уборка после задачи');
    if ($ok) {
        $terminated++;
        continue;
    }
    foreach ($wfErrors as $wfError) {
        $errors[] = "{$wf['ID']}: " . ($wfError['message'] ?? 'неизвестная ошибка');
    }
}
eval_out(['deactivated' => true, 'terminated' => $terminated, 'errors' => $errors]);
