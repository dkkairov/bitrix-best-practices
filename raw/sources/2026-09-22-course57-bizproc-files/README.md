# Курс 57 «Бизнес-процессы» — файлы-примеры уроков

| Поле | Значение |
|------|----------|
| Источник | официальный курс 1С-Битрикс «Бизнес-процессы» (`CLAUDE.md` §9), вложения уроков |
| Издатель | 1С-Битрикс, dev.1c-bitrix.ru |
| Скачано | 2026-09-22, с разрешения пользователя; 11 из 13 файлов |
| Статус | неизменяемый источник (`CLAUDE.md` §2): файлы как на сайте, без правок |
| Снимок уроков | [`2026-09-22-course57-bizproc-manifest.md`](../2026-09-22-course57-bizproc-manifest.md) |

- **Как скачаны.** Пользователь скачал файлы вручную: сайт курса временно не принимал соединения
  с адреса, с которого шёл ингест. Размеры совпадают с ответом сервера на HEAD-запросы того же дня.
- **Не скачались** архивы уроков 2903 и 7771 — в папке их нет.
- **Проверено 2026-09-22:** все шаблоны в UTF-8, обратимы (`bpt.php check`) и проходят проверку
  импорта на стенде (коробка, bizproc 26.1075.0, `validateTemplate` без записи в базу).
- **Привязки к порталу автора курса** — `user_1`, `user_59`, `user_63`, `user_67`, поля `UF_CRM_*`,
  стадии `C1:*`, в `bp_vacation.bpt` — ссылка на локальный адрес; в текстах — условное имя
  «Иван Иванов». Секретов и персданных нет (проверено 2026-09-22).
- **Git.** `*.bpt` в `.gitignore` закрыты как шаблоны клиентов; эти — публичные примеры курса,
  добавлены через `git add -f`.
- **Разбор:** `php tools/bpt/bpt.php analyze|compact|render <файл>`. Собрать их из спецификации
  нельзя: в примерах 20 типов действий вне каталога `tools/bpt`. Выводы — в конспекте
  [`source-course57-examples`](../../../wiki/sources/source-course57-examples.md).

| Файл | Урок | Изменён на сайте | Байт | sha256[:16] | Корень | Узлов |
|------|------|------------------|------|-------------|--------|-------|
| [`bp-7.bpt`](https://dev.1c-bitrix.ru/images/admin_expert/bizproc/activities/bp-7.bpt) | [23568 «Получить информацию о товарной позиции»](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=23568) | 2022-05-16 | 7302 | `9567df20115f339c` | последовательный | 7 |
| [`bp-disk-1.bpt`](https://dev.1c-bitrix.ru/images/admin_expert/bizproc/activities/bp-disk-1.bpt) | [7731 «Копировать/Переместить в Диске»](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=7731) | 2022-05-25 | 1019 | `72ddace4f1ab424b` | последовательный | 3 |
| [`bp-168.bpt`](https://dev.1c-bitrix.ru/images/admin_expert/bizproc/activities/example/bp-168.bpt) | [7993 «Пример изменения процесса Выдача наличных»](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=7993) | 2016-10-07 | 5772 | `a39972cbb469a2e1` | последовательный | 61 |
| [`bp-196.bpt`](https://dev.1c-bitrix.ru/images/admin_expert/bizproc/activities/example/bp-196.bpt) | [8387 «Пример изменения процесса Заявление на отпуск»](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=8387) | 2016-10-20 | 6041 | `6f3e9b3b30228a8c` | последовательный | 61 |
| [`bp-200.bpt`](https://dev.1c-bitrix.ru/images/admin_expert/bizproc/activities/example/bp-200.bpt) | [8391 «Пример изменения процесса Исходящие документы»](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=8391) | 2016-10-25 | 3917 | `da935551de211ff6` | последовательный | 23 |
| [`calculate.bpt`](https://dev.1c-bitrix.ru/images/admin_expert/bizproc/example/calculate/calculate.bpt) | [5359 «Вычисляем числовые значения с записью в поля документа»](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=5359) | 2013-06-13 | 1376 | `c63500976cf660e1` | последовательный | 3 |
| [`25_03_2015.bpt`](https://dev.1c-bitrix.ru/images/admin_expert/bizproc/example/crm_bp/25_03_2015.bpt) | [7107 «Пример бизнес-процесса для обслуживания заявок клиентов»](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=7107) | 2015-04-03 | 10615 | `8b550d1c08727810` | последовательный | 113 |
| [`crm_bp.bpt`](https://dev.1c-bitrix.ru/images/admin_expert/bizproc/example/crm_bp/crm_bp.bpt) | [3871 «Пример бизнес-процесса со статусами по созданию счета для клиента в CRM»](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&CHAPTER_ID=03871), [12293 «Техническое задание»](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=12293) | 2023-04-06 | 15555 | `f1ae1d2c48f88c01` | со статусами | 104 |
| [`bp-31.bpt`](https://dev.1c-bitrix.ru/images/admin_expert/bizproc/example/multitask/bp-31.bpt) | [7125 «Список шаблонов для импорта»](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=7125) | 2015-06-10 | 2276 | `2a254b6aa73d9296` | последовательный | 10 |
| [`statuses_simple.bpt`](https://dev.1c-bitrix.ru/images/admin_expert/bizproc/example/statuses_simple/statuses_simple.bpt) | [3861 «Пример: организация обработки и доработки документа»](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=3861) | 2013-05-08 | 2190 | `644740acec337951` | со статусами | 16 |
| [`bp_vacation.bpt`](https://dev.1c-bitrix.ru/images/admin_expert/bizproc/example/vacation/bp_vacation.bpt) | [5518 «Пример бизнес-процесса для подачи заявки на отпуск»](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=5518) | 2013-12-11 | 4558 | `112b66751d46829e` | последовательный | 45 |
| [`write2logactivity.zip`](https://dev.1c-bitrix.ru/images/dev_full/biz_proc/write2logactivity.zip) | [2903 «Пример создания действия Запись в лог»](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=2903) | — | — | не скачан | — | — |
| [`task.zip`](https://dev.1c-bitrix.ru/images/portal_admin/bizproc/examples/task.zip) | [7771 «Пример использования REST в процессах»](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=7771) | — | — | не скачан | — | — |

Все шаблоны, кроме `bp-7.bpt`, `bp-disk-1.bpt`, `calculate.bpt` и `statuses_simple.bpt`, собраны
также в уроке [7125 «Список шаблонов для импорта»](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=7125).
