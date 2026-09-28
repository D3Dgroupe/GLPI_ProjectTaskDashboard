<?php

declare(strict_types=1);

function plugin_projecttaskdashboard_install(): bool
{
    return true;
}

function plugin_projecttaskdashboard_uninstall(): bool
{
    (new \GlpiPlugin\Projecttaskdashboard\Project\ProjectMenuSettings())->deleteAll();
    return true;
}

function plugin_projecttaskdashboard_redefine_menus(array $menu): array
{
    return (new \GlpiPlugin\Projecttaskdashboard\Navigation\ProjectMenuManager())->redefine($menu);
}

function plugin_projecttaskdashboard_project_menu_changed($item): void
{
    if (!is_object($item)) {
        return;
    }

    (new \GlpiPlugin\Projecttaskdashboard\Navigation\ProjectMenuCacheInvalidator())->invalidate($item);
}

/**
 * Issue #23: emoji / quick-menu fields at the bottom of the project form.
 */
function plugin_projecttaskdashboard_post_item_form(array $params): void
{
    (new \GlpiPlugin\Projecttaskdashboard\Project\ProjectSettingsForm())->render($params);
}

/**
 * Save the project form's emoji / quick-menu fields. PRE_ITEM_UPDATE rather
 * than ITEM_UPDATE: GLPI only fires the latter when a project column
 * changed, so changing just the emoji would be lost.
 */
function plugin_projecttaskdashboard_project_pre_update($item): void
{
    if (!$item instanceof Project || !is_array($item->input) || !$item->canUpdateItem()) {
        return;
    }
    $saved = (new \GlpiPlugin\Projecttaskdashboard\Project\ProjectMenuSettings())
        ->saveFromInput((int) $item->getID(), $item->input);
    if ($saved) {
        (new \GlpiPlugin\Projecttaskdashboard\Navigation\ProjectMenuCacheInvalidator())->invalidate($item);
    }
}

function plugin_projecttaskdashboard_project_added($item): void
{
    if ($item instanceof Project && is_array($item->input)) {
        (new \GlpiPlugin\Projecttaskdashboard\Project\ProjectMenuSettings())
            ->saveFromInput((int) $item->getID(), $item->input);
    }
    plugin_projecttaskdashboard_project_menu_changed($item);
}

function plugin_projecttaskdashboard_project_purged($item): void
{
    if ($item instanceof Project) {
        (new \GlpiPlugin\Projecttaskdashboard\Project\ProjectMenuSettings())->delete((int) $item->getID());
    }
    plugin_projecttaskdashboard_project_menu_changed($item);
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
