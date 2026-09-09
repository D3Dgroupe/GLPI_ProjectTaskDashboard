<?php

declare(strict_types=1);

class ProjectTask {}

require_once __DIR__ . '/../src/Search/ScopedProjectTaskSearchSession.php';
require_once __DIR__ . '/../src/Search/DashboardSearchSession.php';
require_once __DIR__ . '/../src/Search/MyTasksSearchSession.php';

use GlpiPlugin\Projecttaskdashboard\Search\DashboardSearchSession;
use GlpiPlugin\Projecttaskdashboard\Search\MyTasksSearchSession;

$_SESSION = [
    'glpisearch' => [
        ProjectTask::class => ['criteria' => [['field' => 87]]],
    ],
];

$dashboard = new DashboardSearchSession();
$dashboard->setCriteria([['field' => 12]]);
$seenDashboard = $dashboard->run(fn(): int => (int) $_SESSION['glpisearch'][ProjectTask::class]['criteria'][0]['field']);
assert($seenDashboard === 12);
assert($_SESSION['glpisearch'][ProjectTask::class]['criteria'][0]['field'] === 87);
assert($_SESSION['projecttaskdashboard']['search_scopes']['dashboard']['search']['criteria'][0]['field'] === 12);

$mytasks = new MyTasksSearchSession();
$mytasks->setCriteria([['field' => 1]]);
$seenMyTasks = $mytasks->run(fn(): int => (int) $_SESSION['glpisearch'][ProjectTask::class]['criteria'][0]['field']);
assert($seenMyTasks === 1);
assert($_SESSION['glpisearch'][ProjectTask::class]['criteria'][0]['field'] === 87);
assert($_SESSION['projecttaskdashboard']['search_scopes']['mytasks']['search']['criteria'][0]['field'] === 1);
assert($_SESSION['projecttaskdashboard']['search_scopes']['dashboard']['search']['criteria'][0]['field'] === 12);

try {
    $mytasks->run(static function (): void {
        $_SESSION['glpisearch'][ProjectTask::class]['criteria'] = [['field' => 999]];
        throw new RuntimeException('boom');
    });
    assert(false, 'exception expected');
} catch (RuntimeException $e) {
    assert($e->getMessage() === 'boom');
}
assert($_SESSION['glpisearch'][ProjectTask::class]['criteria'][0]['field'] === 87);

try {
    new GlpiPlugin\Projecttaskdashboard\Search\ScopedProjectTaskSearchSession('');
    assert(false, 'empty scope must throw');
} catch (InvalidArgumentException) {
}

echo "scoped search sessions ok\n";
