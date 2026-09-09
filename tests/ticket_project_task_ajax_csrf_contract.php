<?php

declare(strict_types=1);

$script = file_get_contents(__DIR__ . '/../js/ticketprojecttask.js');
$publicScript = file_get_contents(__DIR__ . '/../public/js/ticketprojecttask.js');

assert($script !== false);
assert($publicScript !== false);
assert(str_contains($script, "'X-Glpi-Csrf-Token'"));
assert(str_contains($script, '_glpi_csrf_token'));
assert(str_contains($publicScript, "'X-Glpi-Csrf-Token'"));
assert(str_contains($publicScript, '_glpi_csrf_token'));

echo "ticket project task ajax csrf contract ok\n";
