<?php

declare(strict_types=1);

class ProjectTask {}
class Session { public static function getLoginUserID(): int { return 42; } }
class User {}
class Group {}
class ProjectTaskTeam { public static function getTable(): string { return 'glpi_projecttaskteams'; } }
final class MineWorkaroundFakeDB {
    public array $lastRequest = [];
    public function request(array $criteria): array {
        $this->lastRequest = $criteria;
        return [
            ['projecttasks_id' => 11],
            ['projecttasks_id' => 22],
            ['projecttasks_id' => 11],
        ];
    }
}

$GLOBALS['DB'] = new MineWorkaroundFakeDB();
$_SESSION = [
    'glpigroups' => [7, 8],
    'glpisearch' => [
        ProjectTask::class => [
            'criteria' => [['field' => 87, 'searchtype' => 'equals', 'value' => 'myself']],
        ],
    ],
];

require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Search/CriteriaTransformer.php';
require __DIR__ . '/../src/Search/MineTaskProvider.php';
require __DIR__ . '/../src/Search/MineCriteriaExpander.php';
require __DIR__ . '/../src/Search/DashboardSearchSession.php';

use GlpiPlugin\Projecttaskdashboard\Search\CriteriaTransformer;
use GlpiPlugin\Projecttaskdashboard\Search\DashboardSearchSession;
use GlpiPlugin\Projecttaskdashboard\Search\MineCriteriaExpander;
use GlpiPlugin\Projecttaskdashboard\Search\MineTaskProvider;

$transformer = new CriteriaTransformer();
$marker = $transformer->applyWidgetAction([], ['ptd_action' => 'toggle_mine']);
assert($marker === [['field' => 99001, 'searchtype' => 'equals', 'value' => 1]]);

$provider = new MineTaskProvider();
assert($provider->taskIds() === [11, 22]);
assert($GLOBALS['DB']->lastRequest['WHERE']['OR'] === [
    ['itemtype' => User::class, 'items_id' => 42],
    ['itemtype' => Group::class, 'items_id' => [7, 8]],
]);

$expanded = (new MineCriteriaExpander())->expand($marker, [11, 22]);
assert($expanded == [[
    'criteria' => [
        ['field' => 99002, 'searchtype' => 'equals', 'value' => 11],
        ['field' => 99002, 'searchtype' => 'equals', 'value' => 22, 'link' => 'OR'],
    ],
]]);

$session = new DashboardSearchSession();
$seen = $session->run(function (): array {
    $criteria = $_SESSION['glpisearch'][ProjectTask::class]['criteria'] ?? [];
    $_SESSION['glpisearch'][ProjectTask::class]['criteria'] = [
        ['field' => 99001, 'searchtype' => 'equals', 'value' => 1],
    ];
    return $criteria;
});
assert($seen === []);
assert(($_SESSION['glpisearch'][ProjectTask::class]['criteria'][0]['field'] ?? null) === 87);
$seenAgain = $session->run(fn(): array => $_SESSION['glpisearch'][ProjectTask::class]['criteria'] ?? []);
assert(($seenAgain[0]['field'] ?? null) === 99001);

echo "mine workaround ok\n";
