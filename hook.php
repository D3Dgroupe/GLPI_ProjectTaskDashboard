<?php

declare(strict_types=1);

/**
 * Install and upgrade (GLPI runs this again when the version changes): creates
 * the GitHub links table if absent; existing settings are left untouched.
 */
function plugin_projecttaskdashboard_install(): bool
{
    global $DB;

    if (!$DB->tableExists(\GlpiPlugin\Projecttaskdashboard\Github\GithubLink::TABLE)) {
        $DB->doQuery(\GlpiPlugin\Projecttaskdashboard\Github\GithubLink::getCreateTableSql());
    }
    plugin_projecttaskdashboard_reset_api_routes_cache();
    return true;
}

/**
 * GLPI 11 caches the High-Level API route table under the `hlapi_routes` key for 5 minutes and does not
 * reset it when a plugin is (de)activated: without this, the plugin routes answer 404 for up to 5 minutes
 * after activation and 500 (stale route to an unloaded controller) after deactivation.
 */
function plugin_projecttaskdashboard_reset_api_routes_cache(): void
{
    global $GLPI_CACHE;

    if (is_object($GLPI_CACHE) && method_exists($GLPI_CACHE, 'delete')) {
        $GLPI_CACHE->delete('hlapi_routes');
    }
}

function plugin_projecttaskdashboard_activate(): void
{
    plugin_projecttaskdashboard_reset_api_routes_cache();
}

function plugin_projecttaskdashboard_deactivate(): void
{
    plugin_projecttaskdashboard_reset_api_routes_cache();
}

function plugin_projecttaskdashboard_uninstall(): bool
{
    global $DB;

    (new \GlpiPlugin\Projecttaskdashboard\Project\ProjectMenuSettings())->deleteAll();
    $DB->doQuery('DROP TABLE IF EXISTS `' . \GlpiPlugin\Projecttaskdashboard\Github\GithubLink::TABLE . '`');
    plugin_projecttaskdashboard_reset_api_routes_cache();
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

/** Purge (not trash) of a ProjectTask or Project removes its GitHub link. */
function plugin_projecttaskdashboard_githublink_purged($item): void
{
    if ($item instanceof ProjectTask || $item instanceof Project) {
        \GlpiPlugin\Projecttaskdashboard\Github\GithubLink::deleteForItem($item::class, (int) $item->getID());
    }
}

function plugin_projecttaskdashboard_project_purged($item): void
{
    plugin_projecttaskdashboard_githublink_purged($item);
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
