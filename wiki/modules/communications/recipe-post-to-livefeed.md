---
title: "Пост в живую ленту из PHP: CBlogPost::Add + Notify"
type: recipe
module: communications
edition: box
status: verified
provenance: empirical
verified: "2026-07-13 / коробка: модули blog + socialnetwork, публикация из Operation\\Action"
tags: [живая-лента, blog, socialnetwork, уведомления, публикация]
sources: []
related: ["[[pattern-crm-action-vs-event]]", "[[concept-change-invasiveness-hierarchy]]", "[[recipe-module-structure-and-install]]"]
aliases: ["bitrix24-post-to-livefeed"]
updated: "2026-09-18"
---

# Пост в живую ленту из PHP

**Результат:** из кода модуля (или действия операции CRM) публикуется пост, видимый всем
авторизованным сотрудникам в живой ленте портала.

## Предусловия
- Коробка: подключены модули `blog` **и** `socialnetwork`.
- Известен `AUTHOR_ID` — от чьего имени публикуем. Для «системных» постов завести служебного
  пользователя.

## Проблема

D7-API для живой ленты нет. `CBlogPost::Add()` сам по себе создаёт пост в блоге автора, но **в
ленте он не появляется**: живая лента — отдельный реестр модуля `socialnetwork`, и запись в нём
создаёт `CBlogPost::Notify()`.

## Шаги

```php
use Bitrix\Main\Loader;

Loader::includeModule('blog');
Loader::includeModule('socialnetwork');

$blog = \CBlog::GetByOwnerID($authorId);          // блог автора поста
if (!$blog) {
    // блог заводится при первой публикации через интерфейс — обработать и залогировать
    return;
}

$postFields = [
    'TITLE'            => $title,
    'DETAIL_TEXT'      => $message,
    'DETAIL_TEXT_TYPE' => 'text',
    'DATE_PUBLISH'     => date('d.m.Y H:i:s'),
    'PUBLISH_STATUS'   => 'P',
    'PATH'             => '/company/personal/user/' . $authorId . '/blog/#post_id#/',
    'SOCNET_RIGHTS'    => ['UA'],                 // видят все авторизованные
    'TAGS'             => 'Благодарности',        // тег для фильтрации в ленте
    '=DATE_CREATE'     => 'now()',
    'AUTHOR_ID'        => $authorId,
    'BLOG_ID'          => $blog['ID'],
];

global $DB;
$DB->StartTransaction();
$postId = \CBlogPost::Add($postFields);
$postFields['ID'] = $postId;

if ($postId && \CBlogPost::Notify($postFields, [], ['bSoNet' => true])) {
    $DB->Commit();
} else {
    $DB->Rollback();
}
```

`Notify(..., ['bSoNet' => true])` — ключевой шаг: он регистрирует пост в живой ленте. Без него пост
существует, но невидим.

## Проверка результата
- Пост виден в живой ленте под нужным автором и с нужным тегом.
- Пост открывается по ссылке из `PATH`.
- При искусственной ошибке в `Notify` пост-призрак не остаётся (транзакция откатилась).

## Подводные камни
- **У автора может не быть блога.** `CBlog::GetByOwnerID()` вернёт `false` — блог создаётся при
  первой публикации через интерфейс. Обрабатывать явно и логировать, а не падать.
- **Транзакция обязательна:** без неё при сбое `Notify` останется пост вне ленты, который никто не
  увидит и никто не удалит.
- **Fail-open.** Публикация — побочный эффект, а не суть операции: её сбой не должен ломать
  основное действие (элемент к этому моменту уже сохранён). Логируем и идём дальше.
- Права видимости задаются `SOCNET_RIGHTS`; `['UA']` — все авторизованные. Для узкой аудитории
  указывать конкретные коды (группы, отделы) — иначе внутреннее уведомление уедет всему порталу.

## Альтернативы
- REST `log.blogpost.add` — путь для облака и внешних приложений; в коробочном модуле это лишний
  HTTP-слой.
- `CSocNetLog::Add` напрямую — теоретически возможно, но связка выше проверена на живом портале.

## Связанное
- [[pattern-crm-action-vs-event]] — откуда обычно вызывается такая публикация
- [[concept-change-invasiveness-hierarchy]] — используем легитимное старое C-API, ядро не трогаем

[← Коммуникации](_index-communications.md)
