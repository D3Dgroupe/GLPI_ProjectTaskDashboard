<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Search;

use Glpi\Exception\Http\AccessDeniedHttpException;
use ProjectTask;
use Throwable;

final class MyTasksAjaxSearchContext
{
    private bool $active = false;

    public function __construct(
        private readonly MyTasksScopeProvider $scope = new MyTasksScopeProvider(),
        private readonly MyTasksCriteriaGuard $criteriaGuard = new MyTasksCriteriaGuard(),
        private readonly MineCriteriaExpander $mineExpander = new MineCriteriaExpander(),
        private readonly MyTasksSearchSession $searchSession = new MyTasksSearchSession(),
        private readonly ProjectSearchRightsScope $projectSearchRights = new ProjectSearchRightsScope(),
    ) {
    }

    public function activateFromRequest(array &$request): void
    {
        if ($this->active || !$this->isMyTasksResultRequest($request)) {
            return;
        }

        if (!ProjectTask::canView()) {
            throw new AccessDeniedHttpException();
        }

        $criteria = isset($request['criteria']) && is_array($request['criteria'])
            ? array_values($request['criteria'])
            : [];
        $allowedTaskIds = $this->scope->taskIds();
        $criteria = $this->mineExpander->expand($criteria, $allowedTaskIds);
        $request['criteria'] = $this->criteriaGuard->force($criteria, $allowedTaskIds);
        $request['usesession'] = 0;

        $this->active = true;
        try {
            $this->searchSession->enter();
            $this->projectSearchRights->enter();
            register_shutdown_function([$this, 'restore']);
        } catch (Throwable $e) {
            $this->restore();
            throw $e;
        }
    }

    public function restore(): void
    {
        if (!$this->active) {
            return;
        }

        $this->projectSearchRights->leave();
        $this->searchSession->leave();
        $this->active = false;
    }

    private function isMyTasksResultRequest(array $request): bool
    {
        return ($request['action'] ?? '') === 'display_results'
            && ($request['itemtype'] ?? '') === ProjectTask::class
            && ($request['ptd_scope'] ?? '') === 'mytasks';
    }
}
