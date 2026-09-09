<?php

declare(strict_types=1);

use GlpiPlugin\Projecttaskdashboard\Ticket\ProjectTaskFromTicketCreator;
use GlpiPlugin\Projecttaskdashboard\Ticket\TicketConversationBuilder;

require_once dirname(__DIR__, 3) . '/inc/includes.php';

Session::checkCentralAccess();

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');

    try {
        Session::checkCSRF($_POST);

        $ticketId = (int) ($_POST['ptd_ticket_id'] ?? 0);
        $projectId = (int) ($_POST['ptd_project_id'] ?? 0);
        $name = trim((string) ($_POST['ptd_name'] ?? ''));
        $content = (string) ($_POST['ptd_content'] ?? '');
        $closeTicket = isset($_POST['ptd_close_ticket'])
            && (string) $_POST['ptd_close_ticket'] === '1';

        $result = (new ProjectTaskFromTicketCreator())->create(
            $ticketId,
            $projectId,
            $name,
            $content,
            $closeTicket
        );

        if ($result['warnings'] !== []) {
            Session::addMessageAfterRedirect(
                implode('<br>', array_map('htmlescape', $result['warnings'])),
                false,
                WARNING
            );
        }

        echo json_encode([
            'success' => true,
            'task_id' => $result['task_id'],
            'ticket_closed' => $result['ticket_closed'],
            'warnings' => $result['warnings'],
        ], JSON_THROW_ON_ERROR);
    } catch (RuntimeException $e) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
        ], JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        Toolbox::logInFile(
            'projecttaskdashboard',
            sprintf("Ticket project task creation failed: %s\n", $e->getMessage())
        );
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Une erreur interne est survenue pendant la création de la tâche.',
        ], JSON_THROW_ON_ERROR);
    }
    exit;
}

$ticketId = (int) ($_GET['ticket_id'] ?? 0);
$projectId = (int) ($_GET['project_id'] ?? 0);

$ticket = new Ticket();
$project = new Project();

if (
    $ticketId <= 0
    || $projectId <= 0
    || !$ticket->getFromDB($ticketId)
    || !$ticket->can($ticketId, UPDATE)
    || in_array((int) $ticket->fields['status'], array_merge(
        Ticket::getSolvedStatusArray(),
        Ticket::getClosedStatusArray()
    ), true)
) {
    http_response_code(403);
    echo '<div class="alert alert-danger m-3">Ticket introuvable, fermé ou non modifiable.</div>';
    exit;
}

if (
    !$project->getFromDB($projectId)
    || !$project->can($projectId, READ)
    || (int) ($project->fields['is_template'] ?? 0) === 1
    || !ProjectTask::canCreate()
) {
    http_response_code(403);
    echo '<div class="alert alert-danger m-3">Projet introuvable ou création de tâche non autorisée.</div>';
    exit;
}

$projectStateId = (int) ($project->fields['projectstates_id'] ?? 0);
if ($projectStateId > 0) {
    $projectState = new ProjectState();
    if (
        $projectState->getFromDB($projectStateId)
        && (int) ($projectState->fields['is_finished'] ?? 0) === 1
    ) {
        http_response_code(403);
        echo '<div class="alert alert-danger m-3">Impossible de créer une tâche dans un projet terminé.</div>';
        exit;
    }
}

global $CFG_GLPI;
$action = $CFG_GLPI['root_doc'] . '/plugins/projecttaskdashboard/front/ticket-project-task-create.php';
$ticketName = trim((string) ($ticket->fields['name'] ?? ''));
$projectName = trim((string) ($project->fields['name'] ?? ''));
$ticketContent = (new TicketConversationBuilder())->build($ticket);

$csrf = Session::getNewCSRFToken();
?>
<div class="modal fade" id="ptd-ticket-project-task-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form id="ptd-ticket-project-task-form" method="post" action="<?= htmlescape($action) ?>">
        <div class="modal-header">
          <h5 class="modal-title">Créer une tâche de projet depuis ce ticket</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-danger d-none" data-ptd-create-error></div>
          <input type="hidden" name="ptd_ticket_id" value="<?= $ticketId ?>">
          <input type="hidden" name="ptd_project_id" value="<?= $projectId ?>">
          <input type="hidden" name="_glpi_csrf_token" value="<?= htmlescape($csrf) ?>">

          <div class="mb-3">
            <label class="form-label">Projet</label>
            <input type="text" class="form-control" value="<?= htmlescape($projectName) ?>" readonly>
          </div>

          <div class="mb-3">
            <label class="form-label" for="ptd-project-task-name">Nom de la tâche</label>
            <input
              type="text"
              class="form-control"
              id="ptd-project-task-name"
              name="ptd_name"
              value="<?= htmlescape($ticketName) ?>"
              maxlength="255"
              required
            >
          </div>

          <div class="mb-3">
            <label class="form-label" for="ptd-project-task-content">Description</label>
            <textarea
              class="form-control"
              id="ptd-project-task-content"
              name="ptd_content"
              rows="7"
            ><?= htmlescape($ticketContent) ?></textarea>
          </div>

          <div class="alert alert-info mb-3">
            La tâche sera créée avec l'état <strong>À faire</strong>, affectée à votre utilisateur et liée automatiquement à ce ticket.
            Un suivi avec un lien vers la tâche sera ajouté au ticket.
          </div>

          <div class="form-check">
            <input class="form-check-input" type="checkbox" value="1" id="ptd-close-ticket" name="ptd_close_ticket">
            <label class="form-check-label" for="ptd-close-ticket">
              Fermer le ticket après création de la tâche
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn btn-primary" data-ptd-create-submit>
            <i class="ti ti-plus"></i>
            Créer la tâche
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
