<?php

declare(strict_types=1);

$setup = file_get_contents(__DIR__ . '/../setup.php');
assert($setup !== false);

foreach ([
    'Hooks::ITEM_ADD',
    'Hooks::ITEM_UPDATE',
    'Hooks::ITEM_DELETE',
    'Hooks::ITEM_PURGE',
    'Hooks::ITEM_RESTORE',
] as $hook) {
    assert(str_contains($setup, $hook), "missing {$hook}");
}

assert(str_contains($setup, "Project::class => 'plugin_projecttaskdashboard_project_menu_changed'"), 'Project lifecycle hook must be class-scoped');
assert(str_contains($setup, "ProjectState::class => 'plugin_projecttaskdashboard_project_menu_changed'"), 'ProjectState lifecycle hook must be class-scoped');

echo "project menu hook registration ok\n";
