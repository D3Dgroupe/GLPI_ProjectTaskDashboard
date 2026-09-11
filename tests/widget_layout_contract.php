<?php

declare(strict_types=1);

// All 7 widgets on one row once there's enough width, in the requested
// order: En veille, À faire, En cours, À contrôler, Bloqué / attente,
// Idée / brouillon, Mes tâches.

$widgetsTemplate = file_get_contents(__DIR__ . '/../templates/dashboard/widgets.html.twig');
$css = file_get_contents(__DIR__ . '/../css/projecttaskdashboard.css');
$publicCss = file_get_contents(__DIR__ . '/../public/css/projecttaskdashboard.css');

assert($widgetsTemplate !== false && $css !== false && $publicCss !== false);
assert($css === $publicCss, 'root/public CSS copies must stay identical');

$expectedOrder = ['on_hold', 'todo', 'in_progress', 'check', 'blocked', 'idea', 'mine'];
$positions = [];
foreach ($expectedOrder as $key) {
    $pos = strpos($widgetsTemplate, "key: '{$key}'");
    assert($pos !== false, "widget '{$key}' must be present");
    $positions[] = $pos;
}
$sorted = $positions;
sort($sorted);
assert(
    $positions === $sorted,
    'widgets must render in the requested order: En veille, À faire, En cours, À contrôler, Bloqué / attente, Idée / brouillon, Mes tâches'
);

assert(str_contains($css, '.ptd-widgets {') && str_contains($css, 'display: grid'), 'widgets must use a CSS grid layout');
assert(str_contains($css, 'repeat(7, 1fr)'), 'widgets must be able to lay out all 7 on a single row');

echo "widget layout contract ok\n";
