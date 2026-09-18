<?php
/**
 * Сверка на корпусе реальных экспортов. Корпус в репозитории не хранится:
 *   php tools/bpt/tests/run.php --corpus "C:/путь/к/папке"
 * Без ключа --corpus эти тесты тихо проходят (проверять нечего).
 */

declare(strict_types=1);

test('Корпус: разбор и сборка совпадают по смыслу', function () {
    $catalog = Catalog::load();
    foreach (corpusFiles() as $file) {
        $original = BptFile::read($file)['data'];
        $spec = (new Decompiler($catalog, null, true))->decompile($original)['spec'];
        $built = (new Compiler($catalog))->compile($spec);
        // Ошибки анализатора наследуются от оригинала (например, висячие ссылки) — их не считаем
        $errors = array_values(array_filter($built['errors'], fn ($e) => !str_starts_with($e, 'проверка:')));
        assertSame([], $errors, basename($file) . ': сборка без ошибок');
        assertSame(canonicalTree($original, $catalog), canonicalTree($built['bpt'], $catalog),
            basename($file) . ': дерево');
        assertSame(canonicalDefinitions($original), canonicalDefinitions($built['bpt']),
            basename($file) . ': параметры, переменные и константы');
    }
});

test('Корпус: снимок и плейсхолдеры не меняют результат', function () {
    $catalog = Catalog::load();
    foreach (corpusFiles() as $file) {
        $original = BptFile::read($file)['data'];
        $snapshot = Snapshot::fromBpt($original, basename($file));
        $spec = (new Decompiler($catalog, $snapshot, true))->decompile($original)['spec'];
        $built = (new Compiler($catalog, $snapshot))->compile($spec);
        $errors = array_values(array_filter($built['errors'], fn ($e) => !str_starts_with($e, 'проверка:')));
        assertSame([], $errors, basename($file) . ': сборка со снимком без ошибок');
        assertSame(canonicalTree($original, $catalog), canonicalTree($built['bpt'], $catalog),
            basename($file) . ': дерево со снимком');
    }
});

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
