<?php

declare(strict_types=1);

require __DIR__ . '/ticket_project_task_creator.php';

Ticket::$lastUpdate = [];
$creator = new \GlpiPlugin\Projecttaskdashboard\Ticket\ProjectTaskFromTicketCreator();
$result = $creator->create(42, 7, 'Sans fermeture', 'Contenu', false);

assert($result['task_id'] === 123);
assert($result['ticket_closed'] === false);
assert(Ticket::$lastUpdate === []);

echo "ticket project task creator without close ok\n";
