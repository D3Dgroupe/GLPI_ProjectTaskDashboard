<?php

declare(strict_types=1);

$ctx = @file_get_contents(__DIR__ . '/../src/Search/DashboardAjaxSearchContext.php');
$session = @file_get_contents(__DIR__ . '/../src/Search/ScopedProjectTaskSearchSession.php');
$wrapper = @file_get_contents(__DIR__ . '/../src/Search/DashboardSearchSession.php');
$rights = @file_get_contents(__DIR__ . '/../src/Search/ProjectSearchRightsScope.php');

assert($ctx !== false, 'DashboardAjaxSearchContext must exist');
assert($session !== false && $wrapper !== false && $rights !== false, 'request scopes must exist');
assert(str_contains($ctx, "'display_results'"), 'scope must only target native result AJAX');
assert(str_contains($ctx, 'ProjectTask::class'), 'scope must only target ProjectTask');
assert(str_contains($ctx, 'forceProject'), 'scope must force project criteria');
assert(str_contains($ctx, 'projectSearchRights->enter()'), 'scope must enable task search rights workaround');
assert(str_contains($ctx, 'dashboardSession->enter()'), 'scope must isolate ProjectTask search session');
assert(!str_contains($ctx, 'SearchFormPreferenceScope'), 'AJAX refreshes must respect the real search form preference, not force it');
assert(str_contains($ctx, 'register_shutdown_function'), 'request scopes must be restored at request shutdown');
assert(str_contains($session, 'public function enter()') && str_contains($session, 'public function leave()'));
assert(str_contains($wrapper, "parent::__construct('dashboard')"));
assert(str_contains($rights, 'public function enter()') && str_contains($rights, 'public function leave()'));

echo "ajax runtime scope contract ok\n";
