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
    '.projecttaskdashboard .ptd-reset-filters',
    'reset=reset',
];

foreach ($checks as $needle) {
    if (strpos($js, $needle) === false) {
        fwrite(STDERR, "missing widget tab-reload behavior: {$needle}\n");
        exit(1);
    }
}

echo "widget tab reload ok\n";
