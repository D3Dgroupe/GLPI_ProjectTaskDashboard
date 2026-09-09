<?php

declare(strict_types=1);

$creator = file_get_contents(__DIR__ . '/../src/Ticket/ProjectTaskFromTicketCreator.php');
$endpoint = file_get_contents(__DIR__ . '/../front/ticket-project-task-create.php');

assert($creator !== false);
assert($endpoint !== false);

assert(str_contains($creator, 'FieldsBridge'));
assert(str_contains($creator, "'projecttasktypes_id' => \$projectTaskTypeId"));
assert(str_contains($creator, "['itilcategories_id']"));
assert(str_contains($creator, 'buildTaskInput'));

assert(str_contains($endpoint, 'ptd_projecttasktypes_id'));
assert(str_contains($endpoint, 'ptd_priority'));
assert(str_contains($endpoint, 'ProjectTaskType::class'));
assert(str_contains($endpoint, 'getPriorityDropdownDefinition'));
assert(str_contains($endpoint, "'name' => 'ptd_priority'"));

echo "ticket project task Fields contract ok\n";
