---
title: "Конспект: «Книга разработчика», модуль задач — провайдеры и команды V2"
type: source-summary
module: tasks-projects
edition: box
status: verified
provenance: documented
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Модуль Задачи — все 12 страниц; без проверки на стенде"
tags: [задачи, tasks, v2, cqrs, команды, провайдеры]
sources: []
related: ["[[concept-tasks-api-v2]]", "[[recipe-tasks-v2-commands]]", "[[pattern-tasks-effectiveness-from-db]]", "[[checklist-tasks-regulations]]", "[[concept-crm-universal-api]]", "[[entity-main-result]]"]
aliases: []
updated: "2026-09-22"
---

# Конспект: модуль задач

## Об источнике
| Поле | Значение |
|------|----------|
| Тип | «Книга разработчика Bitrix24» — эталонный источник (`CLAUDE.md` §9); фокус книги — коробка |
| Раздел | «Модуль Задачи», 12 страниц |
| Страницы | [О модуле](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/O_module.html) · [Поиск](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Poisk.html) · [Основные команды](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Osnovnye_komandy.html) · [Чек-листы](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Cek_listy.html) · [Участники задач](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Ucastniki_zadac.html) · [Работа с файлами](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Rabota_s_fajlami.html) · [Канбан](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Kanban.html) · [Отображение и поведение](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Otobrazenie_i_povedenie.html) · [Гант](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Gant.html) · [Связи и зависимости](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Svazi_i_zavisimosti.html) · [Другие](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Drugie.html) · [Чат](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Cat.html) |
| Версия продукта | модуль `tasks` 26.0.0 — последняя на момент написания; многие классы есть с 25-й, чат задачи — с 25.500 |
| Автор | Андрей Николаев |
| Дата | на страницах дат нет; прочитано по снимку 2026-09-21 |
| Где лежит | снимок-манифест (адреса, якоря, хэши страниц, без текста): [`raw/sources/2026-09-21-bx24devbook-manifest.md`](../../raw/sources/2026-09-21-bx24devbook-manifest.md) |

**Как получен.** Ингест с сайта книги 2026-09-21: все 12 страниц раздела прочитаны целиком по
снимку. Текст книги в репозиторий не копировался — правки эталона отслеживаются по хэшам страниц в
манифесте. До этого ингеста по модулю задач в вики не было ни конспекта, ни страниц о его API.

## TL;DR
Глава — справочник по PHP API модуля `tasks` 26.0.0 для коробки. Она объявляет устаревшим
`CTasks::getList`, даёт три провайдера чтения (совместимый фасад, текущий низкоуровневый,
экспериментальный V2) и 47 команд V2, построенных по CQRS: сущность и конфиг → команда → `run()` →
`Result`. Сильная сторона — полные сигнатуры параметров и конфигов; слабая — примеры с ошибками,
внутренние противоречия и пробелы: нет смены статуса, шаблонов, событий, модели прав и REST.

## Карта страниц книги
| Страница | О чём | Куда встроено |
|----------|-------|---------------|
| [О модуле](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/O_module.html) | версия 26.0.0; путь от статических методов и ORM к CQRS; слои; плюсы и цена подхода; явное подключение модуля | [[concept-tasks-api-v2]] |
| [Поиск](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Poisk.html) | замены `CTasks::getList`: `Provider\TaskProvider` (`$arParams`, `CHECK_PERMISSIONS`), `TaskList` + `TaskQuery`, V2 `TaskProvider` (`TaskParams`, `TaskListParams`) | [[concept-tasks-api-v2]] |
| [Основные команды](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Osnovnye_komandy.html) | модель `Entity\Task`; создание, правка, копия, срок, удаление и их конфиги; `Result` и примеры ошибок | [[concept-tasks-api-v2]], [[recipe-tasks-v2-commands]] (шаги 1, 2, 8) |
| [Чек-листы](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Cek_listy.html) | структура пункта; сохранение всей структуры; отметка и возобновление пунктов; свернуть и развернуть | [[recipe-tasks-v2-commands]] (шаг 4) |
| [Участники задач](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Ucastniki_zadac.html) | роли; `Audit` — по одному пользователю, `Stakeholder` — списками и с `UpdateConfig` | [[recipe-tasks-v2-commands]] (шаг 3) |
| [Работа с файлами](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Rabota_s_fajlami.html) | прикрепить и открепить объекты Диска | [[recipe-tasks-v2-commands]] (шаг 5) |
| [Канбан](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Kanban.html) | связь задачи со стадией (`relationId`); перемещение, привязка, отвязка, очистка; коды ошибок | [[recipe-tasks-v2-commands]] (шаг 7) |
| [Отображение и поведение](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Otobrazenie_i_povedenie.html) | избранное, закрепление, отключение уведомлений, приоритет, просмотр | [[recipe-tasks-v2-commands]] (шаг 9) |
| [Гант](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Gant.html) | `LinkType`; создать, изменить, удалить зависимость | [[recipe-tasks-v2-commands]] (шаг 6) |
| [Связи и зависимости](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Svazi_i_zavisimosti.html) | родительская задача, связанные задачи, подзадачи по списку сотрудников | [[recipe-tasks-v2-commands]] (шаг 6) |
| [Другие](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Drugie.html) | изменение напоминания; `Reminder` и его перечисления | [[recipe-tasks-v2-commands]] (шаг 9) |
| [Чат](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Cat.html) | с 25.500 обсуждение перенесено в чат задачи; отправка сообщения | [[recipe-tasks-v2-commands]] (шаг 9) |

## Ключевые тезисы
- **CQRS.** Основной механизм изменения состояния — команды `Bitrix\Tasks\V2\Public\Command\…`, и
  книга называет их главным публичным API. Слой представления (REST-контроллеры, ваш код) собирает
  команду и запускает её, обработчики работают с `Internal\Service`, `Internal\Repository`,
  `Internal\Entity`. Обещанные плюсы — стабильный интерфейс, строгая валидация, одна точка входа,
  единая обработка ошибок; цена — много мелких команд, каждую конфигурируют вручную.
- **Модуль подключать явно** (`Loader::includeModule('tasks')`); примеры используют
  `Loader::requireModule('tasks')`.
- **Чтение.** `CTasks::getList` устарел. `Provider\TaskProvider` — совместимый фасад над `TaskList`
  (массивы, `CDBResult`, даже количество — полем `CNT`); `TaskList` + `TaskQuery` — текущий провайдер;
  V2 `TaskProvider` экспериментальный и возвращает доменные сущности.
- **Права.** `CHECK_PERMISSIONS = 'N'` в `$arParams` **или** в `$arFilter` выключает проверку прав;
  у `TaskQuery` есть `skipAccessCheck()`; V2 `TaskParams` по умолчанию грузит все связанные данные и
  включает все проверки доступа, `TaskListParams::skipAccessCheck` по умолчанию `false`.
- **Команда = сущность + конфиг.** Сущности (`Task`, `User`) и конфиги (`AddConfig`, `UpdateConfig`,
  `CopyConfig`, `DeleteConfig`) лежат в `V2\Internal`. Конфиги — набор переключателей: пропуск
  бизнес-процессов, уведомлений, комментариев, push, пересчёта; флаг создания из агента; проверка
  прав на файлы (по умолчанию выключена).
- **Правка** — только изменяемые поля; `UpdateConfig` собирают конструктором или
  `createFromArray()` с ключами в верхнем регистре. **Копия** переносит связанное только по флагам
  `with*` (все `false`) и умеет копировать в существующую задачу (`targetTaskId`).
- **Результат** — `V2\Internal\Result\Result`, наследник `Main\Result` с `getId()`, `getObject()`,
  `getCollection()`, `getDataByKey()`.
- **Участники:** `Set…` заменяет список целиком; для наблюдателей есть `Add…` и `Delete…`, для
  соисполнителей — нет; смена постановщика требует текущего ответственного; в `Watch`/`Unwatch`
  `userId` — кто действует, `auditorId` — кого добавляют.
- **Чек-лист** сохраняется целиком, `isComplete` — строка `'Y'`/`'N'`, автор изменения — `updatedBy`.
- **Файлы** сначала загружаются в Диск, затем прикрепляются строковыми ID `n<ID>`.
- **Канбан** работает по `relationId` и без `userId`. **Гант:** `taskId` — зависимая задача,
  `dependentId` — предшествующая, тип связи — `LinkType`.
- **Напоминания** — только изменение (`UpdateReminderCommand`); **чат** — `SendMessageCommand` с
  объектом `Integration\Im\Entity\Message`.

## Что встроено в вики
- [[concept-tasks-api-v2]] — три поколения API, устройство V2, модель задачи, результат, провайдеры
  чтения и права, выбор API, аналогия с Universal API CRM.
- [[recipe-tasks-v2-commands]] — карта подпространств команд, типовые операции (шаги 0–9) и сводка
  ловушек; `draft` до прогона примеров на стенде.

## Противоречия

### С установленной версией (стенд, tasks 26.300.100, 2026-09-22)
- **Структура пункта чек-листа устарела.** Книга: `text` и `isComplete` = `'Y'`/`'N'`. На стенде
  пункт описывается полями `nodeId`, `title`, `isComplete` (булево) — вариант из книги сохраняется
  «успешно», но в базу не попадает. Разбор — [[recipe-tasks-v2-commands]].
- **Копия задачи** из `new Task(id: …)` не создаётся: нужен полный объект из провайдера.
- **Приоритет `Low`** в модели есть, но не сохраняется — задача остаётся со средним.

### Внутри книги
- `TaskParams` объявлен `readonly`, а короткий пример присваивает свойства после `mapFromArray()`
  ([Поиск](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Poisk.html#taskprovider-get)).
- `flow`: в модели задачи описан как бизнес-процесс, в параметрах провайдера — как поток
  ([Объект задачи](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Osnovnye_komandy.html#ob-ekt-zadaci),
  [Поиск](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Poisk.html#taskprovider-get)).
- Приоритет: в модели значения `low`/`medium`/`high`, а команды — только `SetHigh…` и `SetAverage…`
  ([Отображение и поведение](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Otobrazenie_i_povedenie.html#setaveragetaskprioritycommand)).
- Файлы: для прикрепления ID — строки вида `n10`, в примере открепления — числа
  ([Работа с файлами](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Rabota_s_fajlami.html#detachfilescommand)).
- Копия описана как копия задачи со всеми параметрами, но все флаги `with*` по умолчанию `false`;
  в примере исходная задача собрана вручную
  ([Копирование](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Osnovnye_komandy.html#kopirovanie-zadaci)).
- `ClearStageCommand` описан как удаление всех задач из стадии — удаление задач это или отвязка, не
  ясно ([Канбан](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Kanban.html#clearstagecommand)).
- Поля сущности читаются то свойством `->id`, то геттером `->getId()`
  ([Архитектура](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/O_module.html#arhitektura-modula),
  [Создание задачи](https://bx24devbook.website.yandexcloud.net/Modul_Zadaci/Osnovnye_komandy.html#sozdanie-zadaci)).
- В семи примерах нет `$command->run()`, хотя `$result` используется: `AddFavoriteCommand`,
  `PinTaskCommand`, `SetHighTaskPriorityCommand`, `ViewCommand`, `SetParentRelationCommand`,
  `DeleteRelatedTaskCommand`, `UpdateReminderCommand`.
- Мелочи: подпись к примеру чата обещает файлы, а в коде их нет; в примере поиска текст говорит о
  мае, а даты в коде — июньские; в перечне разделов на странице «О модуле» нет канбана; таблица
  аргументов `UpdateTaskCommand` повреждена (`taskBeforeUpdate`, `taskChangesContext`).

### С текущими страницами вики
Прямых нет. [[pattern-tasks-effectiveness-from-db]] опирается на `Bitrix\Tasks\Internals\Effective` и
таблицы БД — книга эту тему не покрывает; старое пространство `Internals` не путать с новым
`V2\Internal`.

## Открытые вопросы
- В главе нет: смены статуса, шаблонов и повторяющихся задач, учёта времени, событий модуля, модели
  прав, REST.
- Что делает `useConsistency`; как найти `relationId` существующей привязки к стадии; от чьего имени
  проверяются права в командах без `userId` (канбан, изменение и удаление связей Ганта).
- Уходит ли задача в корзину при `DeleteTaskCommand`.
- Как создать или удалить напоминание (описано только изменение); как приложить файлы к сообщению
  чата.
- Принимают ли команды `userId: 0`; как `deadlineTs` учитывает часовой пояс (`skipTimeZoneFields`).

[← Конспекты источников](_index-sources.md)
