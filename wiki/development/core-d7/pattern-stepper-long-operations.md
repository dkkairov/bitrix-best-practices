---
title: "Длинная операция шагами: Stepper"
type: pattern
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / коробка в Docker, main 26.750.0: класс Main\\Update\\Stepper, методы bind/bindClass/execute/getHtml/getOuterParams/getTitle, константы CONTINUE_EXECUTION=true, FINISH_EXECUTION=false, THRESHOLD_TIME=20.0, DELAY_COEFFICIENT=0.5; текст — документация фреймворка (docs.1c-bitrix.ru, «Итератор»)"
tags: [stepper, миграции, фоновые-задачи, агенты, массовые-операции]
sources: []
related: ["[[pattern-agents-vs-cron]]", "[[recipe-migrations-as-code]]", "[[recipe-cli-script-bootstrap]]", "[[recipe-module-structure-and-install]]"]
aliases: []
updated: "2026-09-24"
---

# Длинная операция шагами: Stepper

**В чём суть:** пересчёт или миграция миллиона записей не влезает в один хит. `Stepper` —
штатный механизм ядра: операция делится на шаги, ядро запускает очередной шаг на хитах
пользователей, прогресс хранится между запусками, в админке видна полоса.

**Когда применять:** разовый пересчёт после обновления модуля, дозаполнение нового поля,
переиндексация. Постоянная периодическая работа — это агент или cron
([[pattern-agents-vs-cron]]); одноразовая массовая — `Stepper`.

## Как выглядит

```php
namespace Vendor\Project;

use Bitrix\Main\Update\Stepper;

final class FillBookIsbn extends Stepper
{
    protected static $moduleId = 'vendor.project';

    public function execute(array &$option)
    {
        $option['steps'] ??= 0;
        $option['count'] ??= BookTable::getCount();
        $lastId = $option['lastId'] ?? 0;

        $rows = BookTable::getList([
            'filter' => ['>ID' => $lastId],
            'order'  => ['ID' => 'ASC'],
            'limit'  => 50,
        ])->fetchAll();

        if (!$rows)
        {
            return self::FINISH_EXECUTION;
        }

        foreach ($rows as $row)
        {
            // ... полезная работа ...
            $option['lastId'] = $row['ID'];
            $option['steps']++;
        }

        return self::CONTINUE_EXECUTION;
    }
}
```

Запуск: `FillBookIsbn::bind($delay)` для подключённого модуля либо
`Stepper::bindClass(FillBookIsbn::class, 'vendor.project', $delay)` — например, из установщика
модуля ([[recipe-module-structure-and-install]]).

| Элемент | Значение (26.750.0) |
|---|---|
| `self::CONTINUE_EXECUTION` | `true` — вызвать ещё раз |
| `self::FINISH_EXECUTION` | `false` — закончили |
| `self::THRESHOLD_TIME` | `20.0` — ориентир бюджета шага в секундах |
| `self::DELAY_COEFFICIENT` | `0.5` |
| `$option['steps']`, `$option['count']` | прогресс: сделано / всего, из них ядро рисует полосу |

Ядро дёргает шаг на хитах, но **не чаще одного раза в секунду**. Прогресс в админке —
`Stepper::getHtml()`: для конкретного итератора, для всех итераторов модуля (`getHtml('vendor.project', 'Заголовок')`)
или для выбранных; заголовок переопределяется методом `getTitle()`.

## Правила

- **Шаг должен укладываться в секунды, а не минуты.** Ориентир ядра — `THRESHOLD_TIME` 20 с; берём
  порцию (50–500 записей) и смотрим по факту.
- **Состояние — только в `$option`.** Это единственное, что переживает шаг: курсор (`lastId`),
  счётчики, параметры. Глобальные переменные и статика не сохранятся.
- **Курсор по `ID`, а не `offset`.** С `LIMIT/OFFSET` записи, изменённые между шагами, потеряются
  или обработаются дважды.
- **Шаг обязан быть идемпотентным.** Хит может оборваться посередине: повтор не должен ломать
  данные.
- **Нет хитов — нет прогресса.** На портале без посетителей (ночь, тестовый стенд) итератор стоит;
  для гарантированного прохода — cron-скрипт ([[recipe-cli-script-bootstrap]]).
- **Параметры запуска** читаются через `getOuterParams()`; разовую задачу можно завести с
  `$deleteFile = true`, чтобы она снялась после завершения.

## Чем это не является

Это не очередь задач: порядок «по одному шагу на хит» не даёт ни приоритетов, ни параллелизма.
Нужна настоящая очередь — смотрим в сторону штатных очередей сообщений и внешних брокеров.

## Связанные страницы
- [[pattern-agents-vs-cron]] — выбор способа фонового запуска
- [[recipe-migrations-as-code]] — миграции структуры, рядом с которыми обычно и нужен `Stepper`
- [[recipe-module-structure-and-install]] — откуда вызывать `bindClass()`

[← Ядро D7](_index-core-d7.md)
