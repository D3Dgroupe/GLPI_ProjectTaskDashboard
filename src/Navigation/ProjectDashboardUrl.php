<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Navigation;

use InvalidArgumentException;
use Project;

final class ProjectDashboardUrl
{
    public function __construct(private readonly ProjectTabsManager $tabs = new ProjectTabsManager())
    {
    }

    public function forProjectId(int $projectId): string
    {
        if ($projectId <= 0) {
            throw new InvalidArgumentException('Project ID must be positive');
        }

        $target = Project::getFormURLWithID($projectId);
        return $target
            . (str_contains($target, '?') ? '&' : '?')
            . 'forcetab=' . rawurlencode($this->tabs->dashboardForcetab());
    }
}
