<?php

declare(strict_types=1);

$endpoint = file_get_contents(__DIR__ . '/../front/ticket-project-task-create.php');
assert($endpoint !== false);

assert(str_contains($endpoint, 'TicketConversationBuilder'));
assert(str_contains($endpoint, '(new TicketConversationBuilder())->build($ticket)'));
assert(!str_contains($endpoint, "strip_tags((string) (\$ticket->fields['content'] ?? ''))"));
assert(str_contains($endpoint, '><?= htmlescape($ticketContent) ?></textarea>'));

echo "ticket project task conversation ui contract ok\n";
