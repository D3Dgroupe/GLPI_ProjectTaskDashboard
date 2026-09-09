<?php

declare(strict_types=1);

$adapter = @file_get_contents(__DIR__ . '/../src/Search/MyTasksSearchAdapter.php');
assert($adapter !== false, 'MyTasksSearchAdapter must exist');
foreach ([
    'MyTasksScopeProvider',
    'MyTasksCriteriaGuard',
    'MyTasksSearchSession',
    "'ptd_scope' => 'mytasks'",
    "'usesession' => 0",
    'ProjectSearchRightsScope',
    'Config::FIELD_PROJECT',
] as $needle) {
    assert(str_contains($adapter, $needle), 'missing ' . $needle);
}
$projectPos = strpos($adapter, 'Config::FIELD_PROJECT');
$taskPos = strpos($adapter, '1,', strpos($adapter, 'defaultColumns'));
assert($taskPos !== false && $projectPos !== false && $taskPos < $projectPos);

echo "mytasks search adapter contract ok\n";
