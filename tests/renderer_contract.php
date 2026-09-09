<?php

declare(strict_types=1);

$renderer = file_get_contents(__DIR__ . '/../src/DashboardRenderer.php');
$adapter = file_get_contents(__DIR__ . '/../src/Search/NativeSearchAdapter.php');

if ($renderer === false || $adapter === false) {
    fwrite(STDERR, "Unable to read dashboard source files\n");
    exit(1);
}

preg_match_all('/\$this->search->([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $renderer, $calls);
preg_match_all('/public function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $adapter, $methods);

$missing = array_values(array_diff(array_unique($calls[1]), array_unique($methods[1])));
if ($missing !== []) {
    fwrite(STDERR, 'DashboardRenderer calls missing NativeSearchAdapter methods: ' . implode(', ', $missing) . PHP_EOL);
    exit(1);
}

echo "renderer contract ok\n";
