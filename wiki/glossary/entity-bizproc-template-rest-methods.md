---
title: "REST-методы шаблонов БП (bizproc.workflow.template.*)"
type: entity
module: bizproc
edition: cloud
status: verified
provenance: documented
verified: "2026-09-22 / apidocs.bitrix24.ru (через MCP): шаблоны — 2026-09-16, bizproc.task.complete, bizproc.activity.add, bizproc.event.send, bizproc.activity.log — 2026-09-22; курс 57 (уроки 3858, 23036)"
tags: [rest, бизнес-процессы, шаблоны, bpt, приложение, роботы]
sources: ["[[source-course57-developer]]", "[[source-course57-templates-designer]]"]
related: ["[[concept-bizproc-bpt-format]]", "[[recipe-rest-oauth-app-setup]]", "[[pattern-bizproc-ai-assisted-generation]]", "[[entity-robots-triggers]]", "[[antipattern-bizproc-hardcoded-portal-ids]]"]
aliases: []
updated: "2026-09-22"
---

# REST-методы шаблонов БП (`bizproc.workflow.template.*`)

**Что это:** методы для загрузки, обновления, получения и удаления шаблонов бизнес-процессов
**из дизайнера**; загрузка — из файла `.bpt` ([[concept-bizproc-bpt-format|формат .bpt]]).

## Ключевые факты
| Поле | Значение |
|------|----------|
| Тип | группа REST-методов |
| Scope | `bizproc` |
| Кто может | `add` / `update` / `delete` — **только в контексте приложения**, входящий вебхук не подойдёт. `update` / `delete` — только для шаблонов, созданных этим же приложением |
| Что недоступно | шаблоны **роботов** (CRM, смарт-процессы, задачи): через REST их нельзя получить, изменить или удалить |
| Тариф | REST API работает только на коммерческих тарифах (иначе `ACCESS_DENIED`) |
| Edition | облако (сверено по документации); коробка не проверялась |
| Офф. дока | [Шаблоны бизнес-процессов: обзор методов](https://apidocs.bitrix24.ru/api-reference/bizproc/template/index.html) |

## Детали
| Метод | Параметры (* — обязательный) | Результат и ограничения |
|-------|------------------------------|-------------------------|
| `bizproc.workflow.template.add` | `DOCUMENT_TYPE`*, `NAME`*, `DESCRIPTION`, `TEMPLATE_DATA`* — файл `[имя, base64]`, `AUTO_EXECUTE` | ID шаблона. Параметра стадии нет, поэтому роботов так не создать |
| `bizproc.workflow.template.update` | `ID`*, `FIELDS`*: `NAME`, `DESCRIPTION`, `TEMPLATE_DATA`, `AUTO_EXECUTE` | ID. Прочие поля молча игнорируются; чужой шаблон → `ERROR_TEMPLATE_NOT_OWNER` |
| `bizproc.workflow.template.list` | `SELECT` (по умолчанию `ID`), `FILTER`, `ORDER`, `start` | По 50 записей на страницу; только шаблоны дизайнера. Шаблоны приложения — фильтр `SYSTEM_CODE` (например, `rest_app_5`) |
| `bizproc.workflow.template.delete` | `ID`* | `null`. Вне приложения → `ACCESS_DENIED: Application context required` |

`AUTO_EXECUTE`: `0` — без автозапуска (по умолчанию), `1` — при создании, `2` — при изменении,
`3` — при создании и изменении.

**`DOCUMENT_TYPE`** — массив из трёх строк `[модуль, объект, тип]`; он определяет, для чего можно
запускать процесс:

| Объект | `DOCUMENT_TYPE` |
|--------|-----------------|
| Лиды | `['crm', 'CCrmDocumentLead', 'LEAD']` |
| Контакты | `['crm', 'CCrmDocumentContact', 'CONTACT']` |
| Компании | `['crm', 'CCrmDocumentCompany', 'COMPANY']` |
| Сделки | `['crm', 'CCrmDocumentDeal', 'DEAL']` |
| Коммерческие предложения | `['crm', 'Bitrix\Crm\Integration\BizProc\Document\Quote', 'QUOTE']` |
| Счета | `['crm', 'Bitrix\Crm\Integration\BizProc\Document\SmartInvoice', 'SMART_INVOICE']` |
| Смарт-процессы | `['crm', 'Bitrix\Crm\Integration\BizProc\Document\Dynamic', 'DYNAMIC_XXX']` |
| Процессы в ленте новостей | `['lists', 'BizprocDocument', 'iblock_XXX']` |
| Списки в группах | `['lists', 'Bitrix\Lists\BizprocDocumentLists', 'iblock_XXX']` |
| Диск | `['disk', 'Bitrix\Disk\BizProcDocument', 'STORAGE_XXX']` |

`XXX` — ID на **целевом** портале; ID смарт-процесса можно узнать через `crm.type.list`.

### Смежные методы для проверки
- `bizproc.workflow.start` — запуск: `TEMPLATE_ID`, `DOCUMENT_ID` (например,
  `['crm', 'CCrmDocumentLead', 'LEAD_1']`), `PARAMETERS` (пользователь передаётся как `user_ID`).
  Через REST запуск доступен только на платных тарифах, demo- и NFR-лицензиях.
- `bizproc.workflow.instances` — список запущенных процессов. `bizproc.workflow.terminate`
  останавливает процесс с сохранением данных, `bizproc.workflow.kill` удаляет его вместе с данными.
- `bizproc.task.list` / `bizproc.task.complete` — задания (утверждение, ознакомление, запрос
  информации), пригодятся для автотестов. У `complete` параметры `TASK_ID`, `STATUS` (`1`/`yes` —
  утверждено, `2`/`no` — отклонено, `3`/`ok` — ознакомлен, `4`/`cancel` — отмена; набор зависит от типа
  задания), `COMMENT` и `FIELDS` — значения полей запроса информации
  ([bizproc.task.complete](https://apidocs.bitrix24.ru/api-reference/bizproc/bizproc-task/bizproc-task-complete.html)).
- `bizproc.robot.add` / `bizproc.activity.add` — регистрируют **своего** робота или действие
  приложения (нужен контекст приложения и права администратора). Роботов на стадии они не расставляют.
  У действия: `CODE`, `HANDLER` (URL на домене приложения), `USE_SUBSCRIPTION` (ждать ответа),
  `PROPERTIES`, `RETURN_PROPERTIES`, `FILTER`
  ([bizproc.activity.add](https://apidocs.bitrix24.ru/api-reference/bizproc/bizproc-activity/bizproc-activity-add.html)).
  В дизайнере такое действие — в разделе «Действия приложений»; обработчик получает `event_token` и
  отвечает `bizproc.event.send` с `RETURN_VALUES` и `LOG_MESSAGE`
  ([bizproc.event.send](https://apidocs.bitrix24.ru/api-reference/bizproc/bizproc-robot/bizproc-event-send.html)),
  а до ответа может писать в журнал `bizproc.activity.log` — если журнал включён в шаблоне. Курс
  (уроки [23036](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=23036), 7771)
  предупреждает, что действия приложения удаляются при его удалении и обновлении; в документации об
  обновлении не сказано, зато есть `bizproc.activity.update`.

## Пример
```js
// Приложение (OAuth): загрузить шаблон для смарт-процесса
const response = await $b24.callMethod('bizproc.workflow.template.add', {
  DOCUMENT_TYPE: ['crm', 'Bitrix\\Crm\\Integration\\BizProc\\Document\\Dynamic', 'DYNAMIC_1000'],
  NAME: 'Согласование счёта',
  AUTO_EXECUTE: 0,
  TEMPLATE_DATA: ['bp-invoice.bpt', base64Content], // base64 от содержимого файла .bpt
});
const templateId = response.getData().result;
```

```php
$base64Content = base64_encode(file_get_contents('bp-invoice.bpt'));
```

## Подводные камни
- **Вебхук не подходит** для `add` / `update` / `delete` — нужно своё приложение
  ([[recipe-rest-oauth-app-setup|OAuth-приложение]]). Шаблон, созданный вручную, через REST не
  обновить — только загрузить новый из приложения.
- **Роботов на стадиях через REST не развернуть**: в облаке это ручной шаг
  ([[entity-robots-triggers|Роботы и триггеры]]).
- `DOCUMENT_TYPE` должен совпадать с объектом запуска: шаблон для сделок не запустить для лида.
- ID смарт-процессов, стадий и полей различаются между порталами — сверять перед загрузкой
  ([[antipattern-bizproc-hardcoded-portal-ids|Зашитые ID портала]]).
- Что происходит при загрузке, если полей из `DOCUMENT_FIELDS` нет на целевом портале, REST-документация
  не описывает. Курс: импорт создаёт недостающие поля
  ([урок 3858](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=3858)); на стенде
  коробки так и вышло ([[concept-bizproc-bpt-format]]). Существующие поля с тем же кодом импорт
  перезаписывает только в универсальных списках, в CRM — нет (код ядра, коробка).
- Загружать шаблон можно только в документ того же типа, что и при экспорте: курс называет это
  запретом.

## Связанное
- [[concept-bizproc-bpt-format|Формат .bpt]], [[pattern-bizproc-ai-assisted-generation|AI-генерация БП]],
  [[recipe-rest-oauth-app-setup|OAuth-приложение]], [[entity-robots-triggers|Роботы и триггеры]]

[← Глоссарий](_index-glossary.md)
