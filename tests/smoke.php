<?php

declare(strict_types=1);

require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Search/CriteriaTransformer.php';

use GlpiPlugin\Projecttaskdashboard\Search\CriteriaTransformer;

$t = new CriteriaTransformer();
$r = $t->applyWidgetAction([], ['ptd_action' => 'set_state', 'ptd_state' => '1']);
assert($r === [['field' => 12, 'searchtype' => 'equals', 'value' => 1]]);
$r = $t->applyWidgetAction($r, ['ptd_action' => 'set_state', 'ptd_state' => '1']);
assert($r === []);
$r = $t->applyWidgetAction([], ['ptd_action' => 'toggle_mine']);
assert($t->detectWidgetState($r) === ['state' => null, 'mine' => true]);
echo "smoke ok\n";
