<?php

declare(strict_types=1);

require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Search/TaskIdCriteriaBuilder.php';

use GlpiPlugin\Projecttaskdashboard\Search\TaskIdCriteriaBuilder;

$b = new TaskIdCriteriaBuilder();
assert($b->criterion([]) === [
    'field' => 99002,
    'searchtype' => 'equals',
    'value' => -1,
]);
assert($b->criterion([11])['value'] === 11);
$many = $b->criterion([11, 22, 11, 0], 'AND', true);
assert(($many['link'] ?? null) === 'AND');
assert(($many['_hidden'] ?? false) === true);
assert(($many['criteria'][0]['value'] ?? null) === 11);
assert(($many['criteria'][1]['value'] ?? null) === 22);
assert(($many['criteria'][1]['link'] ?? null) === 'OR');

echo "task id criteria builder ok\n";
