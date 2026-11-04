<?php

declare(strict_types=1);

// Standalone test (no GLPI): php tests/github_link_validator.php

require_once __DIR__ . '/../src/Github/GithubLinkValidator.php';

use GlpiPlugin\Projecttaskdashboard\Github\GithubLinkValidator;

$failures = 0;
$check = static function (string $name, bool $ok) use (&$failures): void {
    if (!$ok) {
        $failures++;
        fwrite(STDERR, "FAIL: $name\n");
    }
};
$code = static function (array $input): ?string {
    try {
        GithubLinkValidator::validate($input);
    } catch (\InvalidArgumentException $e) {
        return $e->getMessage();
    }
    return null;
};

$issue = [
    'itemtype' => 'ProjectTask', 'items_id' => 12, 'kind' => 'issue', 'github_id' => 123456789,
    'repository' => 'D3Dgroupe/repo', 'number' => 7, 'url' => 'https://github.com/D3Dgroupe/repo/issues/7',
];
$repo = [
    'itemtype' => 'Project', 'items_id' => 3, 'kind' => 'repository', 'github_id' => 99,
    'repository' => 'D3Dgroupe/repo', 'url' => 'https://github.com/D3Dgroupe/repo',
];

foreach (['http://github.com/a/b', 'https://github.com.evil.tld/x', 'javascript:alert(1)', '', 'https://github.com'] as $u) {
    $check("url refused: $u", $code(['url' => $u] + $issue) === 'invalid_url');
}
foreach (['https://github.com@evil.tld', 'HTTPS://github.com/x', ' http://github.com/x', "https://github.com/a b", "https://github.com/a\tb", 'https://github.com/a/b ' . "\n" . 'c'] as $u) {
    $check('url refused (extra): ' . json_encode($u), $code(['url' => $u] + $issue) === 'invalid_url');
}
$check('url padded with spaces is trimmed, not refused', $code(['url' => '  https://github.com/a/b  '] + $issue) === null);
$check('ProjectTask + repository', $code(['kind' => 'repository'] + $issue) === 'kind_mismatch');
$check('Project + issue', $code(['kind' => 'issue', 'number' => 1] + $repo) === 'kind_mismatch');
$check('bad itemtype', $code(['itemtype' => 'Ticket'] + $issue) === 'invalid_itemtype');
$noNumber = $issue;
unset($noNumber['number']);
$check('issue without number', $code($noNumber) === 'invalid_number');
foreach (['1.5', 99999999999999999999, '99999999999999999999', 2147483648, -1, 'x'] as $n) {
    $check('issue number refused: ' . var_export($n, true), $code(['number' => $n] + $issue) === 'invalid_number');
}
$check('issue number 0', $code(['number' => 0] + $issue) === 'invalid_number');
$check('repository with number', $code(['number' => 5] + $repo) === 'invalid_number');
foreach ([0, -5, 'abc', '1.5', '9223372036854775808', 9.5, null, true] as $g) {
    $check('github_id refused: ' . var_export($g, true), $code(['github_id' => $g] + $issue) === 'invalid_github_id');
}
$check('github_id max accepted', $code(['github_id' => '9223372036854775807'] + $issue) === null);
foreach (['norepo', 'a/', '/b', 'a/b/c', 'a b/c'] as $r) {
    $check("repo refused: $r", $code(['repository' => $r] + $issue) === 'invalid_repo');
}
$check('items_id 0', $code(['items_id' => 0] + $issue) !== null);

$out = GithubLinkValidator::validate([
    'itemtype' => ' ProjectTask ', 'items_id' => '12', 'kind' => ' issue ', 'github_id' => ' 123456789 ',
    'repository' => ' D3Dgroupe/repo ', 'number' => '7', 'url' => ' https://github.com/D3Dgroupe/repo/issues/7 ',
]);
$check('normalized', $out === [
    'itemtype' => 'ProjectTask', 'items_id' => 12, 'kind' => 'issue', 'github_id' => 123456789,
    'repository' => 'D3Dgroupe/repo', 'number' => 7, 'url' => 'https://github.com/D3Dgroupe/repo/issues/7',
]);
$out = GithubLinkValidator::validate($repo);
$check('repository normalized', $out['number'] === null && $out['github_id'] === 99);

if ($failures > 0) {
    fwrite(STDERR, "$failures failure(s)\n");
    exit(1);
}
echo "OK\n";
