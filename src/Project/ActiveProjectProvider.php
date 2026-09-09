<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Project;

use Project;
use ProjectState;

final class ActiveProjectProvider
{
    /**
     * @return array<int,array{id:int,name:string}>
     */
    public function all(): array
    {
        global $DB;

        $rows = $DB->request([
            'SELECT' => [
                Project::getTable() . '.id',
                Project::getTable() . '.name',
                ProjectState::getTable() . '.is_finished',
            ],
            'FROM' => Project::getTable(),
            'LEFT JOIN' => [
                ProjectState::getTable() => [
                    'ON' => [
                        Project::getTable() => 'projectstates_id',
                        ProjectState::getTable() => 'id',
                    ],
                ],
            ],
            'WHERE' => [Project::getTable() . '.is_deleted' => 0],
        ]);

        $projects = [];
        foreach ($rows as $row) {
            if ((int) ($row['is_finished'] ?? 0) === 1) {
                continue;
            }

            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $project = new Project();
            if (!$project->getFromDB($id) || !$project->canViewItem()) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $projects[] = [
                'id' => $id,
                'name' => $name !== '' ? $name : 'Projet #' . $id,
            ];
        }

        usort($projects, static function (array $a, array $b): int {
            $cmp = strnatcasecmp($a['name'], $b['name']);
            return $cmp !== 0 ? $cmp : ($a['id'] <=> $b['id']);
        });

        return $projects;
    }

    /**
     * @return array<int,int>
     */
    public function ids(): array
    {
        return array_map(
            static fn(array $project): int => $project['id'],
            $this->all()
        );
    }
}
