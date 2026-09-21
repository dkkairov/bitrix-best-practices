---
title: "Действие «PHP код» в шаблонах БП"
type: antipattern
module: bizproc
edition: box
status: verified
provenance: mixed
verified: "2026-09-21 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Модуль Бизнес-процессы / Действие: PHP код, Работа с окружением; без проверки на стенде"
tags: [bizproc, php-код, CodeActivity, активити, журнал, сопровождение]
sources: ["[[source-devbook-bizproc]]"]
related: ["[[entity-cbp-activity]]", "[[entity-bizproc-activity-description]]", "[[recipe-bizproc-custom-task-activity]]", "[[concept-bizproc-activity-catalog]]", "[[concept-bizproc-bpt-format]]"]
aliases: []
updated: "2026-09-21"
---

# Действие «PHP код» в шаблонах БП

**TL;DR:** «PHP код» — самый быстрый способ научить процесс нестандартному, и книга прямо советует
не брать его в релизы: ошибка в коде останавливает процесс, шаблон может править только
администратор, а подстановка значений в текст кода ломается на первой кавычке. Замена — своё
действие. Если без вставки никак — пять правил и шаблон ниже.

## Как выглядит
- В шаблоне стоит штатное действие «PHP код» — класс `CBPCodeActivity`, который выполняет
  произвольный PHP-код
  ([Действия](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Dejstvia.html#dejstvia)).
  В выгрузке шаблона это узел `CodeActivity` со свойством `ExecuteCode`
  ([[concept-bizproc-activity-catalog|каталог действий]]).
- Внутри — десятки строк логики, значения документа вставлены «Вставкой значения» прямо в текст
  кода, код опирается на глобальный `$USER` и ID сайта.

## Почему это плохо
Недостатки по книге
([Действие: PHP код](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/PHP_kod.html#dejstvie-php-kod)):
- **Ошибка в коде блокирует процесс.** Некорректный код может остановить его выполнение.
- **Шаблон может править только администратор.** Если в схеме есть это действие, изменять шаблон
  может лишь сотрудник с правом менять файлы портала. Владелец процесса больше не поправит его сам —
  каждая правка идёт через администратора.
- **Большие вставки со временем не сопровождаются.** Вывод команды: код живёт внутри шаблона, а не
  в репозитории — его не видно в git, не пройти ревью, не покрыть тестами.
- **Подстановка значений в код** ломает синтаксис на первой же кавычке — разбор ниже.
- **Глобальное окружение ненадёжно.** Код выполняется и при открытии страницы, и на cron: текущего
  пользователя может не быть или он окажется не тем, ID сайта может отсутствовать
  ([Правила](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/PHP_kod.html#pravila)).

## Ловушка автоподстановки
Перед выполнением значения подставляются в текст кода как есть — книга сравнивает это с
`str_replace`
([Окружение](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/PHP_kod.html#okruzenie)).
Кавычка в значении превращает код в синтаксическую ошибку:

```php
// текст действия: значение вставлено через «Вставку значения»
$title = "{{Название}}";

// что выполнит PHP, если сделка называется Поставка "Север"
$title = "Поставка "Север"";    // синтаксическая ошибка — процесс падает
```

Значение нужно читать из окружения процесса, а не вставлять в исходник
([Парсинг](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/PHP_kod.html#parsing),
[Использование парсера](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Okruzenie.html#ispol-zovanie-parsera)):

```php
$title = (string)$this->ParseValue('{' . '=Document:TITLE}');
```

- В выражении — технический код поля, а не отображаемое имя: `{{ID}}` → `{=Document:ID}`.
- Строку выражения **обязательно разрезать** конкатенацией: цельная `'{=Document:TITLE}'` тоже
  попадёт под автозамену и даст ту же ошибку.
- Другие безопасные пути: `getVariable()`, `getConstant()`, параметры — через `getRootActivity()`,
  поля документа — через `DocumentService` ([[entity-cbp-activity]],
  [Работа с окружением](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Okruzenie.html)).
- Сообщение для `WriteToTrackingService()` тоже проходит подстановку выражений
  ([Журналирование](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/PHP_kod.html#zurnalirovanie)).

## Почему так делают
По книге у действия два плюса: оно расширяет возможности бизнес-процессов и почти не требует
времени на разработку. Код набирается прямо в дизайнере — выкладывать файлы не нужно.

## Как исправить
Заменить вставку своим действием — штатной точкой расширения без правки ядра
([[concept-bizproc-engine]]):
1. **Логику — в класс** решения или модуля (`/local/php_interface/classes/…` —
   [[pattern-local-solution-structure]]). Так код отлаживается без БП — это первое правило книги.
2. **Своё действие:** каталог в `/local/activities/custom/`, паспорт —
   [[entity-bizproc-activity-description|.description.php]], класс на `BaseActivity`: настройки —
   `getPropertiesDialogMap()`, логика — `internalExecute()`, ошибки — `ErrorCollection`
   ([[entity-cbp-activity]]). Действие, которое ждёт человека, —
   [[recipe-bizproc-custom-task-activity]].
3. **Значения — через настройки действия**, а не подстановкой в код: в поле настройки значение
   вводят явно или выбирают «Вставкой значения»
   ([Действия → properties_dialog.php](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Dejstvia.html#properties-dialog-php)).
4. **Проверить шаблон:** `php tools/bpt/bpt.php analyze <файл.bpt>` — `CodeActivity` анализатор
   считает ошибкой ([[concept-bizproc-bpt-format]]).

В блоках «Условие» и «Цикл» тоже есть вариант «PHP код»
([Свои условия](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Svoi_uslovia.html#svoi-uslovia));
замена ему — своё условие ([[entity-cbp-activity-condition]]). Книга разбирает риски только для
действия; что у условия они те же, — вывод команды.

## Если без «PHP кода» не обойтись
Книга возражает против этого действия именно в релизах; для быстрой проверки идеи на тестовом
стенде оно допустимо (вывод команды). Если вставка всё же остаётся, соблюдать правила книги
([Правила](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/PHP_kod.html#pravila),
[Шаблон активити](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/PHP_kod.html#sablon-aktiviti)):
1. **Минимум кода** — в идеале одна строка вызова класса, который отлаживается отдельно.
2. **Весь код в `try/catch (\Throwable)`** — чтобы не было необъяснимых падений.
3. **Писать в журнал БП всё важное:** успех, ошибку, неожиданное поведение.
4. **Никаких выражений под автоподстановку** — только `ParseValue()` с разрезанной строкой.
5. **Никаких глобальных переменных:** вместо `$USER` — данные процесса, например
   `getTemplateUserId()` (по примеру книги — кто запустил процесс)
   ([Работа с окружением → Дополнительные значения](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Okruzenie.html#dopolnitel-nye-znacenia)).

```php
try {
    $title  = (string)$this->ParseValue('{' . '=Document:TITLE}');
    $result = \Vendor\Project\Bizproc\DealCheck::run($this->getDocumentId(), $title);
    $this->WriteToTrackingService('Проверка сделки: ' . $result, 0, \CBPTrackingType::Report);
} catch (\Throwable $e) {
    $this->WriteToTrackingService(
        sprintf('Ошибка: %s (%s:%d)', $e->getMessage(), $e->getFile(), $e->getLine()),
        0,
        \CBPTrackingType::FaultActivity
    );
}
```

- Сигнатура: `WriteToTrackingService($message = '', $modifiedBy = 0, $trackingType = -1)`;
  `$modifiedBy = 0` — запись от имени системы.
- В журнале видны типы `CBPTrackingType::Error`, `Report`, `Custom`, `FaultActivity`. Выглядят они
  одинаково, но разделять их по смыслу полезно для последующего разбора
  ([Типы сообщений](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/PHP_kod.html#tipy-soobsenij)).
- **Вывод команды:** `catch` без повторного выброса пускает процесс дальше, будто ничего не
  случилось. Если следующие шаги зависят от результата, запишите признак ошибки в переменную
  процесса (`setVariable()`) и проверьте его условием.

## Профилактика
- Пункт ревью шаблона: в том, что уходит в релиз, нет «PHP кода».
- `analyze` из [`tools/bpt`](../../../tools/bpt/README.md) помечает `CodeActivity` ошибкой, а
  сборщик из спецификации это действие не генерирует ([[concept-bizproc-activity-catalog]],
  [[concept-bizproc-bpt-format]]).
- Действие есть только в коробке ([[concept-bizproc-bpt-format]]).
- Нашли в легаси — сократить по правилам выше до вызова класса и завести задачу на замену своим
  действием.

## Связанное
- [[entity-bizproc-activity-description]] — паспорт своего действия
- [[entity-cbp-activity]] — классы действия и доступ к окружению процесса
- [[recipe-bizproc-custom-task-activity]] — своё действие с заданием целиком
- [[concept-bizproc-activity-catalog]] — `CodeActivity` в каталоге и запрет генерации
- [[concept-bizproc-bpt-format]] — проверка шаблона перед переносом

[← Бизнес-процессы](_index-bizproc.md)
