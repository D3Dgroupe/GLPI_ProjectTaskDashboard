<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Search;

final class DashboardSearchSession extends ScopedProjectTaskSearchSession
{
    public function __construct()
    {
        parent::__construct('dashboard');
    }
}
