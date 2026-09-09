<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Ticket;

use GlpiPlugin\Projecttaskdashboard\Config;
use ITILFollowup;
use Project;
use ProjectTask;
use ProjectState;
use ProjectTask_Ticket;
use ProjectTaskTeam;
use RuntimeException;
use Session;
use Ticket;

final class ProjectTaskFromTicketCreator
{
    /**
     * @return array{task_id:int,linked:bool,ticket_closed:bool,warnings:list<string>}
     */
    public function create(
        int $ticketId,
        int $projectId,
        string $name,
        string $content,
        bool $closeTicket
    ): array {
        $ticket = new Ticket();
        if (!$ticket->getFromDB($ticketId) || !$ticket->can($ticketId, UPDATE)) {
            throw new RuntimeException('Ticket introuvable ou non modifiable.');
        }

        if (in_array((int) $ticket->fields['status'], array_merge(
            Ticket::getSolvedStatusArray(),
            Ticket::getClosedStatusArray()
        ), true)) {
            throw new RuntimeException('Impossible de créer une tâche depuis un ticket résolu ou fermé.');
        }

        $project = new Project();
        if (!$project->getFromDB($projectId) || !$project->can($projectId, READ)) {
            throw new RuntimeException('Projet introuvable ou inaccessible.');
        }

        if ((int) ($project->fields['is_template'] ?? 0) === 1 || !ProjectTask::canCreate()) {
            throw new RuntimeException('Création de tâche non autorisée pour ce projet.');
        }

        $projectStateId = (int) ($project->fields['projectstates_id'] ?? 0);
        if ($projectStateId > 0) {
            $projectState = new ProjectState();
            if ($projectState->getFromDB($projectStateId)
                && (int) ($projectState->fields['is_finished'] ?? 0) === 1
            ) {
                throw new RuntimeException('Impossible de créer une tâche dans un projet terminé.');
            }
        }

        $taskInput = [
            'projects_id' => $projectId,
            'projectstates_id' => Config::STATE_TODO,
            'name' => trim($name),
            'content' => $content,
        ];

        if ($taskInput['name'] === '') {
            throw new RuntimeException('Le nom de la tâche est obligatoire.');
        }

        $task = new ProjectTask();
        $task->check(-1, CREATE, $taskInput);
        $taskId = (int) $task->add($taskInput);
        if ($taskId <= 0) {
            throw new RuntimeException('La création de la tâche de projet a échoué.');
        }

        $warnings = [];
        $linked = false;
        $linkInput = [
            'projecttasks_id' => $taskId,
            'tickets_id' => $ticketId,
        ];
        try {
            $link = new ProjectTask_Ticket();
            $link->check(-1, CREATE, $linkInput);
            $linked = (bool) (int) $link->add($linkInput);
            if (!$linked) {
                $warnings[] = 'La liaison automatique entre le ticket et la tâche a échoué.';
            }
        } catch (\Throwable) {
            $warnings[] = 'La liaison automatique entre le ticket et la tâche a échoué.';
        }

        $userId = (int) Session::getLoginUserID();
        if ($userId > 0) {
            try {
                $team = new ProjectTaskTeam();
                if (!(int) $team->add([
                    'projecttasks_id' => $taskId,
                    'itemtype' => 'User',
                    'items_id' => $userId,
                ])) {
                    $warnings[] = "L'affectation de la tâche à l'utilisateur courant a échoué.";
                }
            } catch (\Throwable) {
                $warnings[] = "L'affectation de la tâche à l'utilisateur courant a échoué.";
            }
        }

        try {
            $taskUrl = ProjectTask::getFormURLWithID($taskId);
            $followupInput = [
                'itemtype' => Ticket::class,
                'items_id' => $ticketId,
                'content' => sprintf(
                    'Demande transférée en tâche projet <a href="%s">#%d - %s</a>.',
                    htmlescape($taskUrl),
                    $taskId,
                    htmlescape($taskInput['name'])
                ),
            ];
            $followup = new ITILFollowup();
            $followup->check(-1, CREATE, $followupInput);
            if (!(int) $followup->add($followupInput)) {
                $warnings[] = "L'ajout du suivi au ticket a échoué.";
            }
        } catch (\Throwable) {
            $warnings[] = "L'ajout du suivi au ticket a échoué.";
        }

        $closed = false;
        if ($closeTicket) {
            try {
                $closed = $ticket->update([
                    'id' => $ticketId,
                    'status' => Ticket::CLOSED,
                ]);
                if (!$closed) {
                    $warnings[] = 'La fermeture du ticket a échoué.';
                }
            } catch (\Throwable) {
                $warnings[] = 'La fermeture du ticket a échoué.';
            }
        }

        return [
            'task_id' => $taskId,
            'linked' => $linked,
            'ticket_closed' => $closed,
            'warnings' => $warnings,
        ];
    }
}
