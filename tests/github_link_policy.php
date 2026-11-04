<?php

declare(strict_types=1);

// Standalone test (no GLPI): php tests/github_link_policy.php

require_once __DIR__ . '/../src/Github/GithubLinkPolicy.php';

use GlpiPlugin\Projecttaskdashboard\Github\GithubLinkPolicy as P;

$failures = 0;
$eq = static function (string $name, string $got, string $want) use (&$failures): void {
    if ($got !== $want) {
        $failures++;
        fwrite(STDERR, "FAIL: $name (got $got, want $want)\n");
    }
};

$v = ['itemtype' => 'ProjectTask', 'items_id' => 5, 'kind' => 'issue', 'github_id' => 100,
    'repository' => 'o/r', 'number' => 3, 'url' => 'https://github.com/o/r/issues/3'];
$row = ['itemtype' => 'ProjectTask', 'items_id' => '5', 'kind' => 'issue', 'github_id' => '100',
    'repository' => 'o/r', 'number' => '3', 'url' => 'https://github.com/o/r/issues/3'];

$eq('nothing exists -> create', P::decide(null, null, $v), P::CREATE);
$eq('same item same id unchanged -> noop', P::decide($row, $row, $v), P::NOOP);
$eq('same item same id (no github row lookup) -> noop', P::decide($row, null, $v), P::NOOP);
$eq('new url -> update_metadata', P::decide($row, $row, ['url' => 'https://github.com/o/r/issues/3?x'] + $v), P::UPDATE_METADATA);
$eq('new repository -> update_metadata', P::decide($row, $row, ['repository' => 'o/r2'] + $v), P::UPDATE_METADATA);
$eq('new number -> update_metadata', P::decide($row, $row, ['number' => 4] + $v), P::UPDATE_METADATA);
$eq('item linked to other id -> refused', P::decide($row, null, ['github_id' => 101] + $v), P::ITEM_LINKED_TO_OTHER);
$eq('item linked, other kind -> refused', P::decide($row, null, ['kind' => 'repository'] + $v), P::ITEM_LINKED_TO_OTHER);
$other = ['itemtype' => 'ProjectTask', 'items_id' => 9] + $row;
$eq('other item holds the id, item unlinked', P::decide(null, $other, $v), P::GITHUB_ID_ALREADY_LINKED);
$eq('other item holds the id, item linked elsewhere', P::decide($row, $other, ['github_id' => 101] + $v), P::GITHUB_ID_ALREADY_LINKED);
$otherType = ['itemtype' => 'Project'] + $row;
$eq('same items_id but other itemtype holds the id', P::decide(null, $otherType, $v), P::GITHUB_ID_ALREADY_LINKED);
$eq('repository link without number unchanged', P::decide(
    ['kind' => 'repository', 'github_id' => 7, 'repository' => 'o/r', 'number' => null, 'url' => 'https://github.com/o/r', 'itemtype' => 'Project', 'items_id' => 1],
    null,
    ['itemtype' => 'Project', 'items_id' => 1, 'kind' => 'repository', 'github_id' => 7, 'repository' => 'o/r', 'number' => null, 'url' => 'https://github.com/o/r'],
), P::NOOP);

if ($failures > 0) {
    exit(1);
}
echo "OK\n";
