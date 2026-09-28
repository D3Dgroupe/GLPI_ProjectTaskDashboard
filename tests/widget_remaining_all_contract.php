<?php

declare(strict_types=1);

// Issue #19: two quick-filter widgets next to the per-state ones:
// - RESTE À FAIRE: À FAIRE + EN COURS + À CONTRÔLER + BLOQUÉ / ATTENTE
// - TOUTES: every task (no state filter)
// Both compose with 👤 MES TÂCHES like the per-state widgets do.

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Search/CriteriaTransformer.php';

use GlpiPlugin\Projecttaskdashboard\Config;
use GlpiPlugin\Projecttaskdashboard\Search\CriteriaTransformer;

$t = new CriteriaTransformer();
$keyword = ['field' => 'view', 'searchtype' => 'contains', 'value' => 'foo'];

// RESTE À FAIRE adds a single OR group over the 4 remaining states.
$r = $t->applyWidgetAction([$keyword], ['ptd_action' => 'set_remaining']);
assert(count($r) === 2 && $r[0] === $keyword, 'other user criteria must be kept');
$group = $r[1]['criteria'] ?? null;
assert(is_array($group) && count($group) === 4, 'remaining must be one group of 4 states');
$values = array_map(static fn(array $c): int => (int) $c['value'], $group);
assert($values === [Config::STATE_TODO, Config::STATE_IN_PROGRESS, Config::STATE_CHECK, Config::STATE_BLOCKED]);
foreach ($group as $i => $c) {
    assert($c['field'] === Config::FIELD_STATE && $c['searchtype'] === 'equals');
    if ($i > 0) {
        assert(($c['link'] ?? null) === 'OR', 'remaining states must be ORed together');
    }
}
assert($t->detectWidgetState($r) === ['state' => null, 'remaining' => true, 'mine' => false]);

// Clicking it again toggles it off.
assert($t->applyWidgetAction($r, ['ptd_action' => 'set_remaining']) === [$keyword]);

// A single state replaces the remaining group, and vice versa.
$single = $t->applyWidgetAction($r, ['ptd_action' => 'set_state', 'ptd_state' => (string) Config::STATE_IDEA]);
assert($t->detectWidgetState($single) === ['state' => Config::STATE_IDEA, 'remaining' => false, 'mine' => false]);
$back = $t->applyWidgetAction($single, ['ptd_action' => 'set_remaining']);
assert($t->detectWidgetState($back)['state'] === null && $t->detectWidgetState($back)['remaining'] === true);

// TOUTES clears any state filter but keeps the rest (incl. MES TÂCHES).
$mine = $t->applyWidgetAction($back, ['ptd_action' => 'toggle_mine']);
$all = $t->applyWidgetAction($mine, ['ptd_action' => 'set_all']);
assert($t->detectWidgetState($all) === ['state' => null, 'remaining' => false, 'mine' => true]);
assert($all[0] === $keyword);
$all = $t->applyWidgetAction($single, ['ptd_action' => 'set_all']);
assert($all === [$keyword]);

// withoutWidgetState() (used by the counters) also strips the remaining group.
assert($t->withoutWidgetState($r) === [$keyword]);

// Counters, renderer and template wiring.
$counter = file_get_contents(__DIR__ . '/../src/Search/NativeSearchCounter.php');
$widgetsTemplate = file_get_contents(__DIR__ . '/../templates/dashboard/widgets.html.twig');
$css = file_get_contents(__DIR__ . '/../css/projecttaskdashboard.css');
assert($counter !== false && $widgetsTemplate !== false && $css !== false);
assert(str_contains($counter, "'remaining' =>") && str_contains($counter, "'all' =>"), 'counter must count remaining and all');
assert(str_contains($widgetsTemplate, "{ key: 'remaining', label: '📋 RESTE À FAIRE', action: 'set_remaining'"));
assert(str_contains($widgetsTemplate, "{ key: 'all', label: '🗂️ TOUTES', action: 'set_all'"));
assert(str_contains($css, 'repeat(9, 1fr)'), 'all 9 widgets must fit on one row on wide screens');

echo "widget remaining/all contract ok\n";
