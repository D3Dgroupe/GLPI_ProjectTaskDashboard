<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Search;

use GlpiPlugin\Projecttaskdashboard\Config;

final class MineCriteriaExpander
{
    /**
     * Replace the semantic "Mes tâches" marker with native SQL-searchable task
     * ID criteria. The semantic marker remains what gets displayed/saved.
     *
     * @param array $criteria
     * @param int[] $taskIds
     * @return array
     */
    public function expand(array $criteria, array $taskIds): array
    {
        $taskIds = array_values(array_unique(array_filter(
            array_map('intval', $taskIds),
            static fn(int $id): bool => $id > 0
        )));

        $expanded = [];
        foreach ($criteria as $criterion) {
            if (!is_array($criterion)) {
                continue;
            }

            if ($this->isMineMarker($criterion)) {
                $expanded[] = $this->taskIdCriterion($taskIds, $criterion['link'] ?? null);
                continue;
            }

            if (isset($criterion['criteria']) && is_array($criterion['criteria'])) {
                $criterion['criteria'] = $this->expand($criterion['criteria'], $taskIds);
            }
            $expanded[] = $criterion;
        }

        return array_values($expanded);
    }

    private function isMineMarker(array $criterion): bool
    {
        return !isset($criterion['criteria'])
            && (int) ($criterion['field'] ?? -1) === Config::FIELD_MINE_MARKER
            && ($criterion['searchtype'] ?? '') === 'equals'
            && (int) ($criterion['value'] ?? 0) === 1;
    }

    private function taskIdCriterion(array $taskIds, mixed $link): array
    {
        if ($taskIds === []) {
            $criterion = [
                'field' => Config::FIELD_TASK_ID_INTERNAL,
                'searchtype' => 'equals',
                'value' => -1,
            ];
            if (is_string($link) && $link !== '') {
                $criterion['link'] = $link;
            }
            return $criterion;
        }

        if (count($taskIds) === 1) {
            $criterion = [
                'field' => Config::FIELD_TASK_ID_INTERNAL,
                'searchtype' => 'equals',
                'value' => $taskIds[0],
            ];
            if (is_string($link) && $link !== '') {
                $criterion['link'] = $link;
            }
            return $criterion;
        }

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

        $group = ['criteria' => $children];
        if (is_string($link) && $link !== '') {
            $group['link'] = $link;
        }
        return $group;
    }
}
