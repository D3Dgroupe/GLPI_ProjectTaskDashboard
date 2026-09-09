<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Ticket;

use Glpi\RichText\RichText;
use Html;
use ITILFollowup;
use Ticket;

final class TicketConversationBuilder
{
    public function build(Ticket $ticket): string
    {
        global $DB;

        $ticketId = (int) ($ticket->fields['id'] ?? 0);
        $parts = [sprintf('Demande issue du ticket #%d', $ticketId)];

        $initialContent = $this->toPlainText((string) ($ticket->fields['content'] ?? ''));
        $initialAuthor = $this->userName((int) ($ticket->fields['users_id_recipient'] ?? 0));
        $initialDate = $this->displayDate((string) ($ticket->fields['date'] ?? $ticket->fields['date_creation'] ?? ''));

        $initialBlock = [
            '--- Demande initiale ---',
            $this->metadataLine($initialAuthor, $initialDate),
        ];
        if ($initialContent !== '') {
            $initialBlock[] = $initialContent;
        }
        $parts[] = implode("\n", $initialBlock);

        $followupBlocks = [];
        foreach ($DB->request([
            'FROM' => ITILFollowup::getTable(),
            'WHERE' => [
                'itemtype' => Ticket::class,
                'items_id' => $ticketId,
                'is_private' => 0,
            ],
            'ORDER' => ['date ASC', 'id ASC'],
        ]) as $followup) {
            $content = $this->toPlainText((string) ($followup['content'] ?? ''));
            if ($content === '') {
                continue;
            }

            $followupBlocks[] = implode("\n", [
                $this->metadataLine(
                    $this->userName((int) ($followup['users_id'] ?? 0)),
                    $this->displayDate((string) ($followup['date'] ?? ''))
                ),
                $content,
            ]);
        }

        if ($followupBlocks !== []) {
            $parts[] = "--- Échanges ---\n\n" . implode("\n\n", $followupBlocks);
        }

        return implode("\n\n", $parts);
    }

    private function toPlainText(string $content): string
    {
        return trim(RichText::getTextFromHtml(
            content: $content,
            keep_presentation: true,
            compact: true,
            encode_output: false,
            preserve_case: true,
            preserve_line_breaks: true
        ));
    }

    private function userName(int $userId): string
    {
        if ($userId <= 0) {
            return 'Utilisateur inconnu';
        }

        $name = trim((string) getUserName($userId));
        return $name !== '' ? $name : 'Utilisateur inconnu';
    }

    private function displayDate(string $date): string
    {
        if ($date === '') {
            return '';
        }

        return trim((string) (Html::convDateTime($date) ?? $date));
    }

    private function metadataLine(string $author, string $date): string
    {
        return $date !== '' ? $author . ' - ' . $date : $author;
    }
}
