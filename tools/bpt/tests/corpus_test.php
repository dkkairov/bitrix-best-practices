<?php
/**
 * Сверка на корпусе реальных экспортов. Корпус в репозитории не хранится:
 *   php tools/bpt/tests/run.php --corpus "C:/путь/к/папке"
 * Без ключа --corpus эти тесты тихо проходят (проверять нечего).
 */

declare(strict_types=1);

test('Корпус: каталог покрывает типы и свойства', function () {
    $catalog = Catalog::load();
    foreach (corpusFiles() as $file) {
        $data = BptFile::read($file)['data'];
        foreach (allActivities($data['TEMPLATE'][0]) as $node) {
            $type = (string) $node['Type'];
            assertTrue($catalog->has($type), basename($file) . ": типа {$type} нет в каталоге");
            foreach (array_keys($node['Properties'] ?? []) as $prop) {
                assertTrue($catalog->propType($type, (string) $prop) !== null,
                    basename($file) . ": свойства {$type}.{$prop} нет в каталоге");
            }
        }
    }
});
