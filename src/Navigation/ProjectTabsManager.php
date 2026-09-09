<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Navigation;

use GlpiPlugin\Projecttaskdashboard\DashboardTab;
use Project;
use ProjectTask;

final class ProjectTabsManager
{
    public function mainForcetab(): string
    {
        return Project::class . '$main';
    }

    public function dashboardForcetab(): string
    {
        return DashboardTab::class . '$1';
    }

    public function nativeTasksPrefix(): string
    {
        return ProjectTask::class . '$';
    }
}
