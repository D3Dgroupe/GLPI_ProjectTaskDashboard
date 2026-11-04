<?php

declare(strict_types=1);

// Standalone test (no GLPI): php tests/github_link_renderer.php

require_once __DIR__ . '/../src/Github/GithubLinkRenderer.php';

use GlpiPlugin\Projecttaskdashboard\Github\GithubLinkRenderer as R;

$failures = 0;
$check = static function (string $name, bool $ok) use (&$failures): void {
    if (!$ok) {
        $failures++;
        fwrite(STDERR, "FAIL: $name\n");
    }
};

$issue = ['kind' => 'issue', 'repository' => 'D3Dgroupe/repo', 'number' => 7, 'url' => 'https://github.com/D3Dgroupe/repo/issues/7'];
$repo = ['kind' => 'repository', 'repository' => 'D3Dgroupe/repo', 'number' => null, 'url' => 'https://github.com/D3Dgroupe/repo'];

// issue_cell_empty_without_link
$check('issue_cell_empty_without_link', R::issueCell(null) === '' && R::issueCell([]) === '');
$check('issue_cell_link', R::issueCell($issue)
    === '<a href="https://github.com/D3Dgroupe/repo/issues/7" target="_blank" rel="noopener noreferrer">#7</a>');

// issue_cell_escapes_url_and_number
$evil = ['url' => 'https://github.com/a/b/issues/1"><script>alert(1)</script>', 'number' => '7<script>x</script>'];
$html = R::issueCell($evil);
$check('issue_cell_escapes_url_and_number', !str_contains($html, '<script>') && !str_contains($html, '"><'));
$check('issue_cell_refuses_non_github_url', !str_contains(R::issueCell(['url' => 'javascript:alert(1)', 'number' => 3]), 'href'));
$check('issue_cell_refuses_lookalike_host', !str_contains(R::issueCell(['url' => 'https://github.com.evil.io/x', 'number' => 3]), 'href'));

// project_line_shows_repo_and_links_home_page
$line = R::projectFormLine($repo);
$check('project_line_shows_repo_and_links_home_page',
    str_contains($line, 'Dépôt GitHub') && str_contains($line, 'href="https://github.com/D3Dgroupe/repo"')
    && str_contains($line, '>D3Dgroupe/repo</a>'));
$check('project_line_empty_without_link', str_contains(R::projectFormLine(null), 'Aucun'));
$check('task_line', str_contains(R::taskFormLine($issue), 'Issue GitHub') && str_contains(R::taskFormLine($issue), '#7'));
$check('task_line_empty_without_link', str_contains(R::taskFormLine(null), 'Aucune'));

// lines_use_noopener
foreach ([R::issueCell($issue), R::taskFormLine($issue), R::projectFormLine($repo)] as $i => $h) {
    $check("lines_use_noopener_$i", str_contains($h, 'target="_blank"') && str_contains($h, 'rel="noopener noreferrer"'));
}

// no_html_injection_from_repository_name
$bad = ['kind' => 'repository', 'repository' => '<img src=x onerror=1>/"x', 'url' => 'https://github.com/a/b'];
$check('no_html_injection_from_repository_name',
    !str_contains(R::projectFormLine($bad), '<img') && !str_contains(R::taskFormLine($bad + ['number' => 1]), '<img'));

if ($failures > 0) {
    fwrite(STDERR, "$failures failure(s)\n");
    exit(1);
}
echo "OK\n";
