<?php

declare(strict_types=1);

$setup = file_get_contents(__DIR__ . '/../setup.php');
$js = @file_get_contents(__DIR__ . '/../js/ticketprojecttask.js');
$endpoint = @file_get_contents(__DIR__ . '/../front/ticket-project-task-create.php');

assert($setup !== false);
assert($js !== false, 'ticket project task javascript must exist');
assert($endpoint !== false, 'ticket project task endpoint must exist');
assert(str_contains($setup, "js/ticketprojecttask.js"));
assert(str_contains($js, 'projecttask_ticket.form.php'));
assert(str_contains($js, 'data-ptd-create-project-task'));
assert(str_contains($js, 'ticket-project-task-create.php'));
assert(str_contains($endpoint, 'ptd_close_ticket'));
assert(str_contains($js, 'ticket_closed'));
assert(str_contains($js, 'payload.warnings'));
assert(str_contains($js, 'bootstrap.Modal'));
assert(str_contains($endpoint, 'Session::checkCSRF'));
assert(str_contains($endpoint, 'ProjectTaskFromTicketCreator'));
assert(str_contains($endpoint, 'Session::getNewCSRFToken'));
assert(str_contains($endpoint, 'Fermer le ticket après création'));
assert(str_contains($endpoint, "'warnings'"));
assert(str_contains($endpoint, 'Session::addMessageAfterRedirect'));
assert(str_contains($endpoint, 'catch (RuntimeException $e)'));
assert(str_contains($endpoint, 'Toolbox::logInFile'));
assert(str_contains($endpoint, 'Une erreur interne est survenue'));

echo "ticket project task ui contract ok\n";
