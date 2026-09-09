<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$expected = [
    $root . '/public/js/projecttaskdashboard.js',
    $root . '/public/css/projecttaskdashboard.css',
];

$missing = array_values(array_filter($expected, static fn(string $path): bool => !is_file($path)));
if ($missing !== []) {
    fwrite(STDERR, "Missing GLPI 11 public assets:\n - " . implode("\n - ", $missing) . "\n");
    exit(1);
}

echo "public assets ok\n";
