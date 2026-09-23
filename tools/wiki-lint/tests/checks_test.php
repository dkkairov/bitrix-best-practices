<?php
/** Тесты проверок: каждая ловит свою поломку и молчит на здоровой вики. */

declare(strict_types=1);

/** Маленькая здоровая вики: хаб, две страницы, навигатор. */
function healthyWiki(array $extra = []): string
{
    return makeWiki(array_merge([
        'wiki/development/core-d7/_index-core-d7.md' => page('Ядро D7', 'index', ['verified' => '"2026-09-01 / состав папки"'],
            "- [[concept-alpha]]\n- [[entity-beta]]"),
        'wiki/development/core-d7/concept-alpha.md' => page('Альфа', 'concept', [], 'Ссылка на [[entity-beta]].'),
        'wiki/development/core-d7/entity-beta.md' => page('Бета', 'entity', [], 'Ссылка на [[concept-alpha]].'),
        'index.md' => "# Каталог\n- [[concept-alpha]]\n- [[entity-beta]]\n",
    ], $extra));
}

test('Здоровая вики: ошибок нет', function () {
    $root = healthyWiki();
    $checks = new Checks(Wiki::load($root), '2026-09-23');
    $errors = array_filter($checks->all(), static fn (Finding $f): bool => $f->level === 'error');
    assertSame([], messages(array_values($errors)), 'на здоровой вики ошибок быть не должно');
});

test('Ссылки: битая ссылка найдена', function () {
    $root = healthyWiki([
        'wiki/development/core-d7/concept-alpha.md' => page('Альфа', 'concept', [], 'Ведёт в [[concept-нет-такой]].'),
    ]);
    assertSame(
        ['wiki/development/core-d7/concept-alpha.md: битая ссылка [[concept-нет-такой]]'],
        messages(runCheck($root, 'brokenLinks'))
    );
});

test('Ссылки: код в блоках и экранированный | не считаются', function () {
    $body = "Таблица: [[entity-beta\\|Бета]].\n\n```php\n\$a = [['x' => 1]];\n```\n`[[не ссылка]]`";
    $root = healthyWiki([
        'wiki/development/core-d7/concept-alpha.md' => page('Альфа', 'concept', [], $body),
    ]);
    assertSame([], messages(runCheck($root, 'brokenLinks')), 'ложных срабатываний быть не должно');
});

test('Орфаны: страница без входящих', function () {
    $root = healthyWiki([
        'wiki/development/core-d7/recipe-lonely.md' => page('Одинокий', 'recipe'),
        'wiki/development/core-d7/_index-core-d7.md' => page('Ядро D7', 'index', ['verified' => '"2026-09-01 / состав"'],
            "- [[concept-alpha]]\n- [[entity-beta]]\n- [[recipe-lonely]]"),
    ]);
    // хаб ссылается, значит орфана нет
    assertSame([], messages(runCheck($root, 'orphans')));

    $root2 = healthyWiki(['wiki/development/core-d7/recipe-lonely.md' => page('Одинокий', 'recipe')]);
    assertSame(
        ['wiki/development/core-d7/recipe-lonely.md: нет входящих ссылок'],
        messages(runCheck($root2, 'orphans'))
    );
});

test('Frontmatter: нет обязательных полей и странный edition', function () {
    $root = healthyWiki([
        'wiki/development/core-d7/concept-alpha.md' => page('Альфа', 'concept', ['module' => null, 'edition' => 'oblako'], '[[entity-beta]]'),
    ]);
    $msgs = messages(runCheck($root, 'frontmatter'));
    assertSame(2, count($msgs), 'ожидались две находки: ' . shortJson($msgs));
    assertTrue(str_contains($msgs[0], 'нет полей: module'), 'нет сообщения о поле module');
    assertTrue(str_contains($msgs[1], 'edition = «oblako»'), 'нет сообщения о edition');
});

test('Verified: пустое поле и протухшая дата', function () {
    $root = healthyWiki([
        'wiki/development/core-d7/concept-alpha.md' => page('Альфа', 'concept', ['verified' => '""'], '[[entity-beta]]'),
        'wiki/development/core-d7/entity-beta.md' => page('Бета', 'entity', ['verified' => '"2026-01-01 / давно"'], '[[concept-alpha]]'),
    ]);
    $msgs = messages(runCheck($root, 'verified'));
    assertTrue(str_contains($msgs[0], 'поле verified пустое'), 'не найдено пустое verified: ' . shortJson($msgs));
    assertTrue(str_contains($msgs[1], 'проверено давно: 2026-01-01'), 'не найдена протухшая дата: ' . shortJson($msgs));
});

test('Имена: префикс не совпадает с type', function () {
    $root = healthyWiki([
        'wiki/development/core-d7/concept-gamma.md' => page('Гамма', 'recipe', [], '[[entity-beta]]'),
        'index.md' => "# Каталог\n- [[concept-alpha]]\n- [[entity-beta]]\n- [[concept-gamma]]\n",
    ]);
    $msgs = messages(runCheck($root, 'naming'));
    assertSame(1, count($msgs), shortJson($msgs));
    assertTrue(str_contains($msgs[0], 'ожидался префикс «recipe-»'), shortJson($msgs));
});

test('Структура: подпапка по типу внутри раздела', function () {
    $root = healthyWiki([
        'wiki/development/core-d7/recipes/recipe-deep.md' => page('Глубокий', 'recipe', [], '[[entity-beta]]'),
    ]);
    $msgs = messages(runCheck($root, 'structure'));
    assertSame(1, count($msgs), shortJson($msgs));
    assertTrue(str_contains($msgs[0], 'wiki/development/core-d7/recipes'), shortJson($msgs));
});

test('Хабы: страница не перечислена в хабе', function () {
    $root = healthyWiki([
        'wiki/development/core-d7/recipe-new.md' => page('Новый', 'recipe', [], '[[entity-beta]]'),
        'index.md' => "# Каталог\n- [[concept-alpha]]\n- [[entity-beta]]\n- [[recipe-new]]\n",
    ]);
    $msgs = messages(runCheck($root, 'hubs'));
    assertSame(1, count($msgs), shortJson($msgs));
    assertTrue(str_contains($msgs[0], 'не перечислены: recipe-new'), shortJson($msgs));
});

test('Aliases: alias у двух страниц и alias-двойник имени', function () {
    $root = healthyWiki([
        'wiki/development/core-d7/concept-alpha.md' => page('Альфа', 'concept', ['aliases' => '["общий", "entity-beta"]'], '[[entity-beta]]'),
        'wiki/development/core-d7/entity-beta.md' => page('Бета', 'entity', ['aliases' => '["общий"]'], '[[concept-alpha]]'),
    ]);
    $msgs = messages(runCheck($root, 'aliases'));
    assertSame(2, count($msgs), shortJson($msgs));
    assertTrue(str_contains(implode(' ', $msgs), 'alias «общий» у нескольких страниц'), shortJson($msgs));
    assertTrue(str_contains(implode(' ', $msgs), 'совпадает с именем страницы'), shortJson($msgs));
});

test('Навигатор: страницы нет в index.md', function () {
    $root = healthyWiki([
        'wiki/development/core-d7/recipe-hidden.md' => page('Скрытый', 'recipe', [], '[[entity-beta]]'),
        'wiki/development/core-d7/_index-core-d7.md' => page('Ядро D7', 'index', ['verified' => '"2026-09-01 / состав"'],
            "- [[concept-alpha]]\n- [[entity-beta]]\n- [[recipe-hidden]]"),
    ]);
    assertSame(
        ['wiki/development/core-d7/recipe-hidden.md: страницы нет в index.md'],
        messages(runCheck($root, 'navigator'))
    );
});

test('Даты: verified новее updated и updated в будущем', function () {
    $root = healthyWiki([
        'wiki/development/core-d7/concept-alpha.md' => page('Альфа', 'concept',
            ['verified' => '"2026-09-20 / свежо"', 'updated' => '"2026-09-01"'], '[[entity-beta]]'),
        'wiki/development/core-d7/entity-beta.md' => page('Бета', 'entity', ['updated' => '"2027-01-01"'], '[[concept-alpha]]'),
    ]);
    $msgs = messages(runCheck($root, 'dates'));
    assertTrue(str_contains(implode(' ', $msgs), 'новее updated'), shortJson($msgs));
    assertTrue(str_contains(implode(' ', $msgs), 'updated в будущем'), shortJson($msgs));
});

test('Секреты: вебхук в тексте страницы', function () {
    $root = healthyWiki([
        'wiki/development/core-d7/concept-alpha.md' => page('Альфа', 'concept', [], "Адрес https://portal.example/rest/12/ab12cd34ef56/ — [[entity-beta]]"),
    ]);
    $msgs = messages(runCheck($root, 'secrets'));
    assertSame(1, count($msgs), shortJson($msgs));
    assertTrue(str_contains($msgs[0], 'вебхук'), shortJson($msgs));
});

test('Отчёт: код выхода и статистика', function () {
    $root = healthyWiki();
    $wiki = Wiki::load($root);
    $report = new Report((new Checks($wiki, '2026-09-23'))->all(), $wiki);
    assertTrue(!$report->hasErrors(), 'на здоровой вики ошибок быть не должно');
    assertTrue(str_contains($report->text(), 'Страниц: 3'), 'в статистике нет числа страниц');

    $bad = healthyWiki(['wiki/development/core-d7/recipe-lonely.md' => page('Одинокий', 'recipe')]);
    $badWiki = Wiki::load($bad);
    $badReport = new Report((new Checks($badWiki, '2026-09-23'))->all(), $badWiki);
    assertTrue($badReport->hasErrors(), 'орфан должен давать ошибку');
});
