<?php

declare(strict_types=1);

class ProjectState
{
    public static function getTable(): string { return 'glpi_projectstates'; }
}

class Project
{
    public static array $rows = [];
    public array $fields = [];

    public static function getTable(): string { return 'glpi_projects'; }

    public function getFromDB(int $id): bool
    {
        if (!isset(self::$rows[$id])) {
            return false;
        }
        $this->fields = self::$rows[$id];
        return true;
    }

    public function canViewItem(): bool
    {
        return (bool) ($this->fields['visible'] ?? false);
    }
}

final class FakeDB
{
    public function request(array $query): array
    {
        return [
            ['id' => 1, 'name' => 'Zulu', 'is_finished' => 0],
            ['id' => 2, 'name' => 'Clos', 'is_finished' => 1],
            ['id' => 3, 'name' => 'Secret', 'is_finished' => 0],
            ['id' => 4, 'name' => 'Alpha', 'is_finished' => null],
        ];
    }
}

$GLOBALS['DB'] = new FakeDB();
Project::$rows = [
    1 => ['id' => 1, 'visible' => true],
    2 => ['id' => 2, 'visible' => true],
    3 => ['id' => 3, 'visible' => false],
    4 => ['id' => 4, 'visible' => true],
];

$file = __DIR__ . '/../src/Project/ActiveProjectProvider.php';
if (is_file($file)) {
    require $file;
}

assert(class_exists('GlpiPlugin\\Projecttaskdashboard\\Project\\ActiveProjectProvider'), 'ActiveProjectProvider must exist');

use GlpiPlugin\Projecttaskdashboard\Project\ActiveProjectProvider;

$p = new ActiveProjectProvider();
assert($p->all() === [
    ['id' => 4, 'name' => 'Alpha'],
    ['id' => 1, 'name' => 'Zulu'],
]);
assert($p->ids() === [4, 1]);

echo "active project provider ok\n";
