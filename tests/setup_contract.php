<?php

declare(strict_types=1);

$setup = file_get_contents(__DIR__ . '/../setup.php');
assert($setup !== false);
assert(str_contains($setup, 'function plugin_version_projecttaskdashboard(): array'), 'plugin version function must remain registered');
assert(str_contains($setup, 'function plugin_projecttaskdashboard_check_prerequisites(): bool'), 'prerequisites hook must remain registered');
assert(str_contains($setup, 'function plugin_projecttaskdashboard_check_config(bool $verbose = false): bool'), 'config hook must remain registered');

echo "setup contract ok\n";
