<?php

declare(strict_types=1);

class Project
{
    public int $id = 42;
    public bool $visible = true;
    public function getID(): int { return $this->id; }
    public function canViewItem(): bool { return $this->visible; }
}
class ProjectTask
{
    public static bool $canCreate = true;
    public static function canCreate(): bool { return self::$canCreate; }
    public static function getFormURL(bool $full = true): string { return '/front/projecttask.form.php'; }
}

require __DIR__ . '/../src/Navigation/ProjectTaskCreateLink.php';

use GlpiPlugin\Projecttaskdashboard\Navigation\ProjectTaskCreateLink;

$link = new ProjectTaskCreateLink();
$project = new Project();
assert($link->url($project) === '/front/projecttask.form.php?projects_id=42');
ProjectTask::$canCreate = false;
assert($link->url($project) === null);
ProjectTask::$canCreate = true;
$project->visible = false;
assert($link->url($project) === null);
$project->visible = true;
$project->id = 0;
assert($link->url($project) === null);

echo "project task create link ok\n";
