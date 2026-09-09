<?php

declare(strict_types=1);

class Project
{
    public static function getFormURLWithID(int $id): string
    {
        return '/front/project.form.php?id=' . $id;
    }
}
class ProjectTask {}
class DashboardTabStub {}
class_alias(DashboardTabStub::class, 'GlpiPlugin\\Projecttaskdashboard\\DashboardTab');

foreach ([
    __DIR__ . '/../src/Navigation/ProjectTabsManager.php',
    __DIR__ . '/../src/Navigation/ProjectDashboardUrl.php',
] as $file) {
    if (is_file($file)) {
        require $file;
    }
}

assert(class_exists('GlpiPlugin\\Projecttaskdashboard\\Navigation\\ProjectTabsManager'), 'ProjectTabsManager must exist');
assert(class_exists('GlpiPlugin\\Projecttaskdashboard\\Navigation\\ProjectDashboardUrl'), 'ProjectDashboardUrl must exist');

use GlpiPlugin\Projecttaskdashboard\Navigation\ProjectDashboardUrl;
use GlpiPlugin\Projecttaskdashboard\Navigation\ProjectTabsManager;

$tabs = new ProjectTabsManager();
assert($tabs->mainForcetab() === 'Project$main');
assert($tabs->nativeTasksPrefix() === 'ProjectTask$');
assert(str_ends_with($tabs->dashboardForcetab(), 'DashboardTab$1'));

$url = (new ProjectDashboardUrl($tabs))->forProjectId(42);
assert(str_contains($url, 'id=42'));
assert(str_contains($url, 'forcetab='));

try {
    (new ProjectDashboardUrl($tabs))->forProjectId(0);
    assert(false, 'invalid id must throw');
} catch (InvalidArgumentException) {
}

echo "project navigation contract ok\n";
