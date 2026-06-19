# Журнал операций

Хронология операций над вики (ingest / query / lint / ручные правки). Новые записи — сверху.
Формат строки: `ДАТА — операция — что сделано (затронутые страницы)`.

---

## 2026-06

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
