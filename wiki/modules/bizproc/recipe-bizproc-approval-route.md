---
title: "Маршрут согласования: срок, доработка в цикле, итог"
type: recipe
module: bizproc
edition: both
status: draft
provenance: mixed
verified: ""
tags: [бизнес-процессы, согласование, утверждение, доработка, цикл, таймаут, типовой-процесс]
sources: ["[[source-course57-actions-core]]", "[[source-course57-actions-notify-other]]", "[[source-course57-examples]]"]
related: ["[[checklist-bizproc-template-review]]", "[[concept-bizproc-activity-catalog]]", "[[pattern-bizproc-ai-assisted-generation]]", "[[antipattern-bizproc-hardcoded-portal-ids]]", "[[recipe-bizproc-request-intake]]", "[[concept-bizproc-expressions]]"]
aliases: []
updated: "2026-09-22"
---

# Маршрут согласования: срок, доработка в цикле, итог

**Результат:** документ уходит на согласование с ограниченным сроком; при отказе автор дорабатывает
его и отправляет на новый круг (не больше трёх); по истечении срока процесс не висит, а завершается
итогом «срок истёк»; стадия меняется последним шагом.

> **Черновик.** Спецификация ниже собирается `tools/bpt` и проходит проверку импорта на стенде
> (`validateTemplate`, коробка, bizproc 26.1075.0, 2026-09-22), но целиком процесс на стенде не
> прогонялся. Поведение действий — по курсу 57 и коду ядра.

## Предусловия
- Решено, что нужен БП, а не роботы ([[pattern-robots-vs-bizproc-decision]]); процесс запускается
  при создании или вручную — не «при изменении».
- Согласующие известны как роль (группа, отдел, поле документа), а не как конкретные люди.
- Для сборки из спецификации — снимок портала (`bpt.php snapshot`) со стадиями и группами.

## Шаги
1. **Роли — в константы шаблона:** «Согласующие» и «Кому сообщать об итоге». Тогда перенос на другой
   портал и замена согласующего — правка одной константы
   ([[antipattern-bizproc-hardcoded-portal-ids|зашитые ID]]).
2. **Переменные состояния:** «Итог» (`rework` → `approved` / `expired`), счётчик кругов, признак
   истёкшего срока, поле для ответа автора.
3. **Цикл «пока на доработке и кругов меньше трёх».** Счётчик — аварийный выход: без него отказ за
   отказом крутят цикл бесконечно (курс,
   [урок 8445](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=8445)); предел ядра —
   1000 итераций.
4. **«Утверждение документа» со сроком.** Тип «Любой сотрудник» (решает первый голос), срок 2 дня,
   пояснение обязательно при отклонении (`CommentRequired: YR`), делегирование «никому». По истечении
   срока документ автоматически отклонён: ветка «нет», результат «Автоматическое отклонение»
   (`IsTimeout`) = 1 ([урок 3771](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=3771)).
5. **На ветке «нет» различить отказ и истечение срока:** записать `IsTimeout` в переменную и проверить
   условием. Отказ — «Запрос дополнительной информации» автору со сроком 3 дня: ответ ляжет в
   переменную с кодом поля, замечания согласующих — `{=A…:Comments}`. Срок истёк — итог `expired`.
6. **После цикла — итог:** согласовано → «Сменить стадию»; иначе → уведомление и «Сменить стадию».
   Смена стадии — последний шаг ветки: она завершает процесс
   ([урок 9011](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=9011)).
7. **Уведомление — «от системы»** (`MessageType: "4"`) с отправителем из константы: «Последний
   голосовавший» после автоматического отклонения пуст, потому что никто не голосовал (вывод по коду
   ядра), а отправитель у уведомления обязателен.

```yaml
# Формат — tools/bpt/SPEC.md; плейсхолдеры {{…}} берутся из снимка портала
bizproc: 1
name: Согласование с доработкой
document: DYNAMIC_1000
constants:
  approvers: {Name: Согласующие, Type: user, Multiple: true, Required: true, Default: ["{{group:Бухгалтерия}}"]}
  supervisor: {Name: Кому сообщать об итоге, Type: user, Required: true, Default: "{{user:Иванов Иван}}"}
variables:
  decision: {Name: Итог, Type: string, Default: rework}
  round: {Name: Круг согласования, Type: int, Default: "0"}
  timeout: {Name: Срок истёк, Type: int, Default: "0"}
  rework_note: {Name: Что исправлено, Type: text}
steps:
  - while:
      title: Пока документ на доработке, не больше трёх кругов
      when: {propertyvariablecondition: [[decision, "=", rework, "0"], [round, "<", "3", "0"]]}
      steps:
        - set_var: {title: Следующий круг, VariableValue: {round: "={=Variable:round} + 1"}}
        - approve:
            id: approval
            title: Согласование
            Users: ["{=Constant:approvers}"]
            Name: "Согласуйте {=Document:TITLE}"
            ApproveType: any
            TimeoutDuration: "2"
            TimeoutDurationType: d
            CommentRequired: YR
            DelegationType: "2"
            on_yes:
              - set_var: {title: Согласовано, VariableValue: {decision: approved}}
            on_no:
              - set_var: {title: "Запомнить, истёк ли срок", VariableValue: {timeout: "{=@approval:IsTimeout}"}}
              - if:
                  title: Отклонено или срок истёк?
                  branches:
                    - title: Срок истёк
                      when: {propertyvariablecondition: [[timeout, "=", "1", "0"]]}
                      steps:
                        - set_var: {title: Итог — срок истёк, VariableValue: {decision: expired}}
                    - title: Отклонено — на доработку
                      else: true
                      steps:
                        - request_info:
                            title: Доработка
                            Users: ["{=Document:ASSIGNED_BY_ID}"]
                            Name: "Доработайте {=Document:TITLE}"
                            Description: "Замечания: {=@approval:Comments}"
                            RequestedInformation:
                              - {Name: rework_note, Title: Что исправлено, Type: text, Required: true}
                            TimeoutDuration: "3"
                            TimeoutDurationType: d
  - if:
      title: Итог согласования
      branches:
        - title: Согласовано
          when: {propertyvariablecondition: [[decision, "=", approved, "0"]]}
          steps:
            - change_stage: {TargetStatus: "{{stage:Общая/Клиент}}"}
        - title: Не согласовано
          else: true
          steps:
            - notify:
                title: Сообщить об итоге
                MessageSite: "Не согласовано: {=Document:TITLE}. Итог: {=Variable:decision}"
                MessageType: "4"
                MessageUserFrom: ["{=Constant:supervisor}"]
                MessageUserTo: ["{=Constant:supervisor}", "{=Document:ASSIGNED_BY_ID}"]
            - change_stage: {TargetStatus: "{{stage:Общая/Доработка}}"}
```

```bash
php tools/bpt/bpt.php compile approval.bizproc.yaml --portal=portal.yaml -o approval.bpt
php tools/bpt/bpt.php render approval.bpt
```

## Варианты из курса
- **Голосование.** Тип «Голосование сотрудников» и процент: утверждено, когда доля утвердивших от
  **всех** назначенных строго больше порога; с «Ожидать, чтобы проголосовали все» решение — после всех
  (код ядра; урок 3771).
- **Работу получает первый согласившийся** — голосование с порогом 0 % в цикле и задача утвердившему
  ([урок 7143](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=7143)).
- **Согласующий — руководитель автора с учётом отсутствий:** «Выбор сотрудника» типа «начальник» с
  пропуском отсутствующих и резервом ([урок 5060](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=5060)).
- **Двухэтапное согласование с отчётом** — два задания подряд, отчёт — «Запрос дополнительной
  информации» ([уроки 2791–2793](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=2792)).
  В примере курса у ознакомления нет срока — процесс может ждать вечно; добавляйте срок.

## Проверка результата
- `bpt.php analyze` без ошибок; предупреждение о цикле ожидаемо — выход по счётчику есть.
- На тестовом портале пройти: согласовали; отклонили → доработали → согласовали; отклонили трижды;
  никто не ответил за срок. В каждом случае процесс завершается нужной стадией, в журнале нет висящих
  заданий.

## Откат и проблемы
- Шаблон заменили — уже запущенные процессы идут по старой версии; зависшие удалить в «Все активные».
- Минимальный срок в облаке — 5 минут: для теста срок меньше не поставить
  ([[checklist-bizproc-template-review]]).

## Источники и связанное
- Курс 57: [[source-course57-actions-core]] (задания, условия, цикл), [[source-course57-actions-notify-other]]
  (примеры 3842, 7143, 5060), [[source-course57-examples]] (2791–2793)
- [[concept-bizproc-activity-catalog]] — свойства и результаты «Утверждения» и «Запроса информации»
- [[recipe-bizproc-request-intake]] — заявка с назначением исполнителя

[← Бизнес-процессы](_index-bizproc.md)
