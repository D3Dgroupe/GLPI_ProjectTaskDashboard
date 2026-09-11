<?php

declare(strict_types=1);

// GLPI's native `.search-container` forces `height: calc(100vh - contextbar - margin)`
// with `overflow: auto`, assuming the search results are the only content on the page.
// Both dashboard views (project dashboard and the global "Mes tâches" view) render a
// header above the search results (title/button, KPI widgets). Without disabling that
// forced height, the extra header pushes the search container beyond the viewport,
// producing two independent scrollbars instead of one (see issue #8).
//
// GLPI ships `.search-no-forced-height` for exactly this case ("Useful when displaying
// multiple search results on the same page" — same applies to extra content above them).

$nativeSearchAdapter = file_get_contents(__DIR__ . '/../src/Search/NativeSearchAdapter.php');
$myTasksSearchAdapter = file_get_contents(__DIR__ . '/../src/Search/MyTasksSearchAdapter.php');

assert($nativeSearchAdapter !== false && $myTasksSearchAdapter !== false);

assert(
    str_contains($nativeSearchAdapter, "search_page row search-no-forced-height"),
    'project dashboard search_page must disable the forced search-container height'
);
assert(
    str_contains($myTasksSearchAdapter, "search_page row search-no-forced-height"),
    'mytasks search_page must disable the forced search-container height'
);

echo "search page forced height contract ok\n";
