# Журнал операций

Хронология операций над вики (ingest / query / lint / ручные правки). Новые записи — сверху.
Формат строки: `ДАТА — операция — что сделано (затронутые страницы)`.

---

## 2026-06

- **2026-06-19 — bootstrap** — Создан каркас БЗ по методу LLM Wiki Карпати: схема `CLAUDE.md`,
  `README.md`, `log.md`, `.gitignore`; структура каталогов `raw/`, `wiki/` (modules, development,
  cross-cutting, glossary, sources); 7 шаблонов в `wiki/_templates/`; 3 команды
  `.claude/commands/wiki/`; хабы `_index.md`; стартовый набор seed-страниц; каталог `index.md`.
  REST-страницы сверены с `apidocs.bitrix24.ru` через MCP.
