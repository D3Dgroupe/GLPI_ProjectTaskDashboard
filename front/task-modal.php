<?php

declare(strict_types=1);

use Glpi\Exception\Http\BadRequestHttpException;

require_once dirname(__DIR__, 3) . '/inc/includes.php';

// Issue #22: task form shown in the dashboard modal (iframe).
//
// GLPI's own projecttask.form.php?_in_modal=1 cannot be used for an existing
// task: with _in_modal the form header template does not open the <form> tag
// (open_form is false), so "Enregistrer" / "Mettre à la corbeille" do
// nothing. Here the native form is rendered without _in_modal inside a popup
// header: the <form> is opened, it still posts to projecttask.form.php, whose
// Html::back() brings the iframe back to this page after saving.

Session::checkCentralAccess();

$taskId = (int) ($_GET['id'] ?? 0);
if ($taskId <= 0) {
    throw new BadRequestHttpException();
}

Html::popHeader(ProjectTask::getTypeName(1), '', true);
(new ProjectTask())->showForm($taskId);
Html::popFooter();
