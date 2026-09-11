<?php

declare(strict_types=1);

// Match GLPI's own SearchEngine::show(): only render the full inline criteria
// form when $_SESSION['glpishow_search_form'] asks for it. Previously the
// dashboard forced that session flag to 1 for the whole request
// (SearchFormPreferenceScope) to avoid rendering two criteria builders at
// once (GLPI's own collapsed "Search" button/dropdown, plus our own explicit
// call). That always showed the full form, regardless of the user's actual
// preference, unlike GLPI's native search pages (e.g. Tickets).
//
// The correct fix is to gate our own explicit call the same way GLPI's core
// code does, so exactly one criteria builder renders in either preference
// state: ours when the preference is on, GLPI's own collapsed dropdown
// (rendered by SearchEngine::showOutput, already called right after) when
// it's off.

$nativeAdapter = file_get_contents(__DIR__ . '/../src/Search/NativeSearchAdapter.php');
$myTasksAdapter = file_get_contents(__DIR__ . '/../src/Search/MyTasksSearchAdapter.php');
$dashboardAjaxContext = file_get_contents(__DIR__ . '/../src/Search/DashboardAjaxSearchContext.php');
$myTasksAjaxContext = file_get_contents(__DIR__ . '/../src/Search/MyTasksAjaxSearchContext.php');

assert(
    $nativeAdapter !== false && $myTasksAdapter !== false
    && $dashboardAjaxContext !== false && $myTasksAjaxContext !== false
);

foreach ([$nativeAdapter, $myTasksAdapter] as $source) {
    assert(
        str_contains($source, "\$_SESSION['glpishow_search_form'] ?? true"),
        'the explicit showGenericSearch() call must be gated behind the real user preference'
    );
}

foreach ([$nativeAdapter, $myTasksAdapter, $dashboardAjaxContext, $myTasksAjaxContext] as $source) {
    assert(
        !str_contains($source, 'SearchFormPreferenceScope'),
        'the search form preference must no longer be force-overridden anywhere'
    );
}

assert(!file_exists(__DIR__ . '/../src/Search/SearchFormPreferenceScope.php'), 'unused scope class must be removed');

echo "search form native toggle contract ok\n";
