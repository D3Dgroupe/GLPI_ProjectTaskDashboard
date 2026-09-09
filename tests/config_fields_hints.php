<?php

declare(strict_types=1);

require __DIR__ . '/../src/Config.php';

use GlpiPlugin\Projecttaskdashboard\Config;

assert(Config::FIELDS_MODULE_HINT_ID === 5);
assert(Config::FIELDS_PRIORITY_HINT_ID === 4);

echo "Fields hint IDs ok\n";
