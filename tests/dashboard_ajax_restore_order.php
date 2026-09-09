<?php

declare(strict_types=1);

$ctx = file_get_contents(__DIR__ . '/../src/Search/DashboardAjaxSearchContext.php');
assert($ctx !== false);
$activePos = strpos($ctx, '$this->active = true;');
$enterPos = strpos($ctx, '$this->dashboardSession->enter();');
assert($activePos !== false && $enterPos !== false);
assert($activePos < $enterPos, 'dashboard AJAX context must become restorable before entering temporary scopes');
assert(str_contains($ctx, 'catch (Throwable $e)'));
assert(str_contains($ctx, '$this->restore();'));

echo "dashboard ajax restore order ok\n";
