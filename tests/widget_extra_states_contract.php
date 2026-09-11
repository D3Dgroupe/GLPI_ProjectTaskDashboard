<?php

declare(strict_types=1);

// Issue #10: add distinct, clickable widgets for EN VEILLE (ProjectState id
// 5), BLOQUÉ / ATTENTE (id 9) and IDÉE / BROUILLON (id 6), same behaviour as
// the existing À FAIRE / EN COURS / À CONTRÔLER widgets (own counter, click
// to filter, colored per the ProjectState's configured color).

$config = file_get_contents(__DIR__ . '/../src/Config.php');
$transformer = file_get_contents(__DIR__ . '/../src/Search/CriteriaTransformer.php');
$counter = file_get_contents(__DIR__ . '/../src/Search/NativeSearchCounter.php');
$renderer = file_get_contents(__DIR__ . '/../src/DashboardRenderer.php');
$widgetsTemplate = file_get_contents(__DIR__ . '/../templates/dashboard/widgets.html.twig');

assert(
    $config !== false && $transformer !== false && $counter !== false
    && $renderer !== false && $widgetsTemplate !== false
);

assert(str_contains($config, 'STATE_ON_HOLD = 5'), 'EN VEILLE must be ProjectState id 5');
assert(str_contains($config, 'STATE_BLOCKED = 9'), 'BLOQUÉ / ATTENTE must be ProjectState id 9');
assert(str_contains($config, 'STATE_IDEA = 6'), 'IDÉE / BROUILLON must be ProjectState id 6');

foreach (['Config::STATE_ON_HOLD', 'Config::STATE_BLOCKED', 'Config::STATE_IDEA'] as $needle) {
    assert(str_contains($transformer, $needle), "CriteriaTransformer must recognize {$needle} as a valid widget state");
}

foreach (["'on_hold' => \$stateCount(Config::STATE_ON_HOLD)", "'blocked' => \$stateCount(Config::STATE_BLOCKED)", "'idea' => \$stateCount(Config::STATE_IDEA)"] as $needle) {
    assert(str_contains($counter, $needle), "NativeSearchCounter must count {$needle}");
}

foreach (['on_hold', 'blocked', 'idea'] as $key) {
    assert(str_contains($renderer, "'{$key}' =>"), "DashboardRenderer must wire up the {$key} state");
}

foreach ([
    "{ key: 'on_hold', label: '⏸️ EN VEILLE'",
    "{ key: 'blocked', label: '❓ BLOQUÉ / ATTENTE'",
    "{ key: 'idea', label: '💡 IDÉE / BROUILLON'",
] as $needle) {
    assert(str_contains($widgetsTemplate, $needle), "widgets template must render a distinct clickable widget: {$needle}");
}

echo "widget extra states contract ok\n";
