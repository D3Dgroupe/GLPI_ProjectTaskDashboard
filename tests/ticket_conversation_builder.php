<?php

declare(strict_types=1);

namespace Glpi\RichText {
    final class RichText
    {
        public static function getTextFromHtml(string $content, ...$options): string
        {
            $content = str_ireplace(['<br>', '<br/>', '<br />', '</p>'], ["\n", "\n", "\n", "\n"], $content);
            return trim(html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
    }
}

namespace {
    final class FakeDB
    {
        public array $lastRequest = [];

        public function __construct(private array $rows) {}

        public function request(array $criteria): array
        {
            $this->lastRequest = $criteria;
            $where = $criteria['WHERE'];
            $rows = array_values(array_filter($this->rows, static fn(array $row): bool =>
                $row['itemtype'] === $where['itemtype']
                && $row['items_id'] === $where['items_id']
                && $row['is_private'] === $where['is_private']
            ));
            usort($rows, static fn(array $a, array $b): int => [$a['date'], $a['id']] <=> [$b['date'], $b['id']]);
            return $rows;
        }
    }

    class Ticket
    {
        public array $fields = [
            'id' => 42,
            'content' => '<p>Erreur ventilateur<br>Le poste chauffe.</p>',
            'date' => '2026-08-28 09:15:00',
            'users_id_recipient' => 10,
        ];
    }

    class ITILFollowup { public static function getTable(): string { return 'glpi_itilfollowups'; } }
    class Html { public static function convDateTime($date, $format = null, bool $with_seconds = false): ?string { return 'FMT[' . $date . ']'; } }
    function getUserName($id): string { return [10 => 'Mear Nathanaël', 20 => 'Vilches Antony', 30 => 'Navarrete Benjamin'][$id] ?? 'Utilisateur inconnu'; }

    $DB = new FakeDB([
        ['id' => 2, 'itemtype' => Ticket::class, 'items_id' => 42, 'is_private' => 0, 'users_id' => 30, 'date' => '2026-08-28 11:03:00', 'content' => '<p>Commande effectuée.</p>'],
        ['id' => 1, 'itemtype' => Ticket::class, 'items_id' => 42, 'is_private' => 0, 'users_id' => 20, 'date' => '2026-08-28 10:42:00', 'content' => '<p>Démontage obligatoire.<br>Commande du ventilateur.</p>'],
        ['id' => 3, 'itemtype' => Ticket::class, 'items_id' => 42, 'is_private' => 1, 'users_id' => 20, 'date' => '2026-08-28 11:20:00', 'content' => '<p>NOTE PRIVÉE À NE PAS COPIER</p>'],
        ['id' => 4, 'itemtype' => Ticket::class, 'items_id' => 42, 'is_private' => 0, 'users_id' => 20, 'date' => '2026-08-28 11:30:00', 'content' => '<p> </p>'],
    ]);

    require __DIR__ . '/../src/Ticket/TicketConversationBuilder.php';
    $text = (new \GlpiPlugin\Projecttaskdashboard\Ticket\TicketConversationBuilder())->build(new Ticket());

    assert(str_contains($text, 'Demande issue du ticket #42'));
    assert(str_contains($text, 'Mear Nathanaël - FMT[2026-08-28 09:15:00]'));
    assert(str_contains($text, "Erreur ventilateur\nLe poste chauffe."));
    assert(str_contains($text, 'Vilches Antony - FMT[2026-08-28 10:42:00]'));
    assert(str_contains($text, 'Navarrete Benjamin - FMT[2026-08-28 11:03:00]'));
    assert(strpos($text, 'Démontage obligatoire.') < strpos($text, 'Commande effectuée.'));
    assert(!str_contains($text, 'NOTE PRIVÉE À NE PAS COPIER'));
    assert(substr_count($text, 'Vilches Antony - ') === 1);
    assert($DB->lastRequest['WHERE']['is_private'] === 0);
    assert($DB->lastRequest['ORDER'] === ['date ASC', 'id ASC']);

    echo "ticket conversation builder ok\n";
}
