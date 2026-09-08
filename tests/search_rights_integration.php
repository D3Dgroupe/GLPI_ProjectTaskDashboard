<?php

declare(strict_types=1);

$adapter = file_get_contents(__DIR__ . '/../src/Search/NativeSearchAdapter.php');
$counter = file_get_contents(__DIR__ . '/../src/Search/NativeSearchCounter.php');

assert($adapter !== false && $counter !== false);

assert(str_contains($adapter, 'ProjectSearchRightsScope'), 'adapter must depend on ProjectSearchRightsScope');
assert(preg_match('/projectSearchRights->run\(.*SearchEngine::showOutput/s', $adapter) === 1, 'adapter must execute SearchEngine output inside rights scope');
assert(str_contains($counter, 'ProjectSearchRightsScope'), 'counter must depend on ProjectSearchRightsScope');
assert(preg_match('/projectSearchRights->run\(.*SearchEngine::prepareDataForSearch/s', $counter) === 1, 'counter must execute SearchEngine count inside rights scope');

echo "search rights integration ok\n";
