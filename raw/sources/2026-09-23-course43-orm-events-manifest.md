# Снимок-манифест: курс «Разработчик Bitrix Framework» (COURSE_ID=43), разделы ORM и События, 2026-09-23

| Поле | Значение |
|------|----------|
| Источник | <https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&INDEX=Y> — официальный учебный курс 1С-Битрикс на dev.1c-bitrix.ru |
| Издатель | 1С-Битрикс |
| Статус | эталонный источник: официальная документация (`CLAUDE.md` §9) |
| Снято | 2026-09-23, два раздела уровня Middle: **ORM** (26 уроков, в два захода) и **События** (2 урока) |
| Что внутри | только метаданные: адрес, место в оглавлении, дата изменения, объём и хэш текста |
| Чего нет | текста уроков — в репозиторий его не копируем. Содержание — в конспекте `wiki/sources/source-course43-orm-events.md` |
| Прежние снимки курса | `2026-06-19-bxfw-course43-namespaces.md`, `2026-06-20-bxfw-course43-modules.md` |

Зачем манифест: вики ссылается на конкретные уроки, а курс правят. Изменившийся хэш или дата =
урок перечитать и пересверить страницы, которые на него ссылаются (поиск по `LESSON_ID=<номер>`).
Способ расчёта хэша — тот же, что в `2026-09-22-course57-bizproc-manifest.md` (JSON-LD `articleBody`,
теги вычищены, `sha256[:16]` от текста).

## Уроки

| Название | ID | Где | Изменён | Знаков | sha256[:16] |
|---|---|---|---|---:|---|
| Концепция, описание сущности | [`4803`](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=4803) | ORM | 2025-11-18 | 11481 | `043b66cc85a0a494` |
| Операции с сущностями | [`2244`](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=2244) | ORM | 2025-11-18 | 17946 | `3d354f28ca01650f` |
| Класс объекта | [`11689`](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=11689) | ORM / Объекты | 2023-07-26 | 2478 | `ca16f212e14798c8` |
| Именованные методы | [`11691`](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=11691) | ORM / Объекты | 2023-07-26 | 2760 | `84026eef8c92bd43` |
| Приведение типов | [`11693`](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=11693) | ORM / Объекты | 2020-10-27 | 784 | `08bf9c53cb73b0df` |
| Чтение | [`11695`](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=11695) | ORM / Объекты | 2020-10-27 | 4491 | `92dfd7bc72ccabc9` |
| Запись | [`11697`](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=11697) | ORM / Объекты | 2021-05-21 | 2169 | `81fe693f0935aa61` |
| Проверки | [`11997`](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=11997) | ORM / Объекты | 2020-10-27 | 1373 | `feac804e607423db` |
| Состояние объекта | [`11999`](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=11999) | ORM / Объекты | 2022-08-01 | 718 | `64bdd008b47843da` |
| Создание и редактирование | [`11699`](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=11699) | ORM / Объекты | 2021-04-03 | 2206 | `f06a8fbdad8d94e3` |
| Удаление | [`11701`](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=11701) | ORM / Объекты | 2021-05-26 | 625 | `7b49bc1cb29796ba` |
| Класс коллекции | [`11745`](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=11745) | ORM / Коллекции | 2020-10-27 | 944 | `5caa5ceb9d5d1fb7` |
| Доступ к элементам | [`11747`](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=11747) | ORM / Коллекции | 2020-10-27 | 2608 | `c0a962b5bef9a6c0` |
| Групповые действия | [`11749`](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=11749) | ORM / Коллекции | 2022-05-11 | 3680 | `d68d995490d611c5` |
| getList | [`5753`](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=5753) | ORM / Выборка данных | 2025-11-20 | 10928 | `57d51c7b01a4465a` |
| Объект Query | [`5751`](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=5751) | ORM / Выборка данных | 2025-11-20 | 3087 | `5659164639a18a07` |
| Фильтр ORM | [`3030`](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=3030) | ORM | 2025-11-20 | 8485 | `2256ed588353df0c` |
| События в D7 | [`3113`](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=3113) | События | 2025-11-20 | 1240 | `a035179242b90cf4` |
| Как написать обработчик события | [`3395`](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=3395) | События / Использование событий | 2023-11-13 | 2744 | `d56b4b947f30a28d` |

## Уроки: второй заход (отношения, аннотации, пропущенное)

Первый список уроков, полученный с оглавления, оказался неполным — глава «Отношения» и
часть уроков об объектах в него не попали. Добраны 2026-09-23 тем же способом.

| Название | ID | Где | Изменён | Знаков | sha256[:16] |
|---|---|---|---|---:|---|
| 1:N | [`11737`](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=11737) | ORM / Отношения | 2022-05-11 | 8169 | `a79b17931dc70829` |
| 1:1 | [`11739`](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=11739) | ORM / Отношения | 2020-10-27 | 162 | `d3110893605e385e` |
| N:M | [`11741`](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=11741) | ORM / Отношения | 2020-10-27 | 11500 | `e9d5f8390011f406` |
| Отношения (у объекта) | [`11707`](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=11707) | ORM / Объекты | 2023-08-08 | 2141 | `51aeac6c107906d0` |
| Заполнение | [`11705`](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=11705) | ORM / Объекты | 2020-10-27 | 2356 | `d26e460a7093b015` |
| Восстановление | [`11703`](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=11703) | ORM / Объекты | 2020-10-27 | 1211 | `a523a19d487685c0` |
| ArrayAccess | [`11755`](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=11755) | ORM / Объекты | 2020-10-27 | 884 | `be4a0cacfdf93d8e` |
| Аннотации классов | [`11733`](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=11733) | ORM | 2025-09-19 | 2415 | `b9bed04480b334e7` |
| Обратная совместимость | [`11715`](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=11715) | ORM | 2022-07-27 | 1036 | `f171d6756b75258f` |

## Что бросилось в глаза при снятии

- Ядро разделов (концепция сущности, операции, `getList`, объект `Query`, фильтр ORM, события в D7)
  обновлялось в ноябре 2025 года; уроки про объекты и коллекции — 2020–2023 годов.
- Уроки об объектах описывают API, появившийся заметно позже первых уроков про массивы: в курсе
  соседствуют оба подхода, и это видно по датам.
- Каждый урок начинается строкой «Тему урока можно изучить в новом формате — в документации по
  Bitrix Framework»: 1С-Битрикс переносит содержание курса в новую документацию.
