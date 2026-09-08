<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Search;

use GlpiPlugin\Projecttaskdashboard\Config;
use InvalidArgumentException;
use Project;

final class ProjectContext
{
    public function criterion(Project $project): array
    {
        $id = (int) $project->getID();
        if ($id <= 0) {
            throw new InvalidArgumentException('Project must be persisted before rendering dashboard');
        }

        return [
            'field' => Config::FIELD_PROJECT,
            'searchtype' => 'equals',
            'value' => $id,
            'virtual' => true,
        ];
    }
}
