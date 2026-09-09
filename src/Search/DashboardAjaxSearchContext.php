<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Search;

use Glpi\Exception\Http\AccessDeniedHttpException;
use Project;
use ProjectTask;
use Throwable;

/**
 * Rebuild the dashboard execution context around GLPI's native
 * /ajax/search.php refreshes (sort, pagination, page size, refresh).
 *
 * The browser transports only a project marker. The server validates that
 * project, groups all user criteria, injects the mandatory project criterion,
 * expands the semantic "Mes tâches" marker, isolates the ProjectTask search
 * session and enables the narrow Project-team search workaround for the
 * duration of this one request.
 */
final class DashboardAjaxSearchContext
{
    private bool $active = false;

    public function __construct(
        private readonly DashboardAjaxCriteria $criteriaGuard = new DashboardAjaxCriteria(),
        private readonly MineTaskProvider $mineTasks = new MineTaskProvider(),
        private readonly MineCriteriaExpander $mineExpander = new MineCriteriaExpander(),
        private readonly DashboardSearchSession $dashboardSession = new DashboardSearchSession(),
        private readonly ProjectSearchRightsScope $projectSearchRights = new ProjectSearchRightsScope(),
        private readonly SearchFormPreferenceScope $searchFormPreference = new SearchFormPreferenceScope(),
    ) {
    }

    public function activateFromRequest(array &$request): void
    {
        if ($this->active || !$this->isDashboardResultRequest($request)) {
            return;
        }

        $projectId = filter_var($request['ptd_project_id'] ?? null, FILTER_VALIDATE_INT);
        if (!is_int($projectId) || $projectId <= 0) {
            return;
        }

        $project = new Project();
        if (!$project->getFromDB($projectId) || !$project->canViewItem()) {
            throw new AccessDeniedHttpException();
        }

        $criteria = isset($request['criteria']) && is_array($request['criteria'])
            ? array_values($request['criteria'])
            : [];
        $criteria = $this->mineExpander->expand($criteria, $this->mineTasks->taskIds());

        $request['criteria'] = $this->criteriaGuard->forceProject($criteria, $projectId);
        // Native AJAX refreshes must not persist the execution-only project
        // guard into GLPI's global ProjectTask search criteria.
        $request['usesession'] = 0;

        try {
            $this->dashboardSession->enter();
            $this->searchFormPreference->enter();
            $this->projectSearchRights->enter();
            $this->active = true;
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
        $this->searchFormPreference->leave();
        $this->dashboardSession->leave();
        $this->active = false;
    }

    private function isDashboardResultRequest(array $request): bool
    {
        return ($request['action'] ?? '') === 'display_results'
            && ($request['itemtype'] ?? '') === ProjectTask::class
            && array_key_exists('ptd_project_id', $request);
    }
}
