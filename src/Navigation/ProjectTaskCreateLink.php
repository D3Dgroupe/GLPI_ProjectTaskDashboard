<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Navigation;

use Project;
use ProjectTask;

final class ProjectTaskCreateLink
{
    public function url(Project $project): ?string
    {
        $projectId = (int) $project->getID();
        if ($projectId <= 0 || !$project->canViewItem() || !ProjectTask::canCreate()) {
            return null;
        }

        $url = ProjectTask::getFormURL(false);
        return $url
            . (str_contains($url, '?') ? '&' : '?')
            . 'projects_id=' . $projectId;
    }
}
