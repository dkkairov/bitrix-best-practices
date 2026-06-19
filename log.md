# Журнал операций

Хронология операций над вики (ingest / query / lint / ручные правки). Новые записи — сверху.
Формат строки: `ДАТА — операция — что сделано (затронутые страницы)`.

---

## 2026-06

- **2026-06-20 — lint + правки доков** — Полная перепроверка после рефакторинга нумерации: 0 битых
  ссылок (включая относительные к хабам), 0 орфанов, `id` уникальны и согласованы (имя↔id,
  префикс↔папка↔тип), `edition`/`verified` корректны, артефактов кодировки нет. Правки по итогам:
  `README.md` (добавлены система нумерации и подпапки), `wiki/overview.md` (проставлен `verified`),
  `CLAUDE.md` §8 (lint упоминает ID-проверки).

- **2026-06-20 — refactor: нумерация** — Введена сквозная адресация решений `<РАЗДЕЛ>-<ТИП><NN>`
  (см. `CLAUDE.md` §4.5): коды разделов M01–M14 / D01–D07 / X01–X03 / G / S, под-префиксы типов
  CL/RC/PT/AP/CN/EN/SS. Все 17 контент-страниц переименованы в `<id>-<слаг>.md` и разложены по
  подпапкам `guides/patterns/antipatterns/reference/` (через `git mv` — история сохранена); добавлены
  поля `id` и `aliases` (прежний слаг). Перелинкованы тела и `related` (формат `[[id-слаг|подпись]]`),
  обновлены 14 хабов `_index.md`, `index.md` (новые разделы «По ID» и «Реестр разделов»), 7 шаблонов и
  команды `ingest`/`lint`/`query`. Аудит: 0 битых ссылок, `id` уникальны, имена файлов ↔ `id` совпадают.

- **2026-06-19 — ingest** — Урок «Пространства имён» (курс 43, lesson 3524) → создана
  `wiki/development/core-d7/code-namespaces-and-autoloading.md` и конспект
  `wiki/sources/src-bxfw-course43-namespaces.md`; источник в `raw/sources/`. Обновлены хаб core-d7
  и `index.md`. edition=box, provenance=documented/mixed.
- **2026-06-19 — triage источников** — Зарегистрированы 4 источника от пользователя в
  `wiki/sources/sources-backlog.md` с классификацией и приоритетами: курс 43 (P1), devbook (P1),
  awesome-bitrix (каталог, P2), api_help (каталог/legacy, P3). Залинкован бэклог в хабе и `index.md`.
- **2026-06-19 — bootstrap** — Создан каркас БЗ по методу LLM Wiki Карпати: схема `CLAUDE.md`,
  `README.md`, `log.md`, `.gitignore`; структура каталогов `raw/`, `wiki/` (modules, development,
  cross-cutting, glossary, sources); 7 шаблонов в `wiki/_templates/`; 3 команды
  `.claude/commands/wiki/`; хабы `_index.md`; стартовый набор seed-страниц; каталог `index.md`.
  REST-страницы сверены с `apidocs.bitrix24.ru` через MCP.
