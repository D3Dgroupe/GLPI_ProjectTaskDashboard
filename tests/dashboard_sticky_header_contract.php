<?php

declare(strict_types=1);

$rootCss = file_get_contents(__DIR__ . '/../css/projecttaskdashboard.css');
$publicCss = file_get_contents(__DIR__ . '/../public/css/projecttaskdashboard.css');
$template = file_get_contents(__DIR__ . '/../templates/dashboard/header.html.twig');

assert($rootCss !== false && $publicCss !== false && $template !== false);
assert($rootCss === $publicCss, 'root/public CSS copies must stay identical');
assert(str_contains($template, 'ptd-dashboard-header'), 'dashboard header class must remain present');
assert(str_contains($rootCss, '.projecttaskdashboard .ptd-dashboard-header'), 'sticky rule must be scoped to dashboard');
assert(str_contains($rootCss, 'position: sticky'), 'dashboard header must stay visible while scrolling');
assert(str_contains($rootCss, 'top: calc(var(--glpi-topbar-height, 79px) + var(--glpi-contextbar-height, 56px));'), 'sticky header must stay below GLPI fixed bars');
assert(str_contains($rootCss, 'var(--glpi-zindex-sticky'), 'sticky header must use GLPI sticky z-index');
assert(str_contains($rootCss, 'var(--tblr-body-bg)'), 'sticky header must remain readable over scrolled content');
assert(str_contains($rootCss, 'border-bottom:'), 'sticky header needs visual separation while pinned');

echo "dashboard sticky header contract ok\n";
