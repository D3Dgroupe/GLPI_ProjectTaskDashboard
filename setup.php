<?php

declare(strict_types=1);

use Glpi\Plugin\Hooks;
use GlpiPlugin\Projecttaskdashboard\DashboardTab;
use GlpiPlugin\Projecttaskdashboard\Search\DashboardAjaxSearchContext;

const PLUGIN_PROJECTTASKDASHBOARD_VERSION = '0.1.0';
const PLUGIN_PROJECTTASKDASHBOARD_MIN_GLPI_VERSION = '11.0.0';
const PLUGIN_PROJECTTASKDASHBOARD_MAX_GLPI_VERSION = '12.0.0';

function plugin_init_projecttaskdashboard(): void
{
    global $PLUGIN_HOOKS;

    if (!Plugin::isPluginActive('projecttaskdashboard')) {
        return;
    }

    Plugin::registerClass(DashboardTab::class, ['addtabon' => Project::class]);
    $PLUGIN_HOOKS[Hooks::ADD_CSS]['projecttaskdashboard'][] = 'css/projecttaskdashboard.css';
    $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['projecttaskdashboard'][] = 'js/projecttaskdashboard.js';

    // GLPI's Search Table refreshes sort/pagination/page-size directly through
    // /ajax/search.php. Rebuild the dashboard's server-side context around
    // those requests when the transport marker is present.
    if (isset($_REQUEST['ptd_project_id'])) {
        (new DashboardAjaxSearchContext())->activateFromRequest($_REQUEST);
    }
}

function plugin_version_projecttaskdashboard(): array
{
    return [
        'name' => 'Project Task Dashboard',
        'version' => PLUGIN_PROJECTTASKDASHBOARD_VERSION,
        'author' => 'D3D Groupe',
        'license' => 'MIT',
        'homepage' => 'https://github.com/D3Dgroupe/GLPI_ProjectTaskDashboard',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_PROJECTTASKDASHBOARD_MIN_GLPI_VERSION,
                'max' => PLUGIN_PROJECTTASKDASHBOARD_MAX_GLPI_VERSION,
            ],
            'php' => [
                'min' => '8.2.0',
            ],
        ],
    ];
}

function plugin_projecttaskdashboard_check_prerequisites(): bool
{
    return version_compare(PHP_VERSION, '8.2.0', '>=');
}

function plugin_projecttaskdashboard_check_config(bool $verbose = false): bool
{
    return true;
}
