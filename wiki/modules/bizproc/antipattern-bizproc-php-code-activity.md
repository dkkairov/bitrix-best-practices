---
title: "Действие «PHP код» в шаблонах БП"
type: antipattern
module: bizproc
edition: box
status: verified
provenance: mixed
verified: "2026-09-22 / «Книга разработчика Bitrix24» (bx24devbook, снимок 2026-09-21): Модуль Бизнес-процессы / Действие: PHP код, Работа с окружением; курс 57 (уроки 3806, 13378, 23038, 3789, 5368, 8411); стенд 2026-09-22 (коробка, bizproc 26.1075.0): что фатальная ошибка PHP и исключение делают с процессом"
tags: [bizproc, php-код, CodeActivity, активити, журнал, сопровождение]
sources: ["[[source-devbook-bizproc]]", "[[source-course57-developer]]", "[[source-course57-actions-notify-other]]", "[[source-course57-examples]]"]
related: ["[[entity-cbp-activity]]", "[[entity-bizproc-activity-description]]", "[[recipe-bizproc-custom-task-activity]]", "[[concept-bizproc-activity-catalog]]", "[[concept-bizproc-bpt-format]]", "[[recipe-bizproc-custom-activity-baseactivity]]", "[[source-course57-examples]]"]
aliases: []
updated: "2026-09-22"
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
- **Ошибка в коде блокирует процесс.** Некорректный код может остановить его выполнение. Стенд
  уточняет (2026-09-22): фатальную ошибку PHP (`Error`, в том числе ошибку разбора из `eval`) движок
  не перехватывает — хит падает, процесс остаётся «Выполняется», в журнале процесса записи нет.
  `Exception` движок ловит: шаг закрывается с ошибкой, процесс идёт дальше ([[entity-cbp-activity]]).
- **Шаблон может править только администратор.** Если в схеме есть это действие, изменять шаблон
  может лишь сотрудник с правом менять файлы портала. Владелец процесса больше не поправит его сам —
  каждая правка идёт через администратора.
- **Большие вставки со временем не сопровождаются.** Вывод команды: код живёт внутри шаблона, а не
  в репозитории — его не видно в git, не пройти ревью, не покрыть тестами.
- **Подстановка значений в код** ломает синтаксис на первой же кавычке — разбор ниже.
- **Глобальное окружение ненадёжно.** Код выполняется и при открытии страницы, и на cron: текущего
  пользователя может не быть или он окажется не тем, ID сайта может отсутствовать
  ([Правила](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/PHP_kod.html#pravila)).
- **Курс добавляет** ([урок 13378](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=13378),
  [урок 23038](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=23038)):
  - ошибка разбора PHP после подстановки значений роняет хит — процесс зависает;
  - значение `{=Variable:…}` в SQL без фильтрации — инъекция;
  - права нельзя повышать через `$USER->Authorize()`;
  - у роботов и триггеров нет пользователя-инициатора;
  - модули не подключены — подключать самим; типы приводить явно.
- **Только коробка и только администратор.** Задавать код может только администратор
  ([урок 3806](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=3806)); в облаке
  действия нет — своя логика там только через свои действия приложения по REST.

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
   ([[entity-cbp-activity]]; пошагово — [[recipe-bizproc-custom-activity-baseactivity]]). Действие,
   которое ждёт человека, —
   [[recipe-bizproc-custom-task-activity]].
3. **Значения — через настройки действия**, а не подстановкой в код: в поле настройки значение
   вводят явно или выбирают «Вставкой значения»
   ([Действия → properties_dialog.php](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Dejstvia.html#properties-dialog-php)).
4. **Проверить шаблон:** `php tools/bpt/bpt.php analyze <файл.bpt>` — `CodeActivity` анализатор
   считает ошибкой ([[concept-bizproc-bpt-format]]).

В блоках «Условие» и «Цикл» тоже есть вариант «PHP код»
([Свои условия](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Svoi_uslovia.html#svoi-uslovia));
его задаёт только администратор, код должен вернуть `true` или `false`
([урок 3789](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=3789)). Замена ему —
своё условие ([[entity-cbp-activity-condition]]). Книга разбирает риски только для действия; что у
условия они те же, — вывод команды.

## Примеры курса — не копировать
Курс сам советует читать значения через `$this->GetVariable()`, а не подставлять `{=…}` в код, но его
примеры делают наоборот или ставят вставку там, где уже есть штатное действие:
- урок 3806, примеры 3 и 4, уроки 2172 и 2905 — `{=…}` прямо в тексте кода;
- шаблон «последовательного создания задач» из урока 7125 (`bp-31.bpt`) читает переменные правильно,
  через `GetVariable()`, но сама вставка уже лишняя: «PHP код» в цикле по одному вынимает сотрудников
  из множественной переменной, а сейчас это делает штатный «Итератор»
  (`ForEachActivity`: перебирает значения множественной переменной, текущее — результат
  «Значение», код ядра); он есть в примере курса `bp-7.bpt`. В том же шаблоне крайний срок задачи
  берётся из необъявленной переменной — `bpt.php analyze` это находит ([[source-course57-examples]]);
- [урок 5368](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=5368) — подстановки без
  кавычек: код в показанном виде не выполнится и открыт для инъекции;
- [урок 8411](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=8411) — задача с
  приоритетом, ID исполнителя и постановщика зашиты числами. Штатная замена — «Поставить задачу» с
  признаком важности и «Остановить процесс на время выполнения задачи»; при переменной важности — два
  действия в ветках «Условия».

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
  случилось. Повторный выброс `Exception` процесс тоже не остановит: движок закроет шаг с ошибкой и
  запустит следующий (стенд 2026-09-22). Если следующие шаги зависят от результата, запишите признак
  ошибки в переменную процесса (`setVariable()`), проверьте его условием и при необходимости
  поставьте «Прерывание процесса».

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
- [[recipe-bizproc-custom-activity-baseactivity]] — своё действие на `BaseActivity` целиком
- [[recipe-bizproc-custom-task-activity]] — своё действие с заданием целиком
- [[concept-bizproc-activity-catalog]] — `CodeActivity` в каталоге и запрет генерации
- [[concept-bizproc-bpt-format]] — проверка шаблона перед переносом

[← Бизнес-процессы](_index-bizproc.md)
