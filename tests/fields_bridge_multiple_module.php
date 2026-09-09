<?php

declare(strict_types=1);

class ProjectTask {}
class ITILCategory
{
    public static function getForeignKeyField(): string
    {
        return 'itilcategories_id';
    }
}

class PluginFieldsField
{
    public array $fields = [];

    public function getFromDB(int $id): bool
    {
        $data = [
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
                'multiple' => 1,
            ],
        ][$id] ?? null;

        if ($data === null) {
            return false;
        }

        $this->fields = $data;
        return true;
    }
}

class PluginFieldsContainer
{
    public array $fields = [];

    public function getFromDB(int $id): bool
    {
        if ($id !== 9) {
            return false;
        }

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
    public function getFromDB(int $id): bool
    {
        return in_array($id, [1, 2, 3], true);
    }
}

require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Ticket/FieldsBridge.php';

$bridge = new \GlpiPlugin\Projecttaskdashboard\Ticket\FieldsBridge();
$input = $bridge->buildTaskInput(17, 3);

assert($input === [
    'c_id' => 9,
    'plugin_fields_prioritefielddropdowns_id' => 3,
    'itilcategories_id_modulefield' => [17],
]);

echo "multiple module mapping ok\n";
