<?php

declare(strict_types=1);

// Issue #21: inline edit of État / Type / % effectué / Date de fin planifiée
// from the dashboard tables, validated server side and saved through
// ProjectTask::update().

namespace Glpi\Exception\Http {
    class AccessDeniedHttpException extends \RuntimeException {}
}

namespace {
    const READ = 1;
    const UPDATE = 2;
    const ERROR = 1;

    class ProjectTask {
        public static array $db = [];
        public static array $updates = [];
        public static bool $canUpdate = true;
        public static bool $updateResult = true;
        public array $fields = [];
        public function getFromDB(int $id): bool {
            if (!isset(self::$db[$id])) { return false; }
            $this->fields = self::$db[$id];
            return true;
        }
        public function can(int $id, int $right): bool { return $right === READ || self::$canUpdate; }
        public function update(array $input): bool { self::$updates[] = $input; return self::$updateResult; }
    }
    class ProjectState {
        public static function getTable(): string { return 'glpi_projectstates'; }
        public function getFromDB(int $id): bool { return in_array($id, [1, 2, 3], true); }
    }
    class ProjectTaskType {
        public static function getTable(): string { return 'glpi_projecttasktypes'; }
        public function getFromDB(int $id): bool { return $id === 7; }
    }
    class Dropdown {
        public static function getDropdownName(string $table, int $id, ...$rest): string { return "{$table}#{$id}"; }
    }
    final class FakeDB {
        public function request(array $query): array {
            return $query['FROM'] === 'glpi_projectstates' ? [['id' => 1], ['id' => 2]] : [['id' => 7]];
        }
    }
    $DB = new FakeDB();

    require __DIR__ . '/../src/Task/InlineTaskEditor.php';

    use Glpi\Exception\Http\AccessDeniedHttpException;
    use GlpiPlugin\Projecttaskdashboard\Task\InlineTaskEditor;

    function expectError(callable $fn, string $needle): void {
        try {
            $fn();
        } catch (RuntimeException $e) {
            assert(str_contains($e->getMessage(), $needle), "unexpected message: {$e->getMessage()}");
            return;
        }
        assert(false, "expected an error containing '{$needle}'");
    }

    $editor = new InlineTaskEditor();
    ProjectTask::$db = [
        10 => ['projectstates_id' => 1, 'projecttasktypes_id' => 0, 'percent_done' => 30, 'plan_start_date' => '2026-09-01 08:00:00', 'plan_end_date' => null, 'auto_percent_done' => 0],
        11 => ['projectstates_id' => 2, 'projecttasktypes_id' => 7, 'percent_done' => 50, 'plan_start_date' => null, 'plan_end_date' => null, 'auto_percent_done' => 1],
    ];

    assert(InlineTaskEditor::FIELDS_BY_SEARCH_OPTION === [12 => 'projectstates_id', 14 => 'projecttasktypes_id', 5 => 'percent_done', 8 => 'plan_end_date']);

    // describe(): raw values, options, editable columns.
    $d = $editor->describe(10);
    assert($d['editable'] === ['projectstates_id', 'projecttasktypes_id', 'percent_done', 'plan_end_date']);
    assert($d['values']['projectstates_id'] === 1 && $d['values']['percent_done'] === 30);
    assert($d['options']['projectstates_id'][0] === ['id' => 0, 'name' => '-----']);
    assert(count($d['options']['projectstates_id']) === 3 && $d['options']['percent_done'][1] === 5);
    assert(!in_array('percent_done', $editor->describe(11)['editable'], true), 'auto % must not be editable');
    ProjectTask::$canUpdate = false;
    assert($editor->describe(10)['editable'] === [], 'read-only users get nothing editable');

    // update(): rights first.
    expectError(fn() => $editor->update(10, 'projectstates_id', '2'), '');
    assert(ProjectTask::$updates === []);
    ProjectTask::$canUpdate = true;
    try { $editor->update(999, 'projectstates_id', '2'); assert(false); } catch (AccessDeniedHttpException $e) {}

    // Valid updates go through ProjectTask::update().
    $editor->update(10, 'projectstates_id', '2');
    $editor->update(10, 'projecttasktypes_id', '');
    $editor->update(10, 'percent_done', '75');
    $editor->update(10, 'plan_end_date', '2026-09-30T18:00');
    $editor->update(10, 'plan_end_date', '');
    assert(ProjectTask::$updates === [
        ['id' => 10, 'projectstates_id' => 2],
        ['id' => 10, 'projecttasktypes_id' => 0],
        ['id' => 10, 'percent_done' => 75],
        ['id' => 10, 'plan_end_date' => '2026-09-30 18:00:00'],
        ['id' => 10, 'plan_end_date' => 'NULL'],
    ]);

    // Invalid input is rejected before touching the task.
    ProjectTask::$updates = [];
    expectError(fn() => $editor->update(10, 'name', 'x'), 'ne peut pas être modifiée');
    expectError(fn() => $editor->update(10, 'projectstates_id', '99'), 'n\'existe pas');
    expectError(fn() => $editor->update(10, 'projectstates_id', 'abc'), 'invalide');
    expectError(fn() => $editor->update(10, 'percent_done', '120'), 'entre 0 et 100');
    expectError(fn() => $editor->update(11, 'percent_done', '60'), 'automatiquement');
    expectError(fn() => $editor->update(10, 'plan_end_date', '31/09/2026'), 'Date invalide');
    expectError(fn() => $editor->update(10, 'plan_end_date', '2026-08-01T10:00'), 'avant la date de début');
    assert(ProjectTask::$updates === []);

    // GLPI refusal: its session error messages are returned, not left behind.
    ProjectTask::$updateResult = false;
    $_SESSION['MESSAGE_AFTER_REDIRECT'][ERROR] = ['<b>Refus</b> métier'];
    expectError(fn() => $editor->update(10, 'projectstates_id', '3'), 'Refus métier');
    assert(!isset($_SESSION['MESSAGE_AFTER_REDIRECT'][ERROR]));

    // Endpoint contract: no second CSRF check (GLPI's kernel already checks the
    // AJAX header while preserving the shared page token).
    $front = file_get_contents(__DIR__ . '/../front/task-inline-edit.php');
    assert($front !== false);
    $code = implode("\n", array_filter(explode("\n", $front), static fn(string $l): bool => !str_starts_with(ltrim($l), '//')));
    assert(!str_contains($code, 'Session::checkCSRF('), 'endpoint must not consume the page CSRF token');
    assert(str_contains($front, 'Session::checkCentralAccess()'));

    echo "inline task editor ok\n";
}
