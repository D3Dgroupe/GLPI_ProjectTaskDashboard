<?php

declare(strict_types=1);

const READ = 1;
const CREATE = 2;
const UPDATE = 4;

final class Session { public static function getLoginUserID(): int { return 99; } }
class Ticket {
    public const CLOSED = 6;
    public array $fields = ['status' => 2];
    public function getFromDB(int $id): bool { return true; }
    public function can(int $id, int $right): bool { return true; }
    public static function getSolvedStatusArray(): array { return [5]; }
    public static function getClosedStatusArray(): array { return [6]; }
    public function update(array $input): bool { return false; }
}
class Project {
    public array $fields = ['is_template' => 0, 'projectstates_id' => 0];
    public function getFromDB(int $id): bool { return true; }
    public function can(int $id, int $right): bool { return true; }
}
class ProjectState { public array $fields = []; public function getFromDB(int $id): bool { return false; } }
class ProjectTask {
    public static function canCreate(): bool { return true; }
    public function check(int $id, int $right, array $input = []): bool { return true; }
    public function add(array $input): int { return 123; }
    public static function getFormURLWithID(int $id): string { return '/task/' . $id; }
}
class ProjectTask_Ticket { public function check(int $id, int $right, array $input = []): bool { return true; } public function add(array $input): int { return 1; } }
class ProjectTaskTeam { public function add(array $input): int { return 0; } }
class ITILFollowup { public function check(int $id, int $right, array $input = []): bool { return true; } public function add(array $input): int { return 0; } }
function htmlescape(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }

require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Ticket/ProjectTaskFromTicketCreator.php';

$creator = new \GlpiPlugin\Projecttaskdashboard\Ticket\ProjectTaskFromTicketCreator();
$result = $creator->create(42, 7, 'Tâche créée', 'Contenu', true);

assert($result['task_id'] === 123);
assert($result['ticket_closed'] === false);
assert(count($result['warnings']) === 3);
assert(str_contains(implode(' ', $result['warnings']), 'affectation'));
assert(str_contains(implode(' ', $result['warnings']), 'suivi'));
assert(str_contains(implode(' ', $result['warnings']), 'fermeture'));

echo "ticket project task creator best effort ok\n";
