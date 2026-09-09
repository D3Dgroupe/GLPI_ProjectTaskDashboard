<?php

declare(strict_types=1);

$renderer = file_get_contents(__DIR__ . '/../src/DashboardRenderer.php');
$template = @file_get_contents(__DIR__ . '/../templates/dashboard/header.html.twig');
assert($renderer !== false);
assert($template !== false, 'dashboard header template must exist');
assert(str_contains($renderer, 'ProjectTaskCreateLink'));
assert(str_contains($renderer, "dashboard/header.html.twig"));
assert(str_contains($renderer, "'create_url'"));
assert(str_contains($template, 'Ajouter une tâche'));
assert(str_contains($template, 'create_url'));

echo "dashboard create action contract ok\n";
