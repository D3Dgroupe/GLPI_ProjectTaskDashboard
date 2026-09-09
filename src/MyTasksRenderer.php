<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard;

use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Projecttaskdashboard\Search\MyTasksSearchAdapter;
use ProjectTask;

final class MyTasksRenderer
{
    private const TARGET = '/plugins/projecttaskdashboard/front/mytasks.php';

    public function __construct(private readonly MyTasksSearchAdapter $search = new MyTasksSearchAdapter())
    {
    }

    public function render(): void
    {
        if (!ProjectTask::canView()) {
            throw new AccessDeniedHttpException();
        }

        $userParams = $this->search->readUserParams($_GET);
        echo '<div class="projecttaskdashboard-mytasks" data-mytasks-target="' . htmlescape(self::TARGET) . '">';
        echo '<div class="d-flex align-items-center mb-3"><h2 class="m-0">👤 Mes tâches</h2></div>';
        $this->search->render($userParams, self::TARGET);
        echo '</div>';
    }
}
