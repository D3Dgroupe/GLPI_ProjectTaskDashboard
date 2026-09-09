<?php

declare(strict_types=1);

$renderer = @file_get_contents(__DIR__ . '/../src/MyTasksRenderer.php');
$route = @file_get_contents(__DIR__ . '/../front/mytasks.php');
assert($renderer !== false, 'MyTasksRenderer must exist');
assert($route !== false, 'mytasks route must exist');
assert(str_contains($renderer, 'ProjectTask::canView()'));
assert(str_contains($renderer, 'projecttaskdashboard-mytasks'));
assert(str_contains($renderer, 'data-mytasks-target'));
assert(str_contains($renderer, '👤 Mes tâches'));
assert(str_contains($route, "Session::checkCentralAccess()"));
assert(str_contains($route, "Html::header('Mes tâches'"));
assert(str_contains($route, "'projecttaskdashboard_project'"));
assert(str_contains($route, 'MyTasksRenderer'));

echo "mytasks renderer contract ok\n";
