<?php

declare(strict_types=1);

const READ = 1;
const CREATE = 2;
const UPDATE = 4;

final class Session
{
    public static function getLoginUserID(): int { return 99; }
}

class Project
{
    public array $fields = ['id' => 7, 'is_template' => 0, 'projectstates_id' => 0];
    public function getFromDB(int $id): bool { return $id === 7; }
    public function can(int $id, int $right): bool { return $id === 7 && $right === READ; }
}

class ProjectTask
{
    public static array $lastAdd = [];
    public static function canCreate(): bool { return true; }
    public function check(int $id, int $right, array $input = []): bool { return true; }
    public function add(array $input): int { self::$lastAdd = $input; return 123; }
    public static function getFormURLWithID(int $id): string { return '/front/projecttask.form.php?id=' . $id; }
}

class ProjectTaskType
{
    public function getFromDB(int $id): bool { return $id === 6; }
}

class ProjectTask_Ticket
{
    public function check(int $id, int $right, array $input = []): bool { return true; }
    public function add(array $input): int { return 1; }
}

class ProjectTaskTeam
{
    public function add(array $input): int { return 1; }
}

class ITILFollowup
{
    public function check(int $id, int $right, array $input = []): bool { return true; }
    public function add(array $input): int { return 1; }
}

class Ticket
{
    public const CLOSED = 6;
    public array $fields = [
        'id' => 42,
        'status' => 2,
        'entities_id' => 1,
        'itilcategories_id' => 17,
    ];
    public function getFromDB(int $id): bool { return $id === 42; }
    public function can(int $id, int $right): bool { return $id === 42; }
    public function update(array $input): bool { return true; }
    public static function getSolvedStatusArray(): array { return [5]; }
    public static function getClosedStatusArray(): array { return [6]; }
}

class ITILCategory
{
    public static function getForeignKeyField(): string { return 'itilcategories_id'; }
}

class PluginFieldsField
{
    public array $fields = [];
    public function getFromDB(int $id): bool
    {
        $this->fields = match ($id) {
            4 => [
                'id' => 4,
                'name' => 'prioritefield',
                'label' => 'Priorité',
                'type' => 'dropdown',
                'plugin_fields_containers_id' => 9,
                'default_value' => '2',
                'is_active' => 1,
                'mandatory' => 1,
                'multiple' => 0,
            ],
            5 => [
                'id' => 5,
                'name' => 'modulefield',
                'label' => 'Module',
                'type' => 'dropdown-ITILCategory',
                'plugin_fields_containers_id' => 9,
                'default_value' => '0',
                'is_active' => 1,
                'mandatory' => 0,
                'multiple' => 0,
            ],
            default => [],
        };
        return $this->fields !== [];
    }
}

class PluginFieldsContainer
{
    public array $fields = [];
    public function getFromDB(int $id): bool
    {
        if ($id !== 9) { return false; }
        $this->fields = [
            'id' => 9,
            'type' => 'dom',
            'itemtypes' => json_encode([ProjectTask::class]),
            'is_active' => 1,
        ];
        return true;
    }
}

class PluginFieldsDropdown
{
    public static function getClassname(string $fieldName): string
    {
        return 'PluginFields' . ucfirst($fieldName) . 'Dropdown';
    }
}

class PluginFieldsPrioritefieldDropdown
{
    public function getFromDB(int $id): bool { return $id === 3; }
}

function htmlescape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Ticket/FieldsBridge.php';
require __DIR__ . '/../src/Ticket/ProjectTaskFromTicketCreator.php';

$result = (new \GlpiPlugin\Projecttaskdashboard\Ticket\ProjectTaskFromTicketCreator())->create(
    42,
    7,
    'Refonte écran',
    'Contexte',
    false,
    6,
    3
);

assert($result['task_id'] === 123);
assert(ProjectTask::$lastAdd['projecttasktypes_id'] === 6);
assert(ProjectTask::$lastAdd['c_id'] === 9);
assert(ProjectTask::$lastAdd['plugin_fields_prioritefielddropdowns_id'] === 3);
assert(ProjectTask::$lastAdd['itilcategories_id_modulefield'] === 17);

echo "ticket project task creator Fields mapping ok\n";
