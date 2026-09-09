<?php

declare(strict_types=1);

class Session { public static string $interface = 'central'; public static function getCurrentInterface(): string { return self::$interface; } }
class ProjectState { public static function getTable(): string { return 'glpi_projectstates'; } }
class ProjectTask { public static bool $canView = true; public static function canView(): bool { return self::$canView; } }
class Project
{
    public static bool $canView = true;
    public static array $rows = [];
    public array $fields = [];
    public static function canView(): bool { return self::$canView; }
    public static function getIcon(): string { return 'ti ti-progress'; }
    public static function getSearchURL(bool $full = true): string { return '/front/project.php'; }
    public static function getFormURLWithID(int $id): string { return '/front/project.form.php?id=' . $id; }
    public static function getTable(): string { return 'glpi_projects'; }
    public function getFromDB(int $id): bool { if (!isset(self::$rows[$id])) return false; $this->fields = self::$rows[$id]; return true; }
    public function canViewItem(): bool { return (bool) ($this->fields['visible'] ?? false); }
}
class DashboardTabStub {}
class_alias(DashboardTabStub::class, 'GlpiPlugin\\Projecttaskdashboard\\DashboardTab');
final class ProjectMenuFakeDB
{
    public function request(array $query): array
    {
        return [
            ['id' => 1, 'name' => 'Zulu', 'is_finished' => 0],
            ['id' => 2, 'name' => 'Clos', 'is_finished' => 1],
            ['id' => 4, 'name' => 'Alpha', 'is_finished' => null],
        ];
    }
}
$GLOBALS['DB'] = new ProjectMenuFakeDB();
Project::$rows = [
    1 => ['id' => 1, 'visible' => true],
    2 => ['id' => 2, 'visible' => true],
    4 => ['id' => 4, 'visible' => true],
];

require_once __DIR__ . '/../src/Project/ActiveProjectProvider.php';
require_once __DIR__ . '/../src/Navigation/ProjectTabsManager.php';
require_once __DIR__ . '/../src/Navigation/ProjectDashboardUrl.php';
require_once __DIR__ . '/../src/Navigation/ProjectMenuManager.php';

use GlpiPlugin\Projecttaskdashboard\Navigation\ProjectMenuManager;

$out = (new ProjectMenuManager())->redefine(['tools' => ['title' => 'Outils']]);
assert(isset($out['projecttaskdashboard_project']));
$content = $out['projecttaskdashboard_project']['content'];
assert($content['projects']['title'] === '📋 Projets');
assert(array_keys($content) === ['projects', 'project_4', 'project_1', 'mytasks']);
assert(str_contains($content['project_4']['page'], 'id=4'));
assert(str_contains($content['project_4']['page'], 'forcetab='));
assert($content['mytasks']['title'] === '👤 Mes tâches');
assert($content['mytasks']['page'] === '/plugins/projecttaskdashboard/front/mytasks.php');

Session::$interface = 'helpdesk';
$helpdesk = ['helpdesk' => ['title' => 'Assistance']];
assert((new ProjectMenuManager())->redefine($helpdesk) === $helpdesk, 'project sector must not be injected into helpdesk menu');
Session::$interface = 'central';

Project::$canView = false;
$unchanged = ['tools' => ['title' => 'Outils']];
assert((new ProjectMenuManager())->redefine($unchanged) === $unchanged);

echo "project menu contract ok\n";
