<?php

declare(strict_types=1);

$ctx = @file_get_contents(__DIR__ . '/../src/Search/MyTasksAjaxSearchContext.php');
assert($ctx !== false, 'MyTasksAjaxSearchContext must exist');
foreach ([
    "'display_results'",
    'ProjectTask::class',
    "'mytasks'",
    'MyTasksScopeProvider',
    'MyTasksCriteriaGuard',
    'MyTasksSearchSession',
    'mineExpander->expand',
    "['usesession'] = 0",
    'searchSession->enter()',
    'searchFormPreference->enter()',
    'projectSearchRights->enter()',
    'register_shutdown_function',
] as $needle) {
    assert(str_contains($ctx, $needle), 'missing ' . $needle);
}
assert(str_contains($ctx, 'restore()'));

echo "mytasks ajax context ok\n";
