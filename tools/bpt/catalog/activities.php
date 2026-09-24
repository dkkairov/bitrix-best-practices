<?php
/**
 * Каталог действий бизнес-процессов Bitrix24.
 *
 * Источники:
 *   - корпус из 16 экспортов из дизайнера БП (кнопка «Экспорт») для смарт-процессов коробки клиента,
 *     2026-09: какие свойства встречаются, какие значения выглядят как значения по умолчанию, какие
 *     результаты брали по ссылкам {=A…:Результат}. До 2026-09-21 корпус ошибочно считался шаблонами
 *     роботов облака: корень «Bizproc Automation template» дизайнер пишет и в обычных шаблонах;
 *   - официальный курс «Бизнес-процессы» (COURSE_ID=57, dev.1c-bitrix.ru, снимок 2026-09-22):
 *     смысл полей, варианты, поведение. Урок указан в комментарии: «урок 3771» =
 *     https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=3771;
 *   - код ядра на стенде (коробка, bizproc 26.1075.0): .description.php (названия, результаты),
 *     ValidateProperties (что проверяет импорт), диалоги настроек (варианты значений).
 * Спорное помечено комментарием.
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
 * Ключи свойства:
 *   required       без свойства портал не примет шаблон при импорте (ValidateProperties) — ошибка сборки
 *   default        значение по умолчанию при сборке
 *   values         допустимые значения, которые проверяет ядро: другое — ошибка сборки
 *   options        варианты дизайнера с расшифровкой (значение → смысл): другое — предупреждение
 *   required_keys  ключи отображения, без которых ядро не примет шаблон; «A|B» — хватит любого
 *   note           смысл и поведение — по курсу 57 и ядру
 *
 * Ключи действия: title (заголовок по умолчанию — как NAME в .description.php, его ставит дизайнер),
 * alias, shape, props, required_any (группы свойств: ядро требует хотя бы одно из группы),
 * returns (результаты, которые брали по ссылкам в корпусе; строка — имя
 * свойства со списком полей), returns_more (остальные результаты по курсу и ядру), note, observed
 * (сколько раз тип встретился в корпусе), title_guess (заголовок не подтверждён), forbidden.
 *
 * Формы вложенности (shape): root, sequence, branch, leaf, waiting, waiting-branches,
 * ifelse, parallel, loop, block.
 */

declare(strict_types=1);

// Связка условий подтверждена ядром: пусто/0 — «и», иначе «или» (CBPActivityCondition::getJoiner);
// «и» сильнее «или» (Bizproc\Activity\ConditionGroup::evaluate, урок 3789)
$joiner = 'связка: 0 — «и», 1 — «или»; «и» сильнее «или». «Или» в первой строке делает всё условие истинным';
// Операторы — общий список для всех трёх условий: один UI-диалог (расширение bizproc.condition,
// JS-класс Operator) и один обработчик ядра (Bitrix\Bizproc\Activity\Condition::checkValue +
// backed enum Enum\Operator, 14 кодов). В диалоге дизайнера почти не зависят от типа поля —
// ограничены только `between` (int/double/date/datetime/time) и полем с BaseType `document` (там
// из списка — только empty/!empty); `modified` — только у fieldcondition, и только если документ
// поддерживает отметку изменённых полей (у propertyvariablecondition/mixedcondition эта возможность
// выключена жёстко). Импорт сам оператор не проверяет (ValidateProperties условий всегда пуст) —
// ошибка в неверном коде проявится не на импорте, а при выполнении (тихо станет строгим «=»).
// Стенд, bizproc 26.1075.0, 2026-09-24: шаблон с fieldcondition [OPPORTUNITY,"<=","1000","0"] и
// [TITLE,"contain","OPTEST","0"] на DYNAMIC_2 (Сделка) принят и сохранён без изменений
// (CBPWorkflowTemplateLoader::ImportTemplate, шаблон #111, деактивирован после проверки).
// Источники и файл:строка — .superpowers/sdd/PLAN/operators-report.md
$operators = 'операторы (общие для fieldcondition/propertyvariablecondition/mixedcondition): `=` равно,'
    . ' `!=` не равно, `>` больше, `>=` не меньше, `<` меньше, `<=` не больше, `in` содержится в'
    . ' списке, `!in` не содержится в списке, `contain` содержит, `!contain` не содержит, `!empty`'
    . ' заполнено, `empty` не заполнено, `between` между (только int/double/date/datetime/time),'
    . ' `modified` было изменено (только у fieldcondition, если документ поддерживает отметку'
    . ' изменённых полей)';
$conditions = [
    'fieldcondition'            => ['type' => 'list', 'note' => 'по полям документа: `[[поле, оператор, значение, связка]]`; ' . $joiner . '; ' . $operators],
    'propertyvariablecondition' => ['type' => 'list', 'note' => 'по параметрам и переменным: `[[код, оператор, значение, связка]]`; ' . $joiner . '; ' . $operators],
    'mixedcondition'            => ['type' => 'list', 'note' => 'смешанное: `[{object, field, operator, value, joiner}]`; ' . $joiner . '; ' . $operators],
    'truecondition'             => ['type' => 'str', 'note' => "'1' — условие «Истина»: так делают ветку «иначе» (урок 3789)"],
];

/**
 * Общие свойства заданий-ожиданий (утверждение, ознакомление, запрос информации).
 * Поля — по урокам 3771, 3783, 3782, 7839 и диалогам ядра.
 */
$waitingCommon = [
    'Users'               => ['type' => 'list', 'required' => true],
    'Name'                => ['type' => 'text', 'required' => true],
    'Description'         => ['type' => 'text', 'default' => ''],
    'StatusMessage'       => ['type' => 'text', 'default' => '', 'note' => '«Текст статуса» документа, пока ждём'],
    'SetStatusMessage'    => ['type' => 'yn',   'default' => 'Y', 'note' => '«Устанавливать текст статуса»'],
    'ShowComment'         => ['type' => 'yn',   'default' => 'Y', 'note' => 'показывать исполнителю поле для пояснения'],
    // Варианты у каждого задания свои — см. CommentRequired ниже
    'CommentRequired'     => ['type' => 'str',  'default' => 'N', 'options' => ['N' => 'нет', 'Y' => 'да']],
    'CommentLabelMessage' => ['type' => 'str',  'default' => 'Пояснение', 'note' => 'подпись поля для пояснения'],
    // Урок 3771: пусто или 0 — без срока; меньше «Минимального времени ожидания для действий»
    // (настройка модуля, урок 4678) нельзя — ядро поднимет срок до минимума (calculateExpirationTime)
    'TimeoutDuration'     => ['type' => 'str',  'default' => '', 'note' => 'срок задания в единицах TimeoutDurationType;'
        . ' пусто или 0 — без срока, процесс ждёт исполнителя. Минимум — «Минимальное время ожидания'
        . ' для действий» в настройках модуля'],
    // Другие единицы ядро молча считает секундами
    'TimeoutDurationType' => ['type' => 'str',  'default' => 's', 'options' => ['s' => 'секунды', 'm' => 'минуты', 'h' => 'часы', 'd' => 'дни']],
    // Ядро: в поле задания OVERDUE_DATE идёт момент истечения таймаута, а без таймаута — это свойство
    'OverdueDate'         => ['type' => 'str',  'default' => '', 'note' => 'срок задания в списке заданий, если'
        . ' таймаут не задан; процесс не двигает'],
    'AccessControl'       => ['type' => 'yn',   'default' => 'N', 'note' => '«Ограничить доступ»: Y — текст задания видит'
        . ' только исполнитель, в живую ленту оно не попадает (урок 3771)'],
    // Константы CBPTaskDelegationType; в ядре по умолчанию 0, в корпусе встречалось 1.
    // Урок 3771: при 0 и 1 в задании есть кнопка «Делегировать»; администратор делегирует и при запрете
    'DelegationType'      => ['type' => 'str',  'default' => '1', 'options' => [
        '0' => 'только подчинённым', '1' => 'всем сотрудникам', '2' => 'никому']],
];

return [
    'version'  => 1,
    'verified' => '2026-09-22 / корпус 16 экспортов коробки клиента; курс 57 dev.1c-bitrix.ru (снимок 2026-09-22); ядро стенда bizproc 26.1075.0',

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
            'title'    => 'Ветка',   // в дизайнере ветка называется «Условие» (NAME в ядре); сборщик пишет «Ветка»
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
            // Урок 3789
            'note'     => 'ветки проверяются по порядку, выполняется первая с истинным условием; если не'
                . ' подошла ни одна — конструкция пропускается. Ветку «иначе» делают условием «Истина»',
            'observed' => 113,
        ],
        'ParallelActivity' => [
            'alias'    => 'parallel',
            'title'    => 'Параллельное выполнение',
            'shape'    => 'parallel',
            'props'    => [],
            // Урок 3791: ветки идут слева направо; ожидание в одной ветке не останавливает другие
            'note'     => 'ветки выполняются слева направо, ожидание в одной не держит другие; дальше процесс'
                . ' идёт, когда завершены все ветки',
            'observed' => 21,
        ],
        'WhileActivity' => [
            'alias'    => 'while',
            'title'    => 'Цикл',
            'shape'    => 'loop',
            'props'    => $conditions,
            // Урок 3792; ядро: условие проверяется и перед первой итерацией (execute → tryNextIteration);
            // CYCLE_LIMIT = 1000 и опция модуля limit_while_iterations (с 25.100.0, урок 4678)
            'note'     => 'повторяет тело, пока условие истинно; условие проверяется перед каждой итерацией,'
                . ' начатая итерация доходит до конца. Не больше 1000 итераций (в облаке; в коробке — настройка'
                . ' модуля «Максимальное количество итераций цикла в рамках одного запуска»)',
            'observed' => 11,
        ],
        'EmptyBlockActivity' => [
            'alias'    => 'block',
            'title'    => 'Блок действий',
            'shape'    => 'block',
            'props'    => [],
            'note'     => 'группирует шаги и сворачивается на схеме; выключенный блок выключает всё внутри (уроки 3808, 12321)',
            'observed' => 28,
        ],

        // ---------------------------------------------------------- работа с документом
        'SetFieldActivity' => [
            'alias'    => 'set_field',
            'title'    => 'Изменение документа',
            'shape'    => 'leaf',
            'props'    => [
                'FieldValue'          => ['type' => 'map', 'required' => true],  // код поля → значение
                'ModifiedBy'          => ['type' => 'list', 'default' => [], 'note' => '«Изменять от имени» (урок 3785)'],
                'MergeMultipleFields' => ['type' => 'yn', 'default' => 'N', 'options' => [
                    'N' => 'перезаписать множественные поля', 'Y' => 'дополнить множественные поля']],
            ],
            'returns_more' => ['ErrorMessage'],
            // Урок 3785: результат «Текст ошибки изменения» с bizproc 23.200.0, пуст при успехе
            'note'     => 'ErrorMessage — текст ошибки изменения, пусто при успехе: проверяют смешанным условием'
                . ' «заполнено». Выражения внутри одного действия видят старые значения полей (урок 12407)',
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
                // DT<тип>_<воронка>:СТАДИЯ. Урок 9011: в лидах, КП, счетах и смарт-процессах поле называется
                // «Изменить на статус». Стадия не из воронки документа — ошибка в журнал, процесс идёт дальше
                'TargetStatus' => ['type' => 'portal-id', 'required' => true],
                'ModifiedBy'   => ['type' => 'list', 'default' => [], 'note' => '«Изменить от имени»'],
            ],
            // Урок 9011; ядро: CBPDocument::TerminateWorkflow «Завершён в результате изменения стадии»;
            // стенд 2026-09-22: шаг после смены стадии не выполнился
            'note'     => 'после смены стадии процесс сразу завершается — шаги после неё не выполняются.'
                . ' Больше двух смен на одну стадию документа за хит — ошибка «подозрение на рекурсию» и завершение',
            'observed' => 43,
        ],
        'CrmSetObserverField' => [
            'alias'    => 'observers',
            'title'    => 'Изменить наблюдателей',
            'shape'    => 'leaf',
            'props'    => [
                // Урок 20768; константы CBPCrmSetObserverField::ACTION_*_OBSERVERS
                'ActionOnObservers' => ['type' => 'str', 'default' => 'add', 'options' => [
                    'add' => 'добавить', 'replace' => 'заменить', 'remove' => 'удалить']],
                'Observers'         => ['type' => 'list', 'required' => true],
            ],
            'observed' => 9,
        ],
        'CrmGetDynamicInfoActivity' => [
            'alias'    => 'get_smart_item',
            // NAME в bizproc 26.1075.0; в корпусе — прежнее «Получить информацию об элементе смарт-процесса»
            'title'    => 'Получить информацию об элементе CRM',
            'shape'    => 'leaf',
            'props'    => [
                'DynamicTypeId'        => ['type' => 'portal-id', 'required' => true],
                'ReturnFields'         => ['type' => 'list', 'required' => true],
                // В ядре по умолчанию N (любой тип CRM), в корпусе — Y
                'OnlyDynamicEntities'  => ['type' => 'yn', 'default' => 'Y', 'options' => [
                    'Y' => 'только смарт-процессы', 'N' => 'любой тип элемента CRM']],
                'DynamicFilterFields'  => ['type' => 'map', 'default' => []],
                'DynamicEntityFields'  => ['type' => 'map', 'default' => []],
            ],
            'returns'  => 'ReturnFields',   // возвращает поля, перечисленные в ReturnFields
            'returns_more' => ['Document'], // ссылка на найденный элемент (ADDITIONAL_RESULT DynamicEntityFields)
            'note'     => 'находит элемент по фильтру DynamicFilterFields; если не нашёл — ошибка «не найдено ни одного элемента»',
            'observed' => 7,
        ],
        'CrmGetRelationsInfoActivity' => [
            'alias'    => 'get_parent_item',
            'title'    => 'Получить информацию о привязанном элементе',
            'shape'    => 'leaf',
            'props'    => [
                'ParentTypeId'       => ['type' => 'portal-id', 'required' => true, 'note' => '«Связь с»: тип привязанного элемента'],
                'ParentEntityFields' => ['type' => 'map', 'default' => []],   // описание полей родителя
            ],
            'returns'  => 'ParentEntityFields',   // возвращает поля привязанного элемента
            'note'     => 'нет привязанного элемента этого типа — ошибка «не привязано ни одной сущности»',
            'observed' => 1,
        ],

        // ---------------------------------------------------------- сообщения и задачи
        'CrmEventAddActivity' => [
            'alias'    => 'crm_event',
            'title'    => 'Запись события в crm',
            'shape'    => 'leaf',
            'props'    => [
                // Урок 3778; системные значения справочника CRM «Тип события» (crm, install/index.php).
                // Свои значения справочника портала тоже допустимы
                'EventType' => ['type' => 'str', 'default' => 'INFO', 'options' => [
                    'INFO' => 'Информация', 'PHONE' => 'Телефонный звонок', 'MESSAGE' => 'Отправлен email']],
                'EventText' => ['type' => 'text', 'required' => true],
                'EventUser' => ['type' => 'list', 'default' => [], 'note' => '«Автор» записи'],
            ],
            'note'     => 'запись во вкладку «История» карточки CRM (урок 3778)',
            'observed' => 99,
        ],
        'CrmTimelineCommentAdd' => [
            'alias'    => 'timeline_comment',
            'title'    => 'Добавить комментарий в элемент',
            'shape'    => 'leaf',
            'props'    => [
                'CommentText' => ['type' => 'text', 'required' => true, 'note' => 'комментарий в таймлайн, BBCode (урок 20760)'],
                'CommentUser' => ['type' => 'list', 'default' => [], 'note' => '«Автор» комментария'],
            ],
            'observed' => 24,
        ],
        'IMNotifyActivity' => [
            'alias'    => 'notify',
            'title'    => 'Уведомление пользователя',
            'shape'    => 'leaf',
            'props'    => [
                'MessageSite'     => ['type' => 'text', 'required' => true, 'note' => '«Текст уведомления для сайта», BBCode'],
                'MessageOut'      => ['type' => 'text', 'default' => '', 'note' => '«Текст уведомления для email/jabber»; пусто — текст для сайта'],
                // Урок 3862; константы модуля im: IM_NOTIFY_FROM = 2, IM_NOTIFY_SYSTEM = 4
                'MessageType'     => ['type' => 'str', 'default' => '2', 'options' => [
                    '2' => 'персонализированное (с аватаром) — от отправителя',
                    '4' => 'от системы (без аватара) — отправитель не показывается']],
                // Обязателен: ValidateProperties отклоняет импорт без отправителя; импортирующий не
                // администратор может указать только себя (bizproc 26.1075.0, стенд 2026-09-22)
                'MessageUserFrom' => ['type' => 'list', 'required' => true, 'note' => 'нужен и при системном типе, хотя тогда не показывается'],
                'MessageUserTo'   => ['type' => 'list', 'required' => true],
            ],
            'observed' => 20,
        ],
        'ImMessageActivity' => [
            'alias'    => 'chat_message',
            'title'    => 'Отправить сообщение сотруднику в чат',
            'shape'    => 'leaf',
            'props'    => [
                // Обязателен: validateProperties проверяет обязательные поля карты, отправитель среди них
                // (bizproc 26.1075.0). Урок 26988: группа в поле — отправитель с наименьшим ID
                'MessageUserFrom' => ['type' => 'list', 'required' => true],
                'MessageUserTo'   => ['type' => 'list', 'required' => true],
                // Урок 26988; Bitrix\Im\Integration\Bizproc\Message\Collection
                'MessageTemplate' => ['type' => 'str', 'default' => 'notify', 'options' => [
                    'plain' => 'Базовое', 'news' => 'Объявление', 'notify' => 'Уведомление',
                    'important' => 'Важное', 'alert' => 'Авария']],
                'MessageFields'   => ['type' => 'map', 'required' => true,   // MessageText, MessageTitle
                                      'note' => 'MessageText; для news и important нужен ещё MessageTitle'],
            ],
            'note'     => 'личное сообщение в рабочий чат с отправителем (урок 26988)',
            'observed' => 3,
        ],
        'Task2Activity' => [
            'alias'    => 'task',
            'title'    => 'Поставить задачу',
            'shape'    => 'leaf',
            'props'    => [
                // Ключи — как у задачи. Обязательные — по Task2Activity::validateProperties
                // (bizproc 26.1075.0): постановщик и название; ответственный — если не задан поток FLOW_ID
                'Fields'                  => ['type' => 'map', 'required' => true,
                                              'required_keys' => ['TITLE', 'CREATED_BY', 'RESPONSIBLE_ID|FLOW_ID']],
                'HoldToClose'             => ['type' => 'yn', 'default' => 'N', 'note' => '«Остановить процесс на время'
                    . ' выполнения задачи»: Y — ждать закрытия, тогда есть ClosedBy и ClosedDate (урок 3805)'],
                'AUTO_LINK_TO_CRM_ENTITY' => ['type' => 'yn', 'default' => 'Y', 'note' => '«Привязать к текущей сущности CRM»'],
                'AsChildTask'             => ['type' => 'str', 'default' => '', 'note' => '«Создать как подзадачу к текущей» — для процессов задач'],
                'CheckListItems'          => ['type' => 'list', 'default' => []],
                'TimeEstimateHour'        => ['type' => 'str', 'default' => ''],
                'TimeEstimateMin'         => ['type' => 'str', 'default' => ''],
            ],
            // .description.php ядра; урок 3805 называет «ID задачи»
            'returns_more' => ['TaskId', 'ClosedBy', 'ClosedDate', 'IsDeleted'],
            'observed' => 6,
        ],

        // ---------------------------------------------------------- время и запуск
        'RobotDelayActivity' => [
            'alias'    => 'delay',
            'title'    => 'Пауза робота',
            'shape'    => 'leaf',
            'props'    => [
                // Урок 25792: режимы «Промежуток» (период) и «Время»; дата в прошлом — действие пропускается
                'TimeoutTime'       => ['type' => 'expr', 'note' => 'до какого времени ждать; дата в прошлом —'
                    . ' пауза пропускается'],
                // Период вместо времени: CBPDelayActivity::ValidateProperties требует одно из двух (стенд
                // 2026-09-22: пауза только с периодом проходит проверку импорта). Без значений по умолчанию —
                // в корпусе этих свойств нет
                'TimeoutDuration'     => ['type' => 'str', 'note' => 'период паузы в единицах TimeoutDurationType;'
                    . ' минимум — «Минимальное время ожидания для действий» (в облаке 5 минут)'],
                'TimeoutDurationType' => ['type' => 'str', 'options' => ['s' => 'секунды', 'm' => 'минуты', 'h' => 'часы', 'd' => 'дни']],
                'TimeoutTimeIsLocal' => ['type' => 'yn', 'default' => 'N'],
                'WriteToLog'        => ['type' => 'yn', 'default' => 'Y', 'note' => 'писать в журнал записи о паузе'],
                'WaitWorkDayUser'   => ['type' => 'list', 'default' => [], 'note' => 'после паузы ждать начала'
                    . ' рабочего дня сотрудника, без предела по времени (урок 25792)'],
            ],
            'required_any' => [['TimeoutTime', 'TimeoutDuration']],
            'note'     => 'паузу нельзя прервать; с паузы процесс снимает агент, поэтому он может проснуться позже заданного (урок 3807)',
            'observed' => 13,
        ],
        'StartWorkflowActivity' => [
            'alias'    => 'start_workflow',
            'title'    => 'Запустить бизнес-процесс',
            'shape'    => 'leaf',
            'props'    => [
                'DocumentId'         => ['type' => 'expr', 'required' => true, 'note' => 'документ того же типа, что у шаблона'],
                'TemplateId'         => ['type' => 'portal-id', 'required' => true],
                // Урок 7721; ядро: подписка на OnWorkflowComplete
                'UseSubscription'    => ['type' => 'yn', 'default' => 'N', 'options' => [
                    'N' => 'не ждать', 'Y' => 'ждать завершения запущенного процесса']],
                'TemplateParameters' => ['type' => 'map', 'default' => []],
            ],
            'returns_more' => ['WorkflowId'],
            // Урок 7721; ядро: BPSWFA_SELFSTART_ERROR, BPSWFA_ACCESS_DENIED_1
            'note'     => 'настройки и импорт — только администратор; запуск своего же шаблона блокируется (ошибка'
                . ' в журнал, процесс идёт дальше). Автор нового процесса — автор исходного',
            'observed' => 4,
        ],

        // ---------------------------------------------------------- задания людям
        'ApproveActivity' => [
            'alias'    => 'approve',
            'title'    => 'Утверждение документа',
            'shape'    => 'waiting-branches',
            // array_merge, а не «+»: частные значения должны перекрывать общие
            'props'    => array_merge($waitingCommon, [
                // Значения — из ApproveActivity::ValidateProperties (bizproc 26.1075.0, стенд 2026-09-22),
                // смысл — урок 3771 и код решения в OnExternalEvent
                'ApproveType'        => ['type' => 'str', 'default' => 'all', 'values' => ['all', 'any', 'vote'],
                    'options' => [
                        'all'  => 'все сотрудники: первое «нет» отклоняет, «да» — когда утвердили все',
                        'any'  => 'любой сотрудник: решает первый голос',
                        'vote' => 'голосование: процент от всех назначенных']],
                'ApproveMinPercent'  => ['type' => 'str', 'default' => '50', 'note' => 'для vote: утверждено, когда'
                    . ' процент утвердивших строго больше этого числа'],
                'ApproveWaitForAll'  => ['type' => 'yn', 'default' => 'N', 'options' => [
                    'N' => 'решить, как только наберётся процент', 'Y' => 'ждать, пока проголосуют все']],
                'Parameters'         => ['type' => 'str', 'default' => ''],
                'CommentRequired'    => ['type' => 'str', 'default' => 'N', 'options' => [
                    'N' => 'нет', 'Y' => 'да', 'YA' => 'только при утверждении', 'YR' => 'только при отклонении']],
                // Подписи кнопок в дизайнере по умолчанию (lang ядра); в корпусе обе кнопки переименованы
                'TaskButton1Message' => ['type' => 'str', 'default' => 'Утвердить документ'],
                'TaskButton2Message' => ['type' => 'str', 'default' => 'Отклонить'],
            ]),
            'returns'  => ['Comments', 'LastApprover'],
            // Урок 3771 и RETURN в .description.php
            'returns_more' => ['TaskId', 'VotedCount', 'TotalCount', 'VotedPercent', 'ApprovedPercent',
                'NotApprovedPercent', 'ApprovedCount', 'NotApprovedCount', 'LastApproverComment',
                'UserApprovers', 'Approvers', 'UserRejecters', 'Rejecters', 'IsTimeout'],
            // Урок 3771; ядро: OnExternalEvent → ExecuteOnNonApprove
            'note'     => 'ждёт решения; по истечении срока документ автоматически отклонён: ветка «нет»,'
                . ' IsTimeout = 1. Comments — «ФИО (e-mail): Утвержден/Отклонен» и пояснение',
            'observed' => 9,
        ],
        'ReviewActivity' => [
            'alias'    => 'review',
            'title'    => 'Ознакомление с документом',   // NAME в ядре, урок 3783
            'shape'    => 'waiting',
            'props'    => array_merge($waitingCommon, [
                'ApproveType'         => ['type' => 'str', 'default' => 'all', 'options' => [
                    'all' => 'должны ознакомиться все', 'any' => 'достаточно любого']],
                'Parameters'          => ['type' => 'str', 'default' => ''],
                'TaskButtonMessage'   => ['type' => 'str', 'default' => 'Принято'],
                'CommentLabelMessage' => ['type' => 'str', 'default' => 'Комментарий'],
            ]),
            'returns'  => ['Comments', 'LastReviewer'],
            'returns_more' => ['TaskId', 'ReviewedCount', 'TotalCount', 'IsTimeout', 'LastReviewerComment'],
            // Урок 3783; ядро: CloseActivity
            'note'     => 'по истечении срока задание завершается автоматически (IsTimeout = 1), процесс идёт дальше',
            'observed' => 18,
        ],
        'RequestInformationActivity' => [
            'alias'    => 'request_info',
            'title'    => 'Запрос дополнительной информации',
            'shape'    => 'waiting',
            'props'    => array_merge($waitingCommon, [
                'RequestedInformation' => ['type' => 'defs', 'required' => true, 'note' => 'поля запроса; ответы'
                    . ' записываются в переменные процесса с теми же кодами'],
                'TaskButtonMessage'    => ['type' => 'str', 'default' => 'Принято'],
            ]),
            'returns'  => ['Comments', 'InfoUser'],
            'returns_more' => ['TaskId', 'IsTimeout', 'Changes'],
            // Урок 3782; ядро: closeActivity
            'note'     => 'задание выполняет первый приступивший из Users; по истечении срока — автоматическое'
                . ' завершение (IsTimeout = 1, InfoUser пуст), процесс идёт дальше',
            'observed' => 6,
        ],
        'RequestInformationOptionalActivity' => [
            'alias'    => 'request_info_optional',
            'title'    => 'Запрос доп.информации (с отклонением)',   // NAME в ядре, урок 7839
            'shape'    => 'waiting-branches',
            'props'    => array_merge($waitingCommon, [
                'RequestedInformation'    => ['type' => 'defs', 'required' => true],
                'TaskButtonMessage'       => ['type' => 'str', 'default' => 'Принято'],
                // Ядро: getPropertiesDialogMap, completeTask. Урок 7839 показывает поле только на скриншоте
                'CancelType'              => ['type' => 'str', 'default' => 'any', 'options' => [
                    'any' => 'отклоняет любой сотрудник', 'all' => 'отклонено, когда отказали все']],
                'TaskButtonCancelMessage' => ['type' => 'str', 'default' => 'Отклонить'],
                'SaveVariables'           => ['type' => 'yn', 'default' => 'N', 'note' => '«Сохранять значения в'
                    . ' случае отказа» (с bizproc 20.200.0, урок 7839)'],
                // Урок 7839: четыре варианта с bizproc 21.400.0
                'CommentRequired'         => ['type' => 'str', 'default' => 'N', 'options' => [
                    'N' => 'нет', 'Y' => 'да', 'YA' => 'только при утверждении (вводе)', 'YR' => 'только при отклонении']],
            ]),
            'returns'  => ['Comments', 'InfoUser'],
            'returns_more' => ['TaskId', 'IsTimeout'],
            // Урок 7839 говорит только «задание автоматически завершается»; ветку показывает код ядра
            // (closeActivity переопределён и запускает вторую последовательность)
            'note'     => 'по истечении срока — автоматическое завершение (IsTimeout = 1) и ветка отклонения',
            'observed' => 3,
        ],

        // ---------------------------------------------------------- запрещено генерировать
        'CodeActivity' => [
            'alias'     => 'php_code',
            'title'     => 'PHP код',   // NAME в ядре, урок 3806
            'shape'     => 'leaf',
            'props'     => ['ExecuteCode' => ['type' => 'text', 'required' => true]],
            'forbidden' => 'выполнение PHP-кода (только коробка)',
            'observed'  => 0,
        ],
    ],
];
