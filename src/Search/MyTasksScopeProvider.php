<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Search;

use GlpiPlugin\Projecttaskdashboard\Project\ActiveProjectProvider;
use ProjectTask;

final class MyTasksScopeProvider
{
    public function __construct(
        private readonly MineTaskProvider $mineTasks = new MineTaskProvider(),
        private readonly ActiveProjectProvider $projects = new ActiveProjectProvider(),
    ) {
    }

    /** @return array<int,int> */
    public function taskIds(): array
    {
        global $DB;

        $mineTaskIds = array_values(array_unique(array_filter(
            array_map('intval', $this->mineTasks->taskIds()),
            static fn(int $id): bool => $id > 0
        )));
        $activeProjectIds = array_values(array_unique(array_filter(
            array_map('intval', $this->projects->ids()),
            static fn(int $id): bool => $id > 0
        )));

        if ($mineTaskIds === [] || $activeProjectIds === []) {
            return [];
        }

        $rows = $DB->request([
            'SELECT' => 'id',
            'FROM' => ProjectTask::getTable(),
            'WHERE' => [
                'id' => $mineTaskIds,
                'projects_id' => $activeProjectIds,
            ],
        ]);

        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }
}
