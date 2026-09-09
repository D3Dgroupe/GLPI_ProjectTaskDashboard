<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Search/TaskIdCriteriaBuilder.php';
require_once __DIR__ . '/../src/Search/MyTasksCriteriaGuard.php';

use GlpiPlugin\Projecttaskdashboard\Search\MyTasksCriteriaGuard;

$guard = new MyTasksCriteriaGuard();
$user = [
    ['field' => 1, 'searchtype' => 'contains', 'value' => 'ivant'],
    ['link' => 'OR', 'field' => 12, 'searchtype' => 'equals', 'value' => 1],
];
$out = $guard->force($user, [11, 22]);
assert($out[0]['criteria'] === $user);
assert(($out[1]['link'] ?? 'AND') === 'AND');
assert(($out[1]['_hidden'] ?? false) === true);
$closed = $guard->force([], []);
assert(($closed[0]['value'] ?? null) === -1);
assert(($closed[0]['_hidden'] ?? false) === true);

echo "mytasks criteria guard ok\n";
