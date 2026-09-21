<?php
/**
 * Каталог действий бизнес-процессов Bitrix24.
 *
 * Источник — разбор корпуса из 16 экспортов из дизайнера БП (кнопка «Экспорт») для смарт-процессов
 * коробки клиента, 2026-09. До 2026-09-21 корпус ошибочно считался шаблонами роботов облака:
 * корень «Bizproc Automation template» дизайнер пишет и в обычных шаблонах.
 * Официальной документации по свойствам действий нет, поэтому здесь только наблюдаемые факты:
 * какие свойства встречаются, какие значения выглядят как значения по умолчанию, что действие
 * возвращает (по ссылкам {=A…:Результат} в корпусе). Спорное помечено комментарием.
 *
 * Типы свойств:
 *   str       короткая строка или перечисление ('all', 'add', 's')
 *   text      текст, часто с выражениями Bitrix
 *   expr      выражение или формула ('=workdateadd(...)')
 *   yn        флаг 'Y' / 'N'
 *   list      список значений
 *   map       отображение: поле → значение
 *   defs      список описаний полей ({Name, Title, Type, Required, Multiple, Default})
 *   portal-id идентификатор, свой на каждом портале (стадия, шаблон, смарт-процесс)
 *
 * Формы вложенности (shape): root, sequence, branch, leaf, waiting, waiting-branches,
 * ifelse, parallel, loop, block.
 *
 * `observed` — сколько раз тип встретился в корпусе; `title_guess` — заголовок по умолчанию
 * в корпусе не наблюдался (везде свой) и взят по смыслу, проверить в дизайнере.
 */

declare(strict_types=1);

$conditions = [
    'fieldcondition'            => ['type' => 'list'],   // [[поле, оператор, значение, связка]]
    'propertyvariablecondition' => ['type' => 'list'],   // [[параметр/переменная, оператор, значение, связка]]
    'mixedcondition'            => ['type' => 'list'],   // [{object, field, operator, value, joiner}]
    'truecondition'             => ['type' => 'str'],    // '1' — ветка «иначе»
];

/** Общие свойства заданий-ожиданий (утверждение, ознакомление, запрос информации). */
$waitingCommon = [
    'Users'               => ['type' => 'list', 'required' => true],
    'Name'                => ['type' => 'text', 'required' => true],
    'Description'         => ['type' => 'text', 'default' => ''],
    'StatusMessage'       => ['type' => 'text', 'default' => ''],
    'SetStatusMessage'    => ['type' => 'yn',   'default' => 'Y'],
    'ShowComment'         => ['type' => 'yn',   'default' => 'Y'],
    'CommentRequired'     => ['type' => 'yn',   'default' => 'N'],
    'CommentLabelMessage' => ['type' => 'str',  'default' => 'Пояснение'],
    'TimeoutDuration'     => ['type' => 'str',  'default' => ''],
    'TimeoutDurationType' => ['type' => 'str',  'default' => 's'],
    'OverdueDate'         => ['type' => 'str',  'default' => ''],
    'AccessControl'       => ['type' => 'yn',   'default' => 'N'],
    'DelegationType'      => ['type' => 'str',  'default' => '1'],
];

return [
    'version'  => 1,
    'verified' => '2026-09-21 / коробка клиента (версия модулей не зафиксирована), корпус 16 экспортов из дизайнера БП',

    // Свойства, которые есть у любого действия
    'common' => [
        'Title'         => ['type' => 'text'],
        'EditorComment' => ['type' => 'text', 'default' => ''],
        '_DesMinimized' => ['type' => 'yn'],   // состояние узла в дизайнере: читаем, сами не пишем
    ],

    'activities' => [

        // ---------------------------------------------------------- служебные узлы
        'SequentialWorkflowActivity' => [
            'title'    => 'Последовательный бизнес-процесс',
            'shape'    => 'root',
            'props'    => ['Permission' => ['type' => 'list', 'default' => []]],
            'observed' => 16,
        ],
        'SequenceActivity' => [
            'title'    => 'Последовательность действий',
            'shape'    => 'sequence',
            'props'    => [],
            'observed' => 125,
        ],
        'IfElseBranchActivity' => [
            'title'    => 'Ветка',
            'shape'    => 'branch',
            'props'    => $conditions,
            'observed' => 264,
        ],

        // ---------------------------------------------------------- структура
        'IfElseActivity' => [
            'alias'    => 'if',
            'title'    => 'Условие',
            'shape'    => 'ifelse',
            'props'    => [],
            'observed' => 113,
        ],
        'ParallelActivity' => [
            'alias'    => 'parallel',
            'title'    => 'Параллельное выполнение',
            'shape'    => 'parallel',
            'props'    => [],
            'observed' => 21,
        ],
        'WhileActivity' => [
            'alias'    => 'while',
            'title'    => 'Цикл',
            'shape'    => 'loop',
            'props'    => $conditions,
            'observed' => 11,
        ],
        'EmptyBlockActivity' => [
            'alias'    => 'block',
            'title'    => 'Блок действий',
            'shape'    => 'block',
            'props'    => [],
            'observed' => 28,
        ],

        // ---------------------------------------------------------- работа с документом
        'SetFieldActivity' => [
            'alias'    => 'set_field',
            'title'    => 'Изменение документа',
            'shape'    => 'leaf',
            'props'    => [
                'FieldValue'          => ['type' => 'map', 'required' => true],  // код поля → значение
                'ModifiedBy'          => ['type' => 'list', 'default' => []],
                'MergeMultipleFields' => ['type' => 'yn', 'default' => 'N'],
            ],
            'observed' => 210,
        ],
        'SetVariableActivity' => [
            'alias'    => 'set_var',
            'title'    => 'Изменение переменных',
            'shape'    => 'leaf',
            'props'    => ['VariableValue' => ['type' => 'map', 'required' => true]],
            'observed' => 62,
        ],
        'CrmChangeStatusActivity' => [
            'alias'    => 'change_stage',
            'title'    => 'Сменить стадию',
            'shape'    => 'leaf',
            'props'    => [
                'TargetStatus' => ['type' => 'portal-id', 'required' => true],   // DT<тип>_<воронка>:СТАДИЯ
                'ModifiedBy'   => ['type' => 'list', 'default' => []],
            ],
            'observed' => 43,
        ],
        'CrmSetObserverField' => [
            'alias'    => 'observers',
            'title'    => 'Изменить наблюдателей',
            'shape'    => 'leaf',
            'props'    => [
                'ActionOnObservers' => ['type' => 'str', 'default' => 'add'],    // add | remove
                'Observers'         => ['type' => 'list', 'required' => true],
            ],
            'observed' => 9,
        ],
        'CrmGetDynamicInfoActivity' => [
            'alias'    => 'get_smart_item',
            'title'    => 'Получить информацию об элементе смарт-процесса',
            'shape'    => 'leaf',
            'props'    => [
                'DynamicTypeId'        => ['type' => 'portal-id', 'required' => true],
                'ReturnFields'         => ['type' => 'list', 'required' => true],
                'OnlyDynamicEntities'  => ['type' => 'yn', 'default' => 'Y'],
                'DynamicFilterFields'  => ['type' => 'map', 'default' => []],
                'DynamicEntityFields'  => ['type' => 'map', 'default' => []],
            ],
            'returns'  => 'ReturnFields',   // возвращает поля, перечисленные в ReturnFields
            'observed' => 7,
        ],
        'CrmGetRelationsInfoActivity' => [
            'alias'    => 'get_parent_item',
            'title'    => 'Получить информацию о привязанном элементе',
            'shape'    => 'leaf',
            'props'    => [
                'ParentTypeId'       => ['type' => 'portal-id', 'required' => true],
                'ParentEntityFields' => ['type' => 'map', 'default' => []],   // описание полей родителя
            ],
            'returns'  => 'ParentEntityFields',   // возвращает поля привязанного элемента
            'observed' => 1,
        ],

        // ---------------------------------------------------------- сообщения и задачи
        'CrmEventAddActivity' => [
            'alias'    => 'crm_event',
            'title'    => 'Запись события в crm',
            'shape'    => 'leaf',
            'props'    => [
                'EventType' => ['type' => 'str', 'default' => 'INFO'],
                'EventText' => ['type' => 'text', 'required' => true],
                'EventUser' => ['type' => 'list', 'default' => []],
            ],
            'observed' => 99,
        ],
        'CrmTimelineCommentAdd' => [
            'alias'    => 'timeline_comment',
            'title'    => 'Добавить комментарий в элемент',
            'shape'    => 'leaf',
            'props'    => [
                'CommentText' => ['type' => 'text', 'required' => true],
                'CommentUser' => ['type' => 'list', 'default' => []],
            ],
            'observed' => 24,
        ],
        'IMNotifyActivity' => [
            'alias'    => 'notify',
            'title'    => 'Уведомление пользователя',
            'shape'    => 'leaf',
            'props'    => [
                'MessageSite'     => ['type' => 'text', 'required' => true],
                'MessageOut'      => ['type' => 'text', 'default' => ''],
                'MessageType'     => ['type' => 'str', 'default' => '2'],   // в корпусе встречались 2 и 4
                'MessageUserFrom' => ['type' => 'list', 'default' => []],
                'MessageUserTo'   => ['type' => 'list', 'required' => true],
            ],
            'observed' => 20,
        ],
        'ImMessageActivity' => [
            'alias'    => 'chat_message',
            'title'    => 'Отправить сообщение сотруднику в чат',
            'shape'    => 'leaf',
            'props'    => [
                'MessageUserFrom' => ['type' => 'str', 'default' => ''],
                'MessageUserTo'   => ['type' => 'list', 'required' => true],
                'MessageTemplate' => ['type' => 'str', 'default' => 'notify'],  // в корпусе: notify, important, alert
                'MessageFields'   => ['type' => 'map', 'required' => true],     // MessageText, MessageTitle
            ],
            'observed' => 3,
        ],
        'Task2Activity' => [
            'alias'    => 'task',
            'title'    => 'Поставить задачу',
            'shape'    => 'leaf',
            'props'    => [
                'Fields'                  => ['type' => 'map', 'required' => true,
                                              'required_keys' => ['TITLE', 'RESPONSIBLE_ID']],
                'HoldToClose'             => ['type' => 'yn', 'default' => 'N'],
                'AUTO_LINK_TO_CRM_ENTITY' => ['type' => 'yn', 'default' => 'Y'],
                'AsChildTask'             => ['type' => 'str', 'default' => ''],
                'CheckListItems'          => ['type' => 'list', 'default' => []],
                'TimeEstimateHour'        => ['type' => 'str', 'default' => ''],
                'TimeEstimateMin'         => ['type' => 'str', 'default' => ''],
            ],
            'observed' => 6,
        ],

        // ---------------------------------------------------------- время и запуск
        'RobotDelayActivity' => [
            'alias'    => 'delay',
            'title'    => 'Пауза робота',
            'shape'    => 'leaf',
            'props'    => [
                'TimeoutTime'       => ['type' => 'expr', 'required' => true],
                'TimeoutTimeIsLocal' => ['type' => 'yn', 'default' => 'N'],
                'WriteToLog'        => ['type' => 'yn', 'default' => 'Y'],
                'WaitWorkDayUser'   => ['type' => 'list', 'default' => []],
            ],
            'observed' => 13,
        ],
        'StartWorkflowActivity' => [
            'alias'    => 'start_workflow',
            'title'    => 'Запустить бизнес-процесс',
            'shape'    => 'leaf',
            'props'    => [
                'DocumentId'         => ['type' => 'expr', 'required' => true],
                'TemplateId'         => ['type' => 'portal-id', 'required' => true],
                'UseSubscription'    => ['type' => 'yn', 'default' => 'N'],
                'TemplateParameters' => ['type' => 'map', 'default' => []],
            ],
            'observed' => 4,
        ],

        // ---------------------------------------------------------- задания людям
        'ApproveActivity' => [
            'alias'    => 'approve',
            'title'    => 'Утверждение документа',
            'title_guess' => true,
            'shape'    => 'waiting-branches',
            // array_merge, а не «+»: частные значения должны перекрывать общие
            'props'    => array_merge($waitingCommon, [
                'ApproveType'        => ['type' => 'str', 'default' => 'all'],
                'ApproveMinPercent'  => ['type' => 'str', 'default' => '50'],
                'ApproveWaitForAll'  => ['type' => 'yn', 'default' => 'N'],
                'Parameters'         => ['type' => 'str', 'default' => ''],
                // В корпусе обе кнопки переименованы; здесь — нейтральные подписи
                'TaskButton1Message' => ['type' => 'str', 'default' => 'Утвердить'],
                'TaskButton2Message' => ['type' => 'str', 'default' => 'Отклонить'],
            ]),
            'returns'  => ['Comments', 'LastApprover'],
            'observed' => 9,
        ],
        'ReviewActivity' => [
            'alias'    => 'review',
            'title'    => 'Ознакомление',
            'title_guess' => true,
            'shape'    => 'waiting',
            'props'    => array_merge($waitingCommon, [
                'ApproveType'         => ['type' => 'str', 'default' => 'all'],
                'Parameters'          => ['type' => 'str', 'default' => ''],
                'TaskButtonMessage'   => ['type' => 'str', 'default' => 'Принято'],
                'CommentLabelMessage' => ['type' => 'str', 'default' => 'Комментарий'],
            ]),
            'returns'  => ['Comments', 'LastReviewer'],
            'observed' => 18,
        ],
        'RequestInformationActivity' => [
            'alias'    => 'request_info',
            'title'    => 'Запрос дополнительной информации',
            'title_guess' => true,
            'shape'    => 'waiting',
            'props'    => array_merge($waitingCommon, [
                'RequestedInformation' => ['type' => 'defs', 'required' => true],
                'TaskButtonMessage'    => ['type' => 'str', 'default' => 'Принято'],
            ]),
            'returns'  => ['Comments', 'InfoUser'],
            'observed' => 6,
        ],
        'RequestInformationOptionalActivity' => [
            'alias'    => 'request_info_optional',
            'title'    => 'Запрос информации с возможностью отклонить',
            'title_guess' => true,
            'shape'    => 'waiting-branches',
            'props'    => array_merge($waitingCommon, [
                'RequestedInformation'    => ['type' => 'defs', 'required' => true],
                'TaskButtonMessage'       => ['type' => 'str', 'default' => 'Принято'],
                'CancelType'              => ['type' => 'str', 'default' => 'any'],
                'TaskButtonCancelMessage' => ['type' => 'str', 'default' => 'Отклонить'],
                'SaveVariables'           => ['type' => 'yn', 'default' => 'N'],
            ]),
            'returns'  => ['Comments', 'InfoUser'],
            'observed' => 3,
        ],

        // ---------------------------------------------------------- запрещено генерировать
        'CodeActivity' => [
            'alias'     => 'php_code',
            'title'     => 'Выполнение PHP-кода',
            'shape'     => 'leaf',
            'props'     => ['ExecuteCode' => ['type' => 'text', 'required' => true]],
            'forbidden' => 'выполнение PHP-кода (только коробка)',
            'observed'  => 0,
        ],
    ],
];
