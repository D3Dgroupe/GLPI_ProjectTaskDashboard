<?php

declare(strict_types=1);

/**
 * End-to-end tests of the plugin's API v2 routes against the disposable GLPI (dev/up.sh).
 *
 *   php tests/e2e/github_link_api.php
 *
 * Reads dev/.env.test, gets OAuth tokens for ptd_tech (update rights) and ptd_ro (read only),
 * creates its own project/tasks through the core API v2, runs the checks (one line each) and
 * purges everything it created (the plugin purge hook removes the links). Exit code != 0 on failure.
 * Never point this at production: it only accepts a 127.0.0.1/localhost base URL.
 */

$root = dirname(__DIR__, 2);
$envFile = $root . '/dev/.env.test';
if (!is_file($envFile)) {
    fwrite(STDERR, "dev/.env.test absent : lancer bash dev/up.sh\n");
    exit(2);
}
$env = [];
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if ($line[0] === '#' || !str_contains($line, '=')) {
        continue;
    }
    [$k, $v] = explode('=', $line, 2);
    $env[trim($k)] = trim($v);
}
$api = rtrim($env['GLPI_API_V2_URL'], '/');
if (preg_match('#^http://(127\.0\.0\.1|localhost)(:\d+)?/#', $api . '/') !== 1) {
    fwrite(STDERR, "Refus : GLPI_API_V2_URL n'est pas local ($api)\n");
    exit(2);
}
$base = '/Plugin/Projecttaskdashboard';
preg_match("/PLUGIN_PROJECTTASKDASHBOARD_VERSION = '([^']+)'/", (string) file_get_contents($root . '/setup.php'), $m);
$expectedVersion = $m[1] ?? '?';

/**
 * Runs the curl binary (no shell, ext-curl not required); the request body goes through stdin.
 * @param string[] $headers "Name: value" lines
 * @return array{0: int, 1: string} HTTP status, response body
 */
function curlRun(string $method, string $url, array $headers, ?string $body): array
{
    $cmd = ['curl', '-sS', '-X', $method, '--max-time', '30', '-w', "\n%{http_code}", $url];
    foreach ($headers as $h) {
        array_push($cmd, '-H', $h);
    }
    if ($body !== null) {
        array_push($cmd, '--data-binary', '@-');
    }
    $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) {
        throw new RuntimeException('curl introuvable');
    }
    fwrite($pipes[0], (string) $body);
    fclose($pipes[0]);
    $out = (string) stream_get_contents($pipes[1]);
    $err = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($proc) !== 0) {
        throw new RuntimeException("curl $method $url: $err");
    }
    $pos = strrpos($out, "\n");
    return [(int) substr($out, $pos + 1), substr($out, 0, $pos)];
}

/** @return array{status: int, body: mixed, raw: string} */
function http(string $method, string $url, ?string $token = null, ?array $json = null, array $headers = []): array
{
    $h = ['Accept: application/json'];
    if ($token !== null) {
        $h[] = 'Authorization: Bearer ' . $token;
    }
    foreach ($headers as $name => $value) {
        $h[] = "$name: $value";
    }
    if ($json !== null) {
        $h[] = 'Content-Type: application/json';
    }
    [$status, $raw] = curlRun($method, $url, $h, $json === null ? null : json_encode($json, JSON_UNESCAPED_SLASHES));
    return ['status' => $status, 'body' => json_decode($raw, true), 'raw' => $raw];
}

function token(array $env, string $login, string $password): string
{
    [, $raw] = curlRun('POST', $env['GLPI_OAUTH_TOKEN_URL'], ['Content-Type: application/x-www-form-urlencoded'], http_build_query([
        'grant_type' => 'password',
        'client_id' => $env['GLPI_OAUTH_CLIENT_ID'],
        'client_secret' => $env['GLPI_OAUTH_CLIENT_SECRET'],
        'username' => $login,
        'password' => $password,
        'scope' => 'api',
    ]));
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['access_token'])) {
        throw new RuntimeException("Jeton OAuth impossible pour $login");
    }
    return $data['access_token'];
}

final class CheckFailed extends RuntimeException
{
}

function check(bool $cond, string $message, ?array $response = null): void
{
    if (!$cond) {
        throw new CheckFailed($message . ($response !== null ? ' [HTTP ' . $response['status'] . ' ' . substr($response['raw'], 0, 300) . ']' : ''));
    }
}

$tech = token($env, $env['GLPI_TECH_LOGIN'], $env['GLPI_TECH_PASSWORD']);
$ro = token($env, $env['GLPI_RO_LOGIN'], $env['GLPI_RO_PASSWORD']);
// Fixtures (create / trash / purge) go through the admin account: the technical profile is deliberately
// limited to what production grants (see, update), so it cannot create or delete projects and tasks.
$admin = token($env, $env['GLPI_ADMIN_LOGIN'], $env['GLPI_ADMIN_PASSWORD']);
$entityOther = (int) $env['GLPI_ENTITY_OTHER_ID'];
$onlyRoot = ['GLPI-Entity' => '0', 'GLPI-Entity-Recursive' => 'false'];
$onlyOther = ['GLPI-Entity' => (string) $entityOther, 'GLPI-Entity-Recursive' => 'false'];

// ---- Fixtures (own project / tasks, purged at the end) -----------------------------------------
$run = bin2hex(random_bytes(3));
$created = ['tasks' => [], 'projects' => []];
$mk = static function (string $path, array $input) use ($api, $admin, &$created): int {
    $r = http('POST', $api . $path, $admin, $input);
    if ($r['status'] !== 201 || !isset($r['body']['id'])) {
        throw new RuntimeException("Création $path impossible: HTTP {$r['status']} {$r['raw']}");
    }
    $created[$path === '/Project' ? 'projects' : 'tasks'][] = (int) $r['body']['id'];
    return (int) $r['body']['id'];
};
$cleanup = static function () use ($api, $admin, &$created): void {
    foreach ($created['tasks'] as $id) {
        http('DELETE', "$api/Project/Task/$id?force=true", $admin);
    }
    foreach ($created['projects'] as $id) {
        http('DELETE', "$api/Project/$id?force=true", $admin);
    }
};
register_shutdown_function($cleanup);

$project = $mk('/Project', ['name' => "PTD E2E github_link $run", 'entity' => ['id' => 0]]);
$t1 = $mk('/Project/Task', ['name' => "E2E T1 $run", 'project' => ['id' => $project], 'entity' => ['id' => 0]]);
$t2 = $mk('/Project/Task', ['name' => "E2E T2 $run", 'project' => ['id' => $project], 'entity' => ['id' => 0]]);
$t3 = $mk('/Project/Task', ['name' => "E2E T3 $run", 'project' => ['id' => $project], 'entity' => ['id' => 0]]);
$tTrash = $mk('/Project/Task', ['name' => "E2E corbeille $run", 'project' => ['id' => $project], 'entity' => ['id' => 0]]);
$otherProject = (int) $env['GLPI_PROJECT_OTHER_ENTITY_ID'];
$tOther = $mk('/Project/Task', ['name' => "E2E autre entité $run", 'project' => ['id' => $otherProject], 'entity' => ['id' => $entityOther]]);

// Unique GitHub ids per run (re-runnable even if an earlier run was interrupted).
$gid = 7_000_000_000_000 + random_int(0, 999_999) * 1000;
$issue = static fn(int $task, int $githubId, int $number, string $repo = 'octo-org/e2e-repo'): array => [
    'itemtype' => 'ProjectTask', 'items_id' => $task, 'kind' => 'issue', 'github_id' => $githubId,
    'repository' => $repo, 'number' => $number, 'url' => "https://github.com/$repo/issues/$number",
];
$links = static function (string $query, string $token, array $headers = []) use ($api, $base): array {
    $r = http('GET', "$api$base/GithubLink?$query", $token, null, $headers);
    check($r['status'] === 200 && is_array($r['body']) && array_is_list($r['body']), "GET ?$query → liste attendue", $r);
    return $r['body'];
};
$put = static fn(array $body, string $token, array $headers = []): array => http('PUT', "$api$base/GithubLink", $token, $body, $headers);
$itemsOf = static fn(array $rows): array => array_map(static fn(array $l): int => (int) $l['items_id'], $rows);

// ---- Checks ------------------------------------------------------------------------------------
$checks = [];

$checks['status_requires_auth_and_returns_version'] = function () use ($api, $base, $tech, $expectedVersion): void {
    $anon = http('GET', "$api$base/Status");
    check($anon['status'] === 401, 'sans jeton : 401 attendu', $anon);
    $r = http('GET', "$api$base/Status", $tech);
    check($r['status'] === 200, 'statut 200 attendu', $r);
    check($r['body'] === ['plugin' => 'projecttaskdashboard', 'version' => $expectedVersion, 'github_link' => true], 'corps inattendu', $r);
};

$checks['put_creates_then_is_idempotent'] = function () use ($put, $issue, $t1, $gid, $tech, $links): void {
    $body = $issue($t1, $gid + 1, 11);
    $r = $put($body, $tech);
    check($r['status'] === 200 && ($r['body']['created'] ?? null) === true, '1er PUT : created=true', $r);
    foreach (['itemtype', 'items_id', 'kind', 'github_id', 'repository', 'number', 'url'] as $k) {
        check(($r['body'][$k] ?? null) === $body[$k], "champ $k renvoyé", $r);
    }
    foreach (['id', 'date_creation', 'date_mod'] as $k) {
        check(isset($r['body'][$k]), "champ $k présent", $r);
    }
    $again = $put($body, $tech);
    check($again['status'] === 200 && ($again['body']['created'] ?? null) === false, '2e PUT : created=false', $again);
    check($again['body']['id'] === $r['body']['id'], 'même ligne', $again);
    check(count($links("itemtype=ProjectTask&items_id=$t1", $tech)) === 1, 'une seule ligne');
};

$checks['put_updates_url_and_repository_on_rename'] = function () use ($put, $issue, $t1, $gid, $tech, $links, $project): void {
    $before = $links("itemtype=ProjectTask&items_id=$t1", $tech)[0] ?? null;
    check($before !== null, 'lien T1 attendu');
    $r = $put($issue($t1, $gid + 1, 11, 'octo-org/e2e-renamed'), $tech);
    check($r['status'] === 200 && $r['body']['created'] === false, 'PUT renommage : 200 created=false', $r);
    check($r['body']['id'] === $before['id'], 'même id', $r);
    check($r['body']['repository'] === 'octo-org/e2e-renamed', 'dépôt mis à jour', $r);
    check($r['body']['url'] === 'https://github.com/octo-org/e2e-renamed/issues/11', 'url mise à jour', $r);
    // Project <-> repository: rename updates name and URL in the same write.
    $repo = ['itemtype' => 'Project', 'items_id' => $project, 'kind' => 'repository', 'github_id' => $gid + 100,
        'repository' => 'octo-org/e2e-repo', 'url' => 'https://github.com/octo-org/e2e-repo'];
    $p1 = $put($repo, $tech);
    check($p1['status'] === 200 && $p1['body']['created'] === true && $p1['body']['number'] === null, 'lien projet créé', $p1);
    $p2 = $put(['repository' => 'octo-org/e2e-repo2', 'url' => 'https://github.com/octo-org/e2e-repo2'] + $repo, $tech);
    check($p2['status'] === 200 && $p2['body']['created'] === false && $p2['body']['id'] === $p1['body']['id'], 'lien projet mis à jour', $p2);
    check($p2['body']['repository'] === 'octo-org/e2e-repo2' && $p2['body']['url'] === 'https://github.com/octo-org/e2e-repo2', 'dépôt projet renommé', $p2);
};

$checks['get_by_item_and_by_project_id_and_by_github_id'] = function () use ($put, $issue, $t1, $t2, $t3, $gid, $tech, $links, $itemsOf, $project, $api, $base): void {
    $r = $put($issue($t2, $gid + 2, 12), $tech);
    check($r['status'] === 200, 'lien T2', $r);
    $one = $links("itemtype=ProjectTask&items_id=$t1", $tech);
    check($itemsOf($one) === [$t1], 'filtre itemtype+items_id');
    check(array_keys($one[0]) === ['id', 'itemtype', 'items_id', 'kind', 'github_id', 'repository', 'number', 'url', 'date_creation', 'date_mod'], 'forme du lien : ' . implode(',', array_keys($one[0])));
    $many = $itemsOf($links("itemtype=ProjectTask&items_ids=$t1,$t2,$t3", $tech));
    sort($many);
    check($many === [$t1, $t2], 'filtre items_ids');
    $byProject = $itemsOf($links("project_id=$project", $tech));
    sort($byProject);
    check($byProject === [$t1, $t2], 'filtre project_id : ' . json_encode($byProject));
    $byGithub = $links('kind=issue&github_id=' . ($gid + 2), $tech);
    check($itemsOf($byGithub) === [$t2] && $byGithub[0]['github_id'] === $gid + 2, 'filtre kind+github_id');
    $byRepo = $links('kind=repository&github_id=' . ($gid + 100), $tech);
    check(count($byRepo) === 1 && $byRepo[0]['itemtype'] === 'Project' && $byRepo[0]['items_id'] === $project, 'filtre dépôt');
    check($links('kind=issue&github_id=' . ($gid + 999), $tech) === [], 'inconnu → []');
    $ids1000 = implode(',', range(1, 1000));
    check(is_array($links("itemtype=ProjectTask&items_ids=$ids1000", $tech)), '1000 ids acceptés');
    $tooMany = http('GET', "$api$base/GithubLink?itemtype=ProjectTask&items_ids=$ids1000,1001", $tech);
    check($tooMany['status'] === 400 && ($tooMany['body']['code'] ?? null) === 'too_many_ids', '1001 ids → 400 too_many_ids', $tooMany);
    $none = http('GET', "$api$base/GithubLink", $tech);
    check($none['status'] === 400 && ($none['body']['code'] ?? null) === 'missing_filter', 'sans filtre → 400 missing_filter', $none);
};

$checks['put_conflict_when_github_id_linked_to_other_item'] = function () use ($put, $issue, $t1, $t3, $gid, $tech, $links): void {
    $before = $links("itemtype=ProjectTask&items_id=$t1", $tech);
    $r = $put($issue($t3, $gid + 1, 11, 'octo-org/e2e-renamed'), $tech);
    check($r['status'] === 409 && ($r['body']['code'] ?? null) === 'github_id_already_linked', '409 github_id_already_linked', $r);
    check($links("itemtype=ProjectTask&items_id=$t1", $tech) === $before, 'ligne T1 inchangée');
    check($links("itemtype=ProjectTask&items_id=$t3", $tech) === [], 'aucune ligne pour T3');
    $repoint = $put($issue($t1, $gid + 9, 19), $tech);
    check($repoint['status'] === 409 && ($repoint['body']['code'] ?? null) === 'item_linked_to_other_github_id', '409 item_linked_to_other_github_id', $repoint);
    check($links("itemtype=ProjectTask&items_id=$t1", $tech) === $before, 'ligne T1 toujours inchangée');
};

$checks['put_forbidden_for_user_without_update_right'] = function () use ($put, $issue, $t3, $gid, $ro, $tech, $links): void {
    $r = $put($issue($t3, $gid + 3, 13), $ro);
    check($r['status'] === 403, '403 attendu pour ptd_ro', $r);
    check($links("itemtype=ProjectTask&items_id=$t3", $tech) === [], 'aucune ligne');
    check($links("itemtype=ProjectTask&items_id=$t3", $ro) === [], 'lecture ptd_ro autorisée (liste vide)');
};

$checks['put_forbidden_on_other_entity'] = function () use ($put, $issue, $t3, $tOther, $gid, $tech, $links, $onlyRoot, $onlyOther): void {
    // An item outside the caller's active entities is not viewable: same 404 as a missing item (no oracle).
    $r = $put($issue($tOther, $gid + 5, 15), $tech, $onlyRoot);
    check($r['status'] === 404 && ($r['body']['code'] ?? null) === 'item_not_found', 'tâche d\'« Autre » hors entité active : 404', $r);
    $r2 = $put($issue($t3, $gid + 3, 13), $tech, $onlyOther);
    check($r2['status'] === 404 && ($r2['body']['code'] ?? null) === 'item_not_found', 'tâche racine depuis « Autre » : 404', $r2);
    check($links("itemtype=ProjectTask&items_ids=$t3,$tOther", $tech) === [], 'aucune ligne');
};

$checks['put_403_when_visible_but_not_updatable'] = function () use ($put, $issue, $t3, $gid, $ro, $tech, $links, $api): void {
    $view = http('GET', "$api/Project/Task/$t3", $ro);
    check($view['status'] === 200, 'ptd_ro voit la tâche', $view);
    $r = $put($issue($t3, $gid + 3, 13), $ro);
    check($r['status'] === 403 && ($r['body']['code'] ?? null) === 'forbidden', '403 forbidden', $r);
    check($links("itemtype=ProjectTask&items_id=$t3", $tech) === [], 'aucune ligne');
};

$checks['put_404_when_not_visible'] = function () use ($put, $issue, $tOther, $gid, $tech, $ro, $links, $onlyRoot): void {
    $missing = $put($issue(2147483000, $gid + 7, 17), $tech, $onlyRoot);
    check($missing['status'] === 404, 'objet inexistant : 404', $missing);
    foreach ([[$tech, 'ptd_tech'], [$ro, 'ptd_ro']] as [$token, $who]) {
        $hidden = $put($issue($tOther, $gid + 5, 15), $token, $onlyRoot);
        check($hidden['status'] === 404, "objet existant invisible ($who) : 404", $hidden);
        check($hidden['raw'] === $missing['raw'], "corps identique à un objet inexistant ($who)", $hidden);
    }
    check($links("itemtype=ProjectTask&items_id=$tOther", $tech) === [], 'aucune ligne');
};

$checks['put_rejected_on_trashed_item'] = function () use ($put, $issue, $tTrash, $gid, $tech, $admin, $links, $api): void {
    $del = http('DELETE', "$api/Project/Task/$tTrash", $admin);
    check($del['status'] === 204, 'mise à la corbeille', $del);
    $r = $put($issue($tTrash, $gid + 6, 16), $tech);
    check($r['status'] === 409 && ($r['body']['code'] ?? null) === 'item_in_trash', '409 item_in_trash', $r);
    check($links("itemtype=ProjectTask&items_id=$tTrash", $tech) === [], 'aucune ligne');
};

$checks['put_404_on_missing_item'] = function () use ($put, $issue, $gid, $tech): void {
    $r = $put($issue(2147483000, $gid + 7, 17), $tech);
    check($r['status'] === 404, '404 attendu', $r);
    $p = $put(['itemtype' => 'Project', 'items_id' => 2147483000, 'kind' => 'repository', 'github_id' => $gid + 8,
        'repository' => 'octo-org/x', 'url' => 'https://github.com/octo-org/x'], $tech);
    check($p['status'] === 404, '404 attendu (projet)', $p);
};

$checks['bad_requests_return_validator_codes'] = function () use ($put, $issue, $t3, $gid, $tech, $links): void {
    $ok = $issue($t3, $gid + 3, 13);
    $cases = [
        'invalid_itemtype' => ['itemtype' => 'Ticket'] + $ok,
        'kind_mismatch' => ['kind' => 'repository'] + $ok,
        'invalid_items_id' => ['items_id' => 0] + $ok,
        'invalid_github_id' => ['github_id' => 'abc'] + $ok,
        'invalid_repo' => ['repository' => 'pas un dépôt'] + $ok,
        'invalid_number' => ['number' => null] + $ok,
        'invalid_url' => ['url' => 'http://evil.example/x'] + $ok,
    ];
    foreach ($cases as $code => $body) {
        $r = $put($body, $tech);
        check($r['status'] === 400 && ($r['body']['status'] ?? null) === 'ERROR_BAD_REQUEST' && ($r['body']['code'] ?? null) === $code, "400 $code", $r);
    }
    check($links("itemtype=ProjectTask&items_id=$t3", $tech) === [], 'aucune ligne');
};

$checks['get_hides_links_of_items_not_visible'] = function () use ($put, $issue, $t1, $tOther, $gid, $tech, $ro, $links, $itemsOf, $onlyRoot, $otherProject): void {
    $r = $put($issue($tOther, $gid + 5, 15), $tech);
    check($r['status'] === 200 && $r['body']['created'] === true, 'lien autre entité (entités récursives)', $r);
    $all = $itemsOf($links("itemtype=ProjectTask&items_ids=$t1,$tOther", $tech));
    sort($all);
    check($all === [$t1, $tOther], 'visible avec entités récursives');
    check($itemsOf($links("itemtype=ProjectTask&items_ids=$t1,$tOther", $tech, $onlyRoot)) === [$t1], 'masqué hors entité active');
    check($links('kind=issue&github_id=' . ($gid + 5), $tech, $onlyRoot) === [], 'masqué par github_id');
    check($links("project_id=$otherProject", $tech, $onlyRoot) === [], 'masqué par project_id');
    check(count($links("itemtype=ProjectTask&items_id=$tOther", $ro)) === 1, 'ptd_ro (lecture) le voit');
};

$checks['no_delete_route'] = function () use ($api, $base, $t1, $tech, $links): void {
    foreach (["$api$base/GithubLink?itemtype=ProjectTask&items_id=$t1", "$api$base/GithubLink/1"] as $url) {
        $r = http('DELETE', $url, $tech);
        check(in_array($r['status'], [404, 405], true), "DELETE $url → 404/405", $r);
    }
    check(count($links("itemtype=ProjectTask&items_id=$t1", $tech)) === 1, 'lien toujours présent');
};

$checks['routes_listed_in_doc_json'] = function () use ($api, $base): void {
    $r = http('GET', "$api/doc.json");
    check($r['status'] === 200 && is_array($r['body']), 'doc.json', $r);
    $paths = $r['body']['paths'] ?? [];
    check(isset($paths["$base/Status"]['get']), "$base/Status (get) documenté");
    check(isset($paths["$base/GithubLink"]['get'], $paths["$base/GithubLink"]['put']), "$base/GithubLink (get, put) documenté");
    check(!isset($paths["$base/GithubLink"]['delete']), 'aucun delete documenté');
    $schema = $r['body']['components']['schemas']['GithubLink'] ?? null;
    check(is_array($schema), 'schéma GithubLink');
    check(array_keys($schema['properties'] ?? []) === ['id', 'itemtype', 'items_id', 'kind', 'github_id', 'repository', 'number', 'url', 'date_creation', 'date_mod'], 'propriétés du schéma');
    $write = $r['body']['components']['schemas']['GithubLinkWriteResult'] ?? null;
    check(is_array($write), 'schéma GithubLinkWriteResult');
    check(array_keys($write['properties'] ?? []) === array_merge(array_keys($schema['properties']), ['created']), 'GithubLinkWriteResult = champs du lien + created');
    check(($write['properties']['created']['type'] ?? null) === 'boolean', 'created booléen');
    $params = array_column($paths["$base/GithubLink"]['get']['parameters'] ?? [], 'name');
    foreach (['itemtype', 'items_id', 'items_ids', 'kind', 'github_id', 'project_id'] as $p) {
        check(in_array($p, $params, true), "paramètre $p documenté");
    }
    check(isset($paths["$base/GithubLink"]['put']['requestBody']), 'corps du PUT documenté');
};

// ---- Run ---------------------------------------------------------------------------------------
$failures = 0;
foreach ($checks as $name => $fn) {
    try {
        $fn();
        echo "PASS $name\n";
    } catch (Throwable $e) {
        $failures++;
        echo "FAIL $name: {$e->getMessage()}\n";
    }
}

$cleanup();
$leftTasks = $created['tasks'];
$created = ['tasks' => [], 'projects' => []];
// Purge hook check: no link row may survive for the purged items (read through the model in the container).
$compose = $root . '/dev/docker-compose.yml';
$php = 'require "vendor/autoload.php"; (new Glpi\Kernel\Kernel("production"))->boot();'
    . ' $n = count(GlpiPlugin\Projecttaskdashboard\Github\GithubLink::findForItems("ProjectTask", [' . implode(',', $leftTasks) . ']))'
    . ' + (GlpiPlugin\Projecttaskdashboard\Github\GithubLink::findForItem("Project", ' . $project . ') === null ? 0 : 1); echo "LEFT=$n\n";';
$out = shell_exec('docker compose -f ' . escapeshellarg($compose) . ' exec -T -w /var/www/glpi glpi php -r ' . escapeshellarg($php) . ' 2>/dev/null');
if (preg_match('/LEFT=(\d+)/', (string) $out, $mm) === 1) {
    echo ($mm[1] === '0' ? 'PASS' : 'FAIL') . " cleanup_purges_links (restant: {$mm[1]})\n";
    $failures += $mm[1] === '0' ? 0 : 1;
} else {
    echo "SKIP cleanup_purges_links (docker compose indisponible)\n";
}

echo $failures === 0 ? 'OK: ' . count($checks) . " checks\n" : "ECHEC: $failures\n";
exit($failures === 0 ? 0 : 1);
