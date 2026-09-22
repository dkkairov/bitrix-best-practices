<?php
// Уборка после задачи: шаблон — неактивен, незавершённые процессы шаблона — остановлены.
$templateId = (int) eval_input('template_id');
\CBPWorkflowTemplateLoader::update($templateId, ['ACTIVE' => 'N']);
$db = \Bitrix\Main\Application::getConnection();
$terminated = 0;
foreach ($db->query("SELECT s.ID, s.DOCUMENT_ID FROM b_bp_workflow_state s JOIN b_bp_workflow_instance i ON i.ID = s.ID
    WHERE s.WORKFLOW_TEMPLATE_ID = {$templateId}")->fetchAll() as $wf) {
    $errors = [];
    \CBPDocument::TerminateWorkflow($wf['ID'], ['crm', \Bitrix\Crm\Integration\BizProc\Document\Dynamic::class,
        $wf['DOCUMENT_ID']], $errors, 'Оценка: уборка после задачи');
    $terminated++;
}
eval_out(['deactivated' => true, 'terminated' => $terminated]);
