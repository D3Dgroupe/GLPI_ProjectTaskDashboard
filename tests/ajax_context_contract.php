<?php

declare(strict_types=1);

$setup = file_get_contents(__DIR__ . '/../setup.php');
$adapter = file_get_contents(__DIR__ . '/../src/Search/NativeSearchAdapter.php');

assert($setup !== false && $adapter !== false);
assert(str_contains($setup, 'DashboardAjaxSearchContext'), 'setup must activate dashboard AJAX search context');
assert(str_contains($adapter, "'ptd_project_id'"), 'search form must transport project id to native AJAX requests');
assert(str_contains($adapter, "'usesession'"), 'native AJAX requests must be isolated from the global ProjectTask search session');

echo "ajax context contract ok\n";
