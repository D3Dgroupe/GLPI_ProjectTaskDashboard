<?php

declare(strict_types=1);

// The ProjectTask table's column header row (thead) must stay pinned while
// scrolling, matching GLPI's native search pages (e.g. Tickets). Because
// #8's fix (search-no-forced-height) makes `.search-container` no longer
// scroll on its own, GLPI's native thead sticky offset (calibrated for that
// container being the scrollport) no longer lines up, so the plugin defines
// its own offset anchored to the dashboard's own sticky header.

$rootCss = file_get_contents(__DIR__ . '/../css/projecttaskdashboard.css');
$publicCss = file_get_contents(__DIR__ . '/../public/css/projecttaskdashboard.css');

assert($rootCss !== false && $publicCss !== false);
assert($rootCss === $publicCss, 'root/public CSS copies must stay identical');

assert(
    str_contains($rootCss, '.projecttaskdashboard .search-results thead:first-child th'),
    'project dashboard table header must be targeted for sticky positioning'
);
assert(
    str_contains($rootCss, '.projecttaskdashboard-mytasks .search-results thead:first-child th'),
    'mytasks table header must be targeted for sticky positioning'
);
assert(
    str_contains($rootCss, 'position: sticky !important;'),
    'table header must be forced sticky regardless of GLPI core specificity'
);
assert(
    str_contains($rootCss, '--ptd-header-height'),
    'table header offset must be anchored to the dashboard header height, not a bare magic number'
);
assert(
    str_contains($rootCss, '.projecttaskdashboard .search-header'),
    'native search-header sticky bar must be neutralized to avoid fighting the dashboard header'
);
assert(
    str_contains($rootCss, 'overflow: visible !important;'),
    '.search-container must stop being a scroll container, otherwise position:sticky descendants '
    . 'anchor to it instead of the real scrolling region and never visually stick'
);
assert(
    str_contains($rootCss, 'z-index: calc(var(--glpi-zindex-sticky, 1020) + 1);'),
    'dashboard header must paint above the sticky thead, otherwise the table header row '
    . 'covers it while scrolling through the transition before the thead itself sticks'
);

echo "table sticky header contract ok\n";
