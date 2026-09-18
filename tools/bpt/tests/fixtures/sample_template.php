<?php
/**
 * Синтетический шаблон для тестов: корень → параллельное выполнение → две последовательности,
 * в первой — смена стадии. Значения выдуманы, реальных данных портала здесь нет.
 */

declare(strict_types=1);

return [
    'VERSION'  => 2,
    'TEMPLATE' => [[
        'Type'       => 'SequentialWorkflowActivity',
        'Name'       => 'Template',
        'Activated'  => 'Y',
        'Node'       => null,
        'Properties' => ['Title' => 'Bizproc Automation template'],
        'Children'   => [[
            'Type'       => 'ParallelActivity',
            'Name'       => 'A11111_22222_33333_44444',
            'Activated'  => 'Y',
            'Node'       => null,
            'Properties' => ['Title' => 'Параллельное выполнение', 'EditorComment' => ''],
            'Children'   => [
                [
                    'Type'       => 'SequenceActivity',
                    'Name'       => 'A55555_66666_77777_88888',
                    'Activated'  => 'Y',
                    'Node'       => null,
                    'Properties' => ['Title' => 'Automation sequence'],
                    'Children'   => [[
                        'Type'       => 'CrmChangeStatusActivity',
                        'Name'       => 'A99999_11111_22222_33333',
                        'Activated'  => 'Y',
                        'Node'       => null,
                        'Properties' => [
                            'TargetStatus'  => 'DT1000_10:CLIENT',
                            'ModifiedBy'    => [],
                            'Title'         => 'Сменить стадию',
                            'EditorComment' => '',
                        ],
                        'Children'   => [],
                    ]],
                ],
                [
                    'Type'       => 'SequenceActivity',
                    'Name'       => 'A44444_33333_22222_11111',
                    'Activated'  => 'Y',
                    'Node'       => null,
                    'Properties' => ['Title' => 'Automation sequence'],
                    'Children'   => [],
                ],
            ],
        ]],
    ]],
    'PARAMETERS' => [],
    'VARIABLES'  => [],
    'CONSTANTS'  => [],
    'DOCUMENT_FIELDS' => [
        'TITLE'    => ['Name' => 'Название', 'Type' => 'string'],
        'STAGE_ID' => [
            'Name'    => 'Стадия',
            'Type'    => 'select',
            'Options' => [
                'DT1000_10:NEW'    => 'Общая/Новая',
                'DT1000_10:CLIENT' => 'Общая/Клиент',
                'DT1000_10:FAIL'   => 'Общая/Провал',
            ],
        ],
        'UF_CRM_7_1700000000001' => ['Name' => 'Сумма к оплате', 'Type' => 'double'],
    ],
];
