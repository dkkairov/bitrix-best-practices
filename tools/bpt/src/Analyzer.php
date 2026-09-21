<?php
/**
 * Анализ шаблона: структура, привязки к порталу, проверки ссылок.
 * Ошибки — то, что точно сломано; предупреждения — то, что стоит посмотреть человеку.
 */

declare(strict_types=1);

final class Analyzer
{
    /**
     * Заголовок корня, который пишет дизайнер в новом шаблоне (экспорт пустого шаблона, 2026-09-21).
     * Такой же у шаблонов роботов, поэтому вид шаблона по нему не определить; в старых шаблонах
     * встречается «Последовательный бизнес-процесс».
     */
    public const ROOT_TITLE = 'Bizproc Automation template';

    /** Действия, которые нельзя генерировать и переносить без отдельного решения. */
    public const FORBIDDEN_TYPES = [
        'CodeActivity' => 'выполнение PHP-кода (только коробка)',
    ];

    /** Действия-ожидания: без таймаута процесс может «висеть» бесконечно. */
    public const WAITING_TYPES = [
        'ApproveActivity', 'ReviewActivity', 'RequestInformationActivity',
        'RequestInformationOptionalActivity',
    ];

    /** Привязки к конкретному порталу, которые ищем в логике шаблона. */
    public const PORTAL_PATTERNS = [
        'users'        => '/\buser_\d+\b/',
        'groups'       => '/\bgroup_[a-z]+\d+\b/',
        'uf_fields'    => '/\bUF_[A-Z0-9_]+\b/',
        'smart_types'  => '/\bDYNAMIC_\d+\b/',
        'parent_links' => '/\bPARENT_ID_\d+\b/',
        'stages'       => '/\b(?:DT\d+_\d+|C\d+):[A-Z0-9_]+\b/',
        'iblocks'      => '/\biblock_\d+\b/',
        'globals'      => '/\{=Global[A-Za-z]*:[^}]+\}/',
        'urls'         => '#https?://[^\s"\'<>{}]+#',
        'emails'       => '/[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}/',
    ];

    /** Системные UF-поля, одинаковые на всех порталах, — не считаем привязкой. */
    public const SYSTEM_UF = ['UF_CRM_TASK', 'UF_TASK_WEBDAV_FILES', 'UF_DEPARTMENT', 'UF_PHONE_INNER'];

    /** Свойства, где «голое» число почти наверняка ID пользователя. */
    public const USER_PROP_RE = '/user|responsible|auditor|accomplice|approver|voter|created_by|modified_by/i';

    public function __construct(private readonly ?Catalog $catalog = null)
    {
    }

    /** @param array{data: array, serialized: ?string, compressed: ?int} $bpt */
    public function analyze(string $file, array $bpt): array
    {
        $data = $bpt['data'];
        $root = $data['TEMPLATE'][0];
        $logic = [
            'TEMPLATE'   => $data['TEMPLATE'],
            'PARAMETERS' => $data['PARAMETERS'] ?? [],
            'VARIABLES'  => $data['VARIABLES'] ?? [],
            'CONSTANTS'  => $data['CONSTANTS'] ?? [],
        ];
        $logicJson = BptFile::flatJson($logic);
        $docFields = $data['DOCUMENT_FIELDS'] ?? [];
        $docFieldsBytes = strlen(BptFile::flatJson($docFields));

        $stats = ['types' => [], 'nodes' => 0, 'depth' => 0, 'deactivated' => 0, 'conditions' => [],
            'names' => [], 'waiting_no_timeout' => [], 'while' => 0, 'forbidden' => [],
            'raw_user_ids' => [], 'set_vars' => [], 'requested' => [], 'doc_fields_used' => [],
            'propvar_used' => [], 'portal_props' => []];
        $this->walk($root, 0, $stats);

        $portal = $this->portalBindings($logicJson, $stats);
        [$errors, $warnings] = $this->checks($data, $logicJson, $docFields, $stats);

        arsort($stats['types']);
        arsort($stats['conditions']);
        $total = strlen($logicJson) + $docFieldsBytes;
        return [
            'file'                  => basename($file),
            'root_title'            => (string) ($root['Properties']['Title'] ?? ''),
            'root_type'             => $root['Type'],
            'version'               => $data['VERSION'] ?? null,
            'compressed_bytes'      => $bpt['compressed'] ?? null,
            'serialized_bytes'      => isset($bpt['serialized']) ? strlen((string) $bpt['serialized']) : null,
            'logic_bytes'           => strlen($logicJson),
            'document_fields'       => count($docFields),
            'document_fields_share' => $total ? (int) round(100 * $docFieldsBytes / $total) : 0,
            'nodes'                 => $stats['nodes'],
            'max_depth'             => $stats['depth'],
            'deactivated'           => $stats['deactivated'],
            'parameters'            => count($data['PARAMETERS'] ?? []),
            'variables'             => count($data['VARIABLES'] ?? []),
            'constants'             => count($data['CONSTANTS'] ?? []),
            'types'                 => $stats['types'],
            'conditions'            => $stats['conditions'],
            'portal_bindings'       => $portal,
            'errors'                => $errors,
            'warnings'              => $warnings,
        ];
    }

    /** Привязки ищем только в логике — метаданные DOCUMENT_FIELDS не в счёт. */
    private function portalBindings(string $logicJson, array $stats): array
    {
        $portal = [];
        foreach (self::PORTAL_PATTERNS as $key => $re) {
            preg_match_all($re, $logicJson, $m);
            $found = array_values(array_unique($m[0]));
            if ($key === 'uf_fields') {
                $found = array_values(array_diff($found, self::SYSTEM_UF));
            }
            if ($found) {
                sort($found);
                $portal[$key] = $found;
            }
        }
        foreach (['raw_user_ids', 'portal_props'] as $key) {
            if ($stats[$key]) {
                $portal[$key] = array_values(array_unique($stats[$key]));
            }
        }
        return $portal;
    }

    /** @return array{0: string[], 1: string[]} ошибки и предупреждения */
    private function checks(array $data, string $logicJson, array $docFields, array $stats): array
    {
        $errors = [];
        $warnings = [];

        $dupes = array_keys(array_filter(array_count_values($stats['names']), fn ($n) => $n > 1));
        if ($dupes) {
            $errors[] = 'повторяющиеся имена действий: ' . implode(', ', $dupes);
        }
        preg_match_all('/\{=(A\d+_\d+_\d+_\d+):/', $logicJson, $m);
        $dangling = array_values(array_diff(array_unique($m[1]), $stats['names']));
        if ($dangling) {
            $errors[] = 'ссылки на несуществующие действия: ' . implode(', ', $dangling);
        }

        $declared = [
            'Variable' => array_merge(array_keys($data['VARIABLES'] ?? []), $stats['requested']),
            'Constant' => array_keys($data['CONSTANTS'] ?? []),
            'Template' => array_keys($data['PARAMETERS'] ?? []),
        ];
        foreach ($declared as $kind => $names) {
            preg_match_all('/\{=' . $kind . ':([^\s:}>]+)/u', $logicJson, $m);
            $used = array_map(fn ($n) => preg_replace('/_printable$/i', '', $n), $m[1]);
            if ($kind === 'Variable') {
                $used = array_merge($used, $stats['set_vars']);
            }
            $missing = array_values(array_diff(array_unique($used), $names));
            if ($missing) {
                $errors[] = "необъявленные {$kind}: " . implode(', ', $missing);
            }
        }
        // propertyvariablecondition проверяет параметры, переменные и константы шаблона
        $missing = array_values(array_diff(array_unique($stats['propvar_used']), array_merge(...array_values($declared))));
        if ($missing) {
            $errors[] = 'условия по необъявленным параметрам/переменным: ' . implode(', ', $missing);
        }
        foreach (array_unique($stats['forbidden']) as $type) {
            $errors[] = "запрещённое действие {$type}: " . self::FORBIDDEN_TYPES[$type];
        }

        if ($docFields) {
            preg_match_all('/\{=Document:([^\s:}>]+)/u', $logicJson, $m);
            $used = array_merge(
                array_map(fn ($n) => preg_replace('/_printable$/i', '', $n), $m[1]),
                $stats['doc_fields_used']
            );
            $unknown = array_values(array_diff(array_unique($used), array_keys($docFields)));
            if ($unknown) {
                $warnings[] = 'поля документа, которых нет в DOCUMENT_FIELDS (удалены или переименованы?): '
                    . implode(', ', $unknown);
            }
        }
        foreach ($stats['waiting_no_timeout'] as $w) {
            $warnings[] = "ожидание без таймаута: {$w}";
        }
        if ($stats['while']) {
            $warnings[] = "циклы WhileActivity: {$stats['while']} — проверьте условие выхода";
        }
        return [$errors, $warnings];
    }

    private function walk(array $node, int $depth, array &$s): void
    {
        $type = (string) ($node['Type'] ?? '?');
        $props = is_array($node['Properties'] ?? null) ? $node['Properties'] : [];
        $name = (string) ($node['Name'] ?? '');
        $label = trim($type . ' ' . $name . ' «' . ($props['Title'] ?? '') . '»');

        $s['types'][$type] = ($s['types'][$type] ?? 0) + 1;
        $s['nodes']++;
        $s['depth'] = max($s['depth'], $depth);
        $s['names'][] = $name;
        if (($node['Activated'] ?? 'Y') === 'N') {
            $s['deactivated']++;
        }
        if (isset(self::FORBIDDEN_TYPES[$type])) {
            $s['forbidden'][] = $type;
        }
        if ($type === 'WhileActivity') {
            $s['while']++;
        }
        if (in_array($type, self::WAITING_TYPES, true)
            && in_array((string) ($props['TimeoutDuration'] ?? ''), ['', '0'], true)) {
            $s['waiting_no_timeout'][] = $label;
        }
        foreach ($props as $key => $value) {
            if (is_string($key) && str_ends_with(strtolower($key), 'condition')) {
                $s['conditions'][$key] = ($s['conditions'][$key] ?? 0) + 1;
                $this->collectConditionFields($key, $value, $s);
            }
            // Свойства-идентификаторы портала известны каталогу (например, TemplateId, DynamicTypeId)
            if (is_string($key) && is_scalar($value) && (string) $value !== ''
                && $this->catalog?->propType($type, $key) === 'portal-id') {
                $s['portal_props'][] = "{$type}.{$key}={$value}";
            }
        }
        if ($type === 'SetFieldActivity' && is_array($props['FieldValue'] ?? null)) {
            array_push($s['doc_fields_used'], ...array_map('strval', array_keys($props['FieldValue'])));
        }
        if ($type === 'SetVariableActivity' && is_array($props['VariableValue'] ?? null)) {
            array_push($s['set_vars'], ...array_map('strval', array_keys($props['VariableValue'])));
        }
        foreach ((array) ($props['RequestedInformation'] ?? []) as $field) {
            if (is_array($field) && isset($field['Name'])) {
                $s['requested'][] = (string) $field['Name'];
            }
        }
        $this->collectRawUserIds($props, $name, $s);

        foreach ((array) ($node['Children'] ?? []) as $child) {
            if (is_array($child)) {
                $this->walk($child, $depth + 1, $s);
            }
        }
    }

    /**
     * Что проверяют условия: fieldcondition и propertyvariablecondition — списки
     * [имя, оператор, значение, связка]; mixedcondition — объекты {object, field, operator, value, joiner}.
     */
    private function collectConditionFields(string $kind, mixed $value, array &$s): void
    {
        if (!is_array($value)) {
            return;
        }
        foreach ($value as $cond) {
            if (!is_array($cond)) {
                continue;
            }
            if ($kind === 'fieldcondition' && isset($cond[0])) {
                $s['doc_fields_used'][] = (string) $cond[0];
            } elseif ($kind === 'propertyvariablecondition' && isset($cond[0])) {
                $s['propvar_used'][] = (string) $cond[0];
            } elseif ($kind === 'mixedcondition' && ($cond['object'] ?? '') === 'Document') {
                $s['doc_fields_used'][] = (string) ($cond['field'] ?? '');
            }
        }
    }

    private function collectRawUserIds(array $props, string $activity, array &$s, string $path = ''): void
    {
        foreach ($props as $key => $value) {
            $keyPath = $path === '' ? (string) $key : "{$path}.{$key}";
            if (is_array($value)) {
                if (is_string($key) && preg_match(self::USER_PROP_RE, $key)) {
                    foreach ($value as $item) {
                        if ((is_string($item) && ctype_digit($item)) || is_int($item)) {
                            $s['raw_user_ids'][] = "{$keyPath}={$item} ({$activity})";
                        }
                    }
                }
                $this->collectRawUserIds($value, $activity, $s, $keyPath);
            } elseif (is_string($key) && preg_match(self::USER_PROP_RE, $key)
                && (is_int($value) || (is_string($value) && ctype_digit($value) && $value !== '0'))) {
                $s['raw_user_ids'][] = "{$keyPath}={$value} ({$activity})";
            }
        }
    }
}
