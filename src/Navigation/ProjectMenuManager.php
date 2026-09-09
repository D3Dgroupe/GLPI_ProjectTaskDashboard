<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Navigation;

use GlpiPlugin\Projecttaskdashboard\Project\ActiveProjectProvider;
use Project;
use ProjectTask;
use Session;
use Throwable;

final class ProjectMenuManager
{
    public function __construct(
        private readonly ActiveProjectProvider $projects = new ActiveProjectProvider(),
        private readonly ProjectDashboardUrl $dashboardUrl = new ProjectDashboardUrl(),
    ) {
    }

    public function redefine(array $menu): array
    {
        if (Session::getCurrentInterface() !== 'central' || !Project::canView()) {
            return $menu;
        }

        $sector = [
            'title' => 'Projet',
            'icon' => Project::getIcon(),
            'content' => [
                'projects' => [
                    'title' => '📋 Projets',
                    'page' => Project::getSearchURL(false),
                    'icon' => Project::getIcon(),
                ],
            ],
        ];

        try {
            foreach ($this->projects->all() as $project) {
                $id = (int) ($project['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $sector['content']['project_' . $id] = [
                    'title' => '📁 ' . (string) ($project['name'] ?? ('Projet #' . $id)),
                    'page' => $this->dashboardUrl->forProjectId($id),
                    'icon' => Project::getIcon(),
                ];
            }
        } catch (Throwable) {
            // Keep static entries usable if dynamic project loading fails.
        }

        if (ProjectTask::canView()) {
            $sector['content']['mytasks'] = [
                'title' => '👤 Mes tâches',
                'page' => '/plugins/projecttaskdashboard/front/mytasks.php',
                'icon' => 'ti ti-user-check',
            ];
        }

        $menu['projecttaskdashboard_project'] = $sector;
        return $menu;
    }
}
