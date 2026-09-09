<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Search/DashboardAjaxCriteria.php';

use GlpiPlugin\Projecttaskdashboard\Search\DashboardAjaxCriteria;

$guard = new DashboardAjaxCriteria();
$user = [
    ['field' => 'view', 'searchtype' => 'contains', 'value' => 'ivant'],
    ['link' => 'OR', 'field' => 12, 'searchtype' => 'equals', 'value' => 1],
];

$out = $guard->forceProject($user, 42);

assert(count($out) === 2);
assert(isset($out[0]['criteria']) && $out[0]['criteria'] === $user, 'user criteria must be grouped');
assert(($out[1]['field'] ?? null) === 2);
assert(($out[1]['value'] ?? null) === 42);
assert(($out[1]['searchtype'] ?? null) === 'equals');
assert(($out[1]['_hidden'] ?? false) === true, 'project guard must stay hidden');
assert(($out[1]['link'] ?? 'AND') === 'AND', 'project guard must be ANDed');

echo "ajax criteria guard ok\n";
