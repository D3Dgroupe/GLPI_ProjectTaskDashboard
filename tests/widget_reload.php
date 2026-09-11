<?php

declare(strict_types=1);

$js = file_get_contents(__DIR__ . '/../js/projecttaskdashboard.js');
if ($js === false) {
    fwrite(STDERR, "unable to read JS\n");
    exit(1);
}

$checks = [
    '.projecttaskdashboard .ptd-widget',
    'window.reloadTab',
    'ptd_action',
    'ptd_state',
];

foreach ($checks as $needle) {
    if (strpos($js, $needle) === false) {
        fwrite(STDERR, "missing widget tab-reload behavior: {$needle}\n");
        exit(1);
    }
}

// The dedicated "Réinitialiser les filtres" button was removed: it duplicated
// GLPI's native search form reset ("x" next to the search button), which
// already sends the same reset=reset request. Its dead click handler must be
// gone too, not just the template markup.
if (strpos($js, 'ptd-reset-filters') !== false) {
    fwrite(STDERR, "dead ptd-reset-filters handler must be removed, native search reset already covers this\n");
    exit(1);
}

echo "widget tab reload ok\n";
