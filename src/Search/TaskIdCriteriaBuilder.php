<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Search;

use GlpiPlugin\Projecttaskdashboard\Config;

final class TaskIdCriteriaBuilder
{
    public function criterion(array $taskIds, ?string $link = null, bool $hidden = false): array
    {
        $taskIds = array_values(array_unique(array_filter(
            array_map('intval', $taskIds),
            static fn(int $id): bool => $id > 0
        )));

        if ($taskIds === []) {
            $criterion = [
                'field' => Config::FIELD_TASK_ID_INTERNAL,
                'searchtype' => 'equals',
                'value' => -1,
            ];
        } elseif (count($taskIds) === 1) {
            $criterion = [
                'field' => Config::FIELD_TASK_ID_INTERNAL,
                'searchtype' => 'equals',
                'value' => $taskIds[0],
            ];
        } else {
            $children = [];
            foreach ($taskIds as $index => $id) {
                $child = [
                    'field' => Config::FIELD_TASK_ID_INTERNAL,
                    'searchtype' => 'equals',
                    'value' => $id,
                ];
                if ($index > 0) {
                    $child['link'] = 'OR';
                }
                $children[] = $child;
            }
            $criterion = ['criteria' => $children];
        }

        if (is_string($link) && $link !== '') {
            $criterion['link'] = $link;
        }
        if ($hidden) {
            $criterion['_hidden'] = true;
        }

        return $criterion;
    }
}
