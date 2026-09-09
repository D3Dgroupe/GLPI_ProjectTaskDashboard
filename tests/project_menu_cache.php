<?php

declare(strict_types=1);

class Project {}
class ProjectState {}

require __DIR__ . '/../src/Navigation/ProjectMenuCacheInvalidator.php';

use GlpiPlugin\Projecttaskdashboard\Navigation\ProjectMenuCacheInvalidator;

$i = new ProjectMenuCacheInvalidator();
$_SESSION['glpimenu'] = ['x' => 1];
$i->invalidate(new Project());
assert(!isset($_SESSION['glpimenu']));
$_SESSION['glpimenu'] = ['x' => 1];
$i->invalidate(new ProjectState());
assert(!isset($_SESSION['glpimenu']));
$_SESSION['glpimenu'] = ['x' => 1];
$i->invalidate(new stdClass());
assert(isset($_SESSION['glpimenu']));

echo "project menu cache ok\n";
