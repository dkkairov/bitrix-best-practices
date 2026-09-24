---
title: "XSS, CSRF, SSRF, инъекции: чем закрывает ядро"
type: concept
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / коробка в Docker, main 26.750.0: прогон — htmlspecialcharsEx не экранирует «&», htmlspecialcharsbx и HtmlFilter::encode экранируют; уровни CBXSanitizer (CUSTOM 0, HIGH 1, MIDDLE 2, LOW 3), высокий уровень вырезал <script> и тег <a> целиком; метод ApplyDoubleEncode в ядре есть, хотя документация его не называет; функции bitrix_sessid* и check_bitrix_sessid на месте; текст — документация фреймворка (docs.1c-bitrix.ru, разделы «XSS», «CSRF и SSRF», «Санитайзер»)"
tags: [безопасность, xss, csrf, ssrf, инъекции, экранирование, санитайзер]
sources: []
related: ["[[concept-coding-standards]]", "[[concept-d7-sql-layer]]", "[[recipe-engine-controller-action]]", "[[recipe-http-client]]", "[[checklist-box-security]]"]
aliases: []
updated: "2026-09-24"
---

# XSS, CSRF, SSRF, инъекции: чем закрывает ядро

**TL;DR:** четыре классические дыры в коробке закрываются штатно — экранированием вывода,
санитайзером для пользовательского HTML, токеном сессии в формах и плейсхолдерами в SQL. Почти все
инциденты на проектах — это не «дыра в Битриксе», а место, где разработчик обошёл штатный механизм.

## XSS: экранируем вывод

| Инструмент | Когда |
|---|---|
| `htmlspecialcharsbx($v)` | **основной**: любое значение в HTML |
| `\Bitrix\Main\Text\HtmlFilter::encode($v)` | то же, но с поддержкой Unicode |
| `\CUtil::JSEscape($v)` | строка внутри JS |
| `\Bitrix\Main\Web\Json::encode($v)` | данные в JSON (экранирует `<` как `<`) |
| `CBXSanitizer` | пользователь присылает **HTML**, который надо показать |

> **`htmlspecialcharsEx` не защищает.** Проверено на стенде: на строке `Привет & "кавычки"` она
> оставляет амперсанд как есть (`Привет &`), а `htmlspecialcharsbx` и `HtmlFilter::encode` дают
> `&amp;`. Работает по чёрному списку — в новом коде не используем.

Правила, которые дают 90 % результата:

- экранируем **в точке вывода**, а не при записи в базу: одно и то же значение попадает и в HTML, и
  в JSON, и в письмо;
- значения атрибутов — только в двойных кавычках: `value="<?= htmlspecialcharsbx($v) ?>"`;
- обработчики событий (`onclick`) требуют двойного экранирования — сначала JS, потом HTML; проще
  вообще не генерировать их из данных;
- в компонентах `$arParams` уже закодирован, «сырое» значение — в ключе `~NAME`
  ([[concept-component-structure]]).

## Пользовательский HTML: `CBXSanitizer`

```php
$sanitizer = new CBXSanitizer();
$sanitizer->SetLevel(CBXSanitizer::SECURE_LEVEL_HIGH);
echo $sanitizer->SanitizeHtml($userHtml);
```

| Уровень | Значение | Что оставляет |
|---|---|---|
| `SECURE_LEVEL_HIGH` | 1 | базовые текстовые теги |
| `SECURE_LEVEL_MIDDLE` | 2 | плюс картинки и ссылки |
| `SECURE_LEVEL_LOW` | 3 | почти всё, кроме `script`, `iframe`, `embed` |
| `SECURE_LEVEL_CUSTOM` | 0 | список собираем сами через `AddTags()` |

Прогон на стенде: при высоком уровне из `<p>Текст</p><script>alert(1)</script><a href="#"
onclick="x()">ссылка</a>` осталось `<p>Текст</p>ссылка` — вырезан и скрипт, и ссылка целиком.
Отсюда практика: **начинаем с высокого уровня и опускаем только до тех пор, пока не появятся нужные
теги**, добавляя их точечно `AddTags()`.

Прочие методы: `DelTags()`, `UpdateTags()`, `DeleteAttributes()`, `DeleteSanitizedTags()`,
`GetTags()`, а также `ApplyDoubleEncode()` — он есть в ядре, хотя документация его не упоминает.

## CSRF: токен сессии

```php
<form method="post">
    <?= bitrix_sessid_post() ?>
    ...
</form>
```

```php
if (!check_bitrix_sessid())
{
    // запрос не от нашей формы
}
```

- `bitrix_sessid()` — сам токен, `bitrix_sessid_get()` — параметр для GET-ссылки,
  `bitrix_sessid_post()` — скрытое поле;
- `check_bitrix_sessid()` понимает и заголовок `X-Bitrix-Csrf-Token` — это путь для AJAX
  (`BX.bitrix_sessid()` на клиенте);
- токен ставим **в начало формы**: при HTML-инъекции в поля ниже он уже отрисован;
- в D7-контроллерах это делает фильтр `Csrf`, включённый по умолчанию
  ([[recipe-engine-controller-action]]) — снимать его `-prefilters` без причины нельзя;
- изменяющие операции — только `POST`, плюс `SameSite` у cookie ([[entity-web-cookie]]).

## SSRF: адрес от пользователя

Запрос по ссылке, которую прислал пользователь, по умолчанию уйдёт и на `127.0.0.1`, и во
внутреннюю сеть: у `HttpClient` параметр `privateIp` равен `true`
([[recipe-http-client]]). Для таких сценариев:

```php
$http = new \Bitrix\Main\Web\HttpClient();
$http->setPrivateIp(false);
```

Загрузка картинок по ссылке — `CFile::MakeFileArray()` плюс `CFile::CheckImageFile()`: оба метода на
стенде есть и проверяют, что пришёл действительно файл изображения.

## SQL-инъекции

Отдельной магии нет: пользуемся ORM, а в прямом SQL — плейсхолдерами `SqlExpression` и методами
`SqlHelper`; параметр `binds` защиты не даёт ([[concept-d7-sql-layer]]).

## Связанные страницы
- [[concept-coding-standards]] — общие правила кода и секретов
- [[checklist-box-security]] — что проверить перед сдачей
- [[recipe-encrypt-sensitive-data]] — если данные надо хранить зашифрованными

[← Ядро D7](_index-core-d7.md)
