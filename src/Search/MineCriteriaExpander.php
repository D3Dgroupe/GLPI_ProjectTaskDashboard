<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Search;

use GlpiPlugin\Projecttaskdashboard\Config;

final class MineCriteriaExpander
{
    public function __construct(private readonly TaskIdCriteriaBuilder $builder = new TaskIdCriteriaBuilder())
    {
    }

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
        $expanded = [];
        foreach ($criteria as $criterion) {
            if (!is_array($criterion)) {
                continue;
            }

            if ($this->isMineMarker($criterion)) {
                $link = isset($criterion['link']) && is_string($criterion['link'])
                    ? $criterion['link']
                    : null;
                $expanded[] = $this->builder->criterion($taskIds, $link);
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
}
