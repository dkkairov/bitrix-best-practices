<?php
// Общая часть скриптов стенда: ядро Битрикса, администратор, помощники. Выполняется в контейнере php.
// Без use/declare/namespace: файл склеивается с другими в один поток (tools/bpt-eval/src/Stand.php).
$_SERVER['DOCUMENT_ROOT'] = '/opt/www';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
foreach (['crm', 'bizproc', 'iblock', 'tasks', 'im'] as $module) {
    \Bitrix\Main\Loader::includeModule($module);
}
$GLOBALS['USER']->Authorize(1);

const EVAL_REQUESTS_CODE = 'PILOT_REQUESTS';   // «Заявки» — тип пилота; setup создаст, если его нет
const EVAL_PROJECTS_CODE = 'EVAL_PROJECTS';

function eval_out(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function eval_fail(string $message): never
{
    eval_out(['error' => $message]);
    exit(0);
}

function eval_input(string $key, mixed $default = null): mixed
{
    return $GLOBALS['EVAL_INPUT'][$key] ?? $default;
}

function eval_norm(string $s): string
{
    return trim(str_replace('ё', 'е', mb_strtolower($s)));
}

function eval_type(string $code): ?\Bitrix\Main\ORM\Objectify\EntityObject
{
    $class = \Bitrix\Crm\Service\Container::getInstance()->getDynamicTypeDataClass();
    return $class::getList(['filter' => ['=CODE' => $code]])->fetchObject() ?: null;
}

function eval_factory(string $code): \Bitrix\Crm\Service\Factory
{
    $type = eval_type($code) ?? eval_fail("нет смарт-процесса с кодом {$code} — запустите eval.php prepare");
    return \Bitrix\Crm\Service\Container::getInstance()->getFactory((int) $type->getEntityTypeId());
}

function eval_document_type(string $code): array
{
    return ['crm', \Bitrix\Crm\Integration\BizProc\Document\Dynamic::class,
        'DYNAMIC_' . eval_factory($code)->getEntityTypeId()];
}

/** Поля документа: название → код (как в снимке), без печатных копий и ссылочных полей. */
function eval_fields(string $code): array
{
    $map = [];
    $fields = \CBPRuntime::GetRuntime(true)->getDocumentService()->GetDocumentFields(eval_document_type($code), true);
    foreach ($fields as $fieldCode => $field) {
        if (str_ends_with(mb_strtoupper((string) $fieldCode), '_PRINTABLE') || str_contains((string) $fieldCode, '.')) {
            continue;
        }
        $map[eval_norm((string) ($field['Name'] ?? $fieldCode))] = (string) $fieldCode;
    }
    return $map;
}

/** Пользователь по «Имя Фамилия» (как их заводит человек: «Юрист Тест»). */
function eval_user_id(string $fullName): ?int
{
    $rs = \CUser::GetList('ID', 'asc', ['ACTIVE' => 'Y'], ['FIELDS' => ['ID', 'NAME', 'LAST_NAME']]);
    while ($u = $rs->Fetch()) {
        if (eval_norm(trim($u['NAME'] . ' ' . $u['LAST_NAME'])) === eval_norm($fullName)) {
            return (int) $u['ID'];
        }
    }
    return null;
}

/** «Имя Фамилия» — так ответ на задание подписывается в результате Comments («ФИО (e-mail): …»). */
function eval_user_name(int $id): string
{
    $u = \CUser::GetByID($id)->Fetch();
    return $u ? trim($u['NAME'] . ' ' . $u['LAST_NAME']) : "#{$id}";
}

/** Значение поля запроса информации, которого нет в проверках задачи: по типу поля. */
function eval_default_value(array $field, int $userId): mixed
{
    return match ((string) ($field['Type'] ?? 'string')) {
        'date' => (new \Bitrix\Main\Type\Date())->toString(),
        'datetime' => (new \Bitrix\Main\Type\DateTime())->toString(),
        'int', 'double' => '1',
        'bool' => 'Y',
        'user' => 'user_' . $userId,
        'select' => (string) array_key_first((array) ($field['Options'] ?? [])),
        default => 'Оценка: значение по умолчанию',
    };
}

function eval_group_id(string $name): ?int
{
    $rs = \CGroup::GetList('id', 'asc', ['NAME' => $name]);
    while ($g = $rs->Fetch()) {
        if (eval_norm($g['NAME']) === eval_norm($name)) {
            return (int) $g['ID'];
        }
    }
    return null;
}

function eval_department_id(string $name): ?int
{
    $iblockId = (int) \COption::GetOptionInt('intranet', 'iblock_structure', 0);
    $rs = \CIBlockSection::GetList([], ['IBLOCK_ID' => $iblockId, 'NAME' => $name], false, ['ID', 'NAME']);
    while ($s = $rs->Fetch()) {
        if (eval_norm($s['NAME']) === eval_norm($name)) {
            return (int) $s['ID'];
        }
    }
    return null;
}
