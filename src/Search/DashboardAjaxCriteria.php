<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Search;

use GlpiPlugin\Projecttaskdashboard\Config;
use InvalidArgumentException;

/**
 * Build the execution-only criteria used by GLPI native AJAX refreshes.
 *
 * User criteria are always grouped before the mandatory project criterion is
 * appended. This prevents an OR supplied by the search UI from escaping the
 * current project scope.
 */
final class DashboardAjaxCriteria
{
    public function forceProject(array $criteria, int $projectId): array
    {
        if ($projectId <= 0) {
            throw new InvalidArgumentException('Project ID must be positive');
        }

        $execution = [];
        if ($criteria !== []) {
            $execution[] = ['criteria' => array_values($criteria)];
        }

        $execution[] = [
            'link' => 'AND',
            'field' => Config::FIELD_PROJECT,
            'searchtype' => 'equals',
            'value' => $projectId,
            'virtual' => true,
            '_hidden' => true,
        ];

        return $execution;
    }
}
