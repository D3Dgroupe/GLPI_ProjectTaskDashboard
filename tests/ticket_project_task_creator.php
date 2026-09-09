<?php

declare(strict_types=1);

const READ = 1;
const CREATE = 2;
const UPDATE = 4;

final class Session
{
    public static function getLoginUserID(): int
    {
        return 99;
    }
}

class Project
{
    public array $fields = [
        'id' => 7,
        'is_template' => 0,
        'projectstates_id' => 0,
    ];

    public function getFromDB(int $id): bool
    {
        return $id === 7;
    }

    public function can(int $id, int $right): bool
    {
        return $id === 7 && $right === READ;
    }
}

class ProjectTask
{
    public static array $lastCheck = [];
    public static array $lastAdd = [];

    public static function canCreate(): bool
    {
        return true;
    }

    public function check(int $id, int $right, array $input = []): bool
    {
        self::$lastCheck = [$id, $right, $input];
        return true;
    }

    public function add(array $input): int
    {
        self::$lastAdd = $input;
        return 123;
    }

    public static function getFormURLWithID(int $id): string
    {
        return '/front/projecttask.form.php?id=' . $id;
    }
}

class ProjectTask_Ticket
{
    public static array $lastCheck = [];
    public static array $lastAdd = [];

    public function check(int $id, int $right, array $input = []): bool
    {
        self::$lastCheck = [$id, $right, $input];
        return true;
    }

    public function add(array $input): int
    {
        self::$lastAdd = $input;
        return 456;
    }
}

class ProjectTaskTeam
{
    public static array $lastAdd = [];

    public function add(array $input): int
    {
        self::$lastAdd = $input;
        return 789;
    }
}

class ITILFollowup
{
    public static array $lastCheck = [];
    public static array $lastAdd = [];

    public function check(int $id, int $right, array $input = []): bool
    {
        self::$lastCheck = [$id, $right, $input];
        return true;
    }

    public function add(array $input): int
    {
        self::$lastAdd = $input;
        return 321;
    }
}

class Ticket
{
    public const CLOSED = 6;

    public static array $lastUpdate = [];
    public array $fields = ['id' => 42, 'status' => 2, 'entities_id' => 1];

    public function getFromDB(int $id): bool
    {
        return $id === 42;
    }

    public function can(int $id, int $right): bool
    {
        return $id === 42 && ($right === READ || $right === UPDATE);
    }

    public function update(array $input): bool
    {
        self::$lastUpdate = $input;
        return true;
    }

    public static function getSolvedStatusArray(): array
    {
        return [5];
    }

    public static function getClosedStatusArray(): array
    {
        return [6];
    }
}

function htmlescape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Ticket/ProjectTaskFromTicketCreator.php';

$creator = new \GlpiPlugin\Projecttaskdashboard\Ticket\ProjectTaskFromTicketCreator();
$result = $creator->create(42, 7, 'Refonte écran', '<p>Depuis le ticket</p>', true);

assert($result['task_id'] === 123);
assert($result['ticket_closed'] === true);
assert(ProjectTask::$lastAdd['projects_id'] === 7);
assert(ProjectTask::$lastAdd['projectstates_id'] === 1);
assert(ProjectTask::$lastAdd['name'] === 'Refonte écran');
assert(ProjectTask::$lastAdd['content'] === '<p>Depuis le ticket</p>');
assert(ProjectTask_Ticket::$lastAdd === [
    'projecttasks_id' => 123,
    'tickets_id' => 42,
]);
assert(ProjectTaskTeam::$lastAdd === [
    'projecttasks_id' => 123,
    'itemtype' => 'User',
    'items_id' => 99,
]);
assert(ITILFollowup::$lastAdd['itemtype'] === Ticket::class);
assert(ITILFollowup::$lastAdd['items_id'] === 42);
assert(str_contains(ITILFollowup::$lastAdd['content'], '#123'));
assert(str_contains(ITILFollowup::$lastAdd['content'], 'Refonte écran'));
assert(Ticket::$lastUpdate === ['id' => 42, 'status' => Ticket::CLOSED]);

echo "ticket project task creator ok\n";
