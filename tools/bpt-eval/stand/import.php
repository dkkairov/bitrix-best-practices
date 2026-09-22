<?php
// Импорт шаблона тем же методом, что кнопка «Импорт» в дизайнере и REST (ImportTemplate).
// Перед импортом: другие активные шаблоны оценки на «Заявках» деактивируются (изоляция задач).
// Ключ бизнес-провала — message, не error: Stand::run() трактует любой error в ответе как
// аварийный сбой стенда (eval_fail) и бросает EvalException — тут же нужен обычный {ok: false}.
$documentType = eval_document_type(EVAL_REQUESTS_CODE);
$rs = \CBPWorkflowTemplateLoader::GetList([], ['DOCUMENT_TYPE' => $documentType, 'ACTIVE' => 'Y'], false, false, ['ID', 'NAME']);
while ($t = $rs->Fetch()) {
    if (str_starts_with((string) $t['NAME'], 'EVAL ')) {
        \CBPWorkflowTemplateLoader::update($t['ID'], ['ACTIVE' => 'N']);
    }
}
try {
    $id = \CBPWorkflowTemplateLoader::ImportTemplate(0, $documentType, (int) eval_input('auto_execute', 0),
        (string) eval_input('name'), 'Оценка навыка: шаблон агента', base64_decode((string) eval_input('bpt_b64'), true));
} catch (\Throwable $e) {
    eval_out(['ok' => false, 'message' => $e->getMessage()]);
    exit(0);
}
if (!$id) {
    $ex = $GLOBALS['APPLICATION']->GetException();
    eval_out(['ok' => false, 'message' => $ex ? $ex->GetString() : 'импорт вернул пустой ID']);
    exit(0);
}
$tpl = \CBPWorkflowTemplateLoader::GetList([], ['ID' => $id], false, false, ['ID', 'CONSTANTS'])->Fetch();
eval_out(['ok' => true, 'template_id' => (int) $id, 'constants' => $tpl['CONSTANTS'] ?: []]);
