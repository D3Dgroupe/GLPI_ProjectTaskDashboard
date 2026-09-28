<?php

declare(strict_types=1);

use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Projecttaskdashboard\Search\NativeSearchAdapter;
use GlpiPlugin\Projecttaskdashboard\Search\NativeSearchCounter;
use GlpiPlugin\Projecttaskdashboard\Task\InlineTaskEditor;

require_once dirname(__DIR__, 3) . '/inc/includes.php';

// Issue #21: inline editing of ProjectTask columns from the dashboard tables.
//
// No Session::checkCSRF() here on purpose: GLPI 11's CheckCsrfListener
// already validates the X-Glpi-Csrf-Token header of every AJAX POST, while
// *preserving* the page's shared token. Checking it again here would consume
// that token and break every following inline edit on the same page.

Session::checkCentralAccess();
header('Content-Type: application/json; charset=UTF-8');

$editor = new InlineTaskEditor();

try {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
        $taskId = (int) ($_POST['id'] ?? 0);
        $editor->update($taskId, (string) ($_POST['field'] ?? ''), $_POST['value'] ?? '');
        echo json_encode(['success' => true], JSON_THROW_ON_ERROR);
        exit;
    }

    if (($_GET['action'] ?? '') === 'counts') {
        // Refresh the dashboard widget counters after an inline edit, with
        // the same (session) criteria the dashboard tab itself uses.
        $project = new Project();
        $projectId = (int) ($_GET['project_id'] ?? 0);
        if ($projectId <= 0 || !$project->getFromDB($projectId) || !$project->canViewItem()) {
            throw new AccessDeniedHttpException();
        }
        $params = (new NativeSearchAdapter())->readUserParams([]);
        $counts = (new NativeSearchCounter())->counts($project, $params['criteria'] ?? []);
        echo json_encode(['success' => true, 'counts' => $counts], JSON_THROW_ON_ERROR);
        exit;
    }

    echo json_encode(['success' => true] + $editor->describe((int) ($_GET['id'] ?? 0)), JSON_THROW_ON_ERROR);
} catch (AccessDeniedHttpException $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Vous n\'avez pas le droit de modifier cette tâche.'], JSON_THROW_ON_ERROR);
} catch (RuntimeException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    Toolbox::logInFile('projecttaskdashboard', sprintf("Inline task edit failed: %s\n", $e->getMessage()));
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Une erreur interne est survenue pendant la modification.'], JSON_THROW_ON_ERROR);
}
