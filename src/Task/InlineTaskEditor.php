<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Task;

use DateTimeImmutable;
use Glpi\Exception\Http\AccessDeniedHttpException;
use ProjectState;
use ProjectTask;
use ProjectTaskType;
use RuntimeException;

/**
 * Issue #21: edit a few ProjectTask columns straight from the dashboard /
 * "Mes tâches" tables (État, Type, % effectué, Date de fin planifiée).
 * Goes through ProjectTask::update() so GLPI's own rules, rights, history
 * and hooks all apply exactly like from the task form.
 */
final class InlineTaskEditor
{
    /** Search option id (column) => ProjectTask field. */
    public const FIELDS_BY_SEARCH_OPTION = [
        12 => 'projectstates_id',
        14 => 'projecttasktypes_id',
        5 => 'percent_done',
        8 => 'plan_end_date',
    ];

    public const PERCENT_STEP = 5;

    public function describe(int $taskId): array
    {
        $task = $this->load($taskId, READ);
        $canUpdate = $task->can($taskId, UPDATE);

        $editable = [];
        if ($canUpdate) {
            $editable = array_values(self::FIELDS_BY_SEARCH_OPTION);
            if ($this->isAutoPercent($task)) {
                $editable = array_values(array_diff($editable, ['percent_done']));
            }
        }

        return [
            'id' => $taskId,
            'editable' => $editable,
            'auto_percent_done' => $this->isAutoPercent($task),
            'values' => [
                'projectstates_id' => (int) ($task->fields['projectstates_id'] ?? 0),
                'projecttasktypes_id' => (int) ($task->fields['projecttasktypes_id'] ?? 0),
                'percent_done' => (int) ($task->fields['percent_done'] ?? 0),
                'plan_end_date' => $task->fields['plan_end_date'] ?? null,
            ],
            'options' => [
                'projectstates_id' => $this->dropdownOptions(ProjectState::class),
                'projecttasktypes_id' => $this->dropdownOptions(ProjectTaskType::class),
                'percent_done' => range(0, 100, self::PERCENT_STEP),
            ],
        ];
    }

    public function update(int $taskId, string $field, mixed $rawValue): void
    {
        if (!in_array($field, self::FIELDS_BY_SEARCH_OPTION, true)) {
            throw new RuntimeException('Cette colonne ne peut pas être modifiée depuis le tableau.');
        }

        $task = $this->load($taskId, UPDATE);
        $value = $this->normalize($task, $field, $rawValue);

        if (!$task->update(['id' => $taskId, $field => $value])) {
            throw new RuntimeException($this->popErrorMessages() ?? 'GLPI a refusé la modification de la tâche.');
        }
    }

    private function load(int $taskId, int $right): ProjectTask
    {
        $task = new ProjectTask();
        if ($taskId <= 0 || !$task->getFromDB($taskId) || !$task->can($taskId, $right)) {
            throw new AccessDeniedHttpException();
        }
        return $task;
    }

    private function normalize(ProjectTask $task, string $field, mixed $rawValue): mixed
    {
        $raw = is_scalar($rawValue) ? trim((string) $rawValue) : '';

        switch ($field) {
            case 'projectstates_id':
            case 'projecttasktypes_id':
                $id = filter_var($raw === '' ? '0' : $raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
                if (!is_int($id)) {
                    throw new RuntimeException('Valeur invalide.');
                }
                $itemtype = $field === 'projectstates_id' ? ProjectState::class : ProjectTaskType::class;
                if ($id > 0 && (new $itemtype())->getFromDB($id) === false) {
                    throw new RuntimeException('Cette valeur n\'existe pas (ou plus).');
                }
                return $id;

            case 'percent_done':
                if ($this->isAutoPercent($task)) {
                    throw new RuntimeException('Le pourcentage de cette tâche est calculé automatiquement à partir de ses sous-tâches.');
                }
                $percent = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
                if (!is_int($percent)) {
                    throw new RuntimeException('Le pourcentage doit être compris entre 0 et 100.');
                }
                return $percent;

            case 'plan_end_date':
                if ($raw === '') {
                    return 'NULL';
                }
                $date = $this->parseDate($raw);
                $start = $task->fields['plan_start_date'] ?? null;
                if (is_string($start) && $start !== '' && $date < new DateTimeImmutable($start)) {
                    throw new RuntimeException('La date de fin planifiée ne peut pas être avant la date de début planifiée.');
                }
                return $date->format('Y-m-d H:i:s');
        }

        throw new RuntimeException('Colonne inconnue.');
    }

    private function parseDate(string $raw): DateTimeImmutable
    {
        // <input type="datetime-local"> sends "Y-m-d\TH:i" (sometimes with seconds).
        foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s', 'Y-m-d'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $raw);
            if ($date !== false && $date->format($format) === $raw) {
                return $date;
            }
        }
        throw new RuntimeException('Date invalide.');
    }

    private function isAutoPercent(ProjectTask $task): bool
    {
        return (int) ($task->fields['auto_percent_done'] ?? 0) === 1;
    }

    /**
     * @param class-string<ProjectState|ProjectTaskType> $itemtype
     * @return list<array{id: int, name: string}>
     */
    private function dropdownOptions(string $itemtype): array
    {
        global $DB;

        $options = [['id' => 0, 'name' => '-----']];
        foreach ($DB->request(['FROM' => $itemtype::getTable(), 'ORDER' => 'name']) as $row) {
            $options[] = [
                'id' => (int) $row['id'],
                'name' => (string) \Dropdown::getDropdownName($itemtype::getTable(), (int) $row['id'], false, true, false),
            ];
        }
        return $options;
    }

    /**
     * GLPI reports update refusals through session messages; hand them back
     * to the caller instead of leaving them to pop up on the next page load.
     */
    private function popErrorMessages(): ?string
    {
        $messages = $_SESSION['MESSAGE_AFTER_REDIRECT'][ERROR] ?? [];
        unset($_SESSION['MESSAGE_AFTER_REDIRECT'][ERROR]);
        $messages = array_filter(array_map(static fn($m): string => trim(strip_tags((string) $m)), (array) $messages));
        return $messages === [] ? null : implode("\n", $messages);
    }
}
