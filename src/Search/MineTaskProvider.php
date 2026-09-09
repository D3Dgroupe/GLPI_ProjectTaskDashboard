<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Search;

use Group;
use ProjectTaskTeam;
use Session;
use User;

final class MineTaskProvider
{
    /**
     * Return every project task assigned directly to the current user or to one
     * of their current GLPI groups. ACL and project scoping are still applied by
     * the native ProjectTask Search Engine afterwards.
     *
     * @return int[]
     */
    public function taskIds(): array
    {
        global $DB;

        $or = [];
        $userId = (int) Session::getLoginUserID();
        if ($userId > 0) {
            $or[] = [
                'itemtype' => User::class,
                'items_id' => $userId,
            ];
        }

        $groupIds = array_values(array_unique(array_filter(
            array_map('intval', $_SESSION['glpigroups'] ?? []),
            static fn(int $id): bool => $id > 0
        )));
        if ($groupIds !== []) {
            $or[] = [
                'itemtype' => Group::class,
                'items_id' => $groupIds,
            ];
        }

        if ($or === []) {
            return [];
        }

        $rows = $DB->request([
            'SELECT' => 'projecttasks_id',
            'FROM' => ProjectTaskTeam::getTable(),
            'WHERE' => ['OR' => $or],
        ]);

        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['projecttasks_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }
}
