<?php
/** Тесты сборки скрипта для контейнера (без стенда). */

declare(strict_types=1);

test('Стенд: вход, общая часть и скрипт — один PHP-поток', function () {
    $code = Stand::compose('setup', ['run' => 'пилот']);
    assertTrue(str_starts_with($code, '<?php'), 'начинается с <?php');
    assertSame(1, substr_count($code, '<?php'), 'открывающий тег один');
    assertTrue(str_contains($code, "\$GLOBALS['EVAL_INPUT']"), 'вход передан');
    assertTrue(str_contains($code, 'function eval_out('), 'общая часть подключена');
    assertTrue(!str_contains($code, 'declare(strict_types'), 'declare в потоке нет');
});
