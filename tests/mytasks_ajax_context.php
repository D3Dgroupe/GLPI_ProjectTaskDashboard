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
    'projectSearchRights->enter()',
    'register_shutdown_function',
] as $needle) {
    assert(str_contains($ctx, $needle), 'missing ' . $needle);
}
assert(str_contains($ctx, 'restore()'));
assert(!str_contains($ctx, 'SearchFormPreferenceScope'), 'AJAX refreshes must respect the real search form preference, not force it');

echo "mytasks ajax context ok\n";
