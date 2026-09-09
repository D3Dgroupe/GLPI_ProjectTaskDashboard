<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Navigation;

use Project;
use ProjectState;

final class ProjectMenuCacheInvalidator
{
    public function invalidate(object $item): void
    {
        if ($item instanceof Project || $item instanceof ProjectState) {
            unset($_SESSION['glpimenu']);
        }
    }
}
