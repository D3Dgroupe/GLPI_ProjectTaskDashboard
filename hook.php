<?php

declare(strict_types=1);

function plugin_projecttaskdashboard_install(): bool
{
    return true;
}

function plugin_projecttaskdashboard_uninstall(): bool
{
    return true;
}

/**
 * Add two plugin-owned ProjectTask search options:
 * - a semantic/savable "Mes tâches" marker, expanded before SQL execution;
 * - an internal task ID option used only by the expanded criteria.
 */
function plugin_projecttaskdashboard_getAddSearchOptions($itemtype): array
{
    if ($itemtype !== ProjectTask::class) {
        return [];
    }

    return [
        \GlpiPlugin\Projecttaskdashboard\Config::FIELD_MINE_MARKER => [
            'table' => ProjectTask::getTable(),
            'field' => 'id',
            'name' => 'Mes tâches (Pilotage)',
            'datatype' => 'bool',
            'massiveaction' => false,
            'nodisplay' => true,
        ],
        \GlpiPlugin\Projecttaskdashboard\Config::FIELD_TASK_ID_INTERNAL => [
            'table' => ProjectTask::getTable(),
            'field' => 'id',
            'name' => 'ID interne tâche (Pilotage)',
            'datatype' => 'number',
            'massiveaction' => false,
            'nosearch' => true,
            'nodisplay' => true,
        ],
    ];
}
