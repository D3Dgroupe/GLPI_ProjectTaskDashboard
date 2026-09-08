<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard;

use Glpi\Application\View\TemplateRenderer;
use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Projecttaskdashboard\Integration\FieldsIntegration;
use GlpiPlugin\Projecttaskdashboard\Search\CriteriaTransformer;
use GlpiPlugin\Projecttaskdashboard\Search\NativeSearchAdapter;
use GlpiPlugin\Projecttaskdashboard\Search\NativeSearchCounter;
use Project;
use Toolbox;

final class DashboardRenderer
{
    public function __construct(
        private readonly NativeSearchAdapter $search = new NativeSearchAdapter(),
        private readonly NativeSearchCounter $counter = new NativeSearchCounter(),
        private readonly CriteriaTransformer $transformer = new CriteriaTransformer(),
        private readonly FieldsIntegration $fields = new FieldsIntegration(),
    ) {
    }

    public function render(Project $project): void
    {
        if (!$project->canViewItem()) {
            throw new AccessDeniedHttpException();
        }

        $request = $_GET;
        $userParams = $this->search->readUserParams($request);
        $criteria = $userParams['criteria'] ?? [];

        if (($request['ptd_action'] ?? '') === 'set_state') {
            $raw = $request['ptd_state'] ?? null;
            if (!in_array((int) $raw, [Config::STATE_TODO, Config::STATE_IN_PROGRESS, Config::STATE_CHECK], true)) {
                $this->log('Invalid ptd_state request value');
            }
        }

        $criteria = $this->transformer->applyWidgetAction($criteria, $request);
        $userParams['criteria'] = $criteria;
        $this->search->synchronizeUserCriteria($criteria);

        $state = $this->transformer->detectWidgetState($criteria);
        $counts = $this->counter->counts($project, $criteria);

        $forcetab = DashboardTab::class . '$1';
        $target = Project::getFormURLWithID((int) $project->getID());
        $dashboardTarget = $target . (str_contains($target, '?') ? '&' : '?') . 'forcetab=' . rawurlencode($forcetab);

        echo '<div class="projecttaskdashboard" data-dashboard-target="' . htmlescape($dashboardTarget) . '">';
        TemplateRenderer::getInstance()->display('@projecttaskdashboard/dashboard/widgets.html.twig', [
            'project_id' => (int) $project->getID(),
            'forcetab' => $forcetab,
            'target' => $target,
            'counts' => $counts,
            'active' => $state,
            'states' => [
                'todo' => Config::STATE_TODO,
                'in_progress' => Config::STATE_IN_PROGRESS,
                'check' => Config::STATE_CHECK,
            ],
        ]);

        $warnings = $this->integrationWarnings();
        if ($warnings !== []) {
            TemplateRenderer::getInstance()->display('@projecttaskdashboard/dashboard/warning.html.twig', ['warnings' => $warnings]);
        }

        $this->search->render($project, $userParams, [
            'id' => (int) $project->getID(),
            'forcetab' => $forcetab,
        ], $dashboardTarget);
        echo '</div>';
    }

    private function integrationWarnings(): array
    {
        if (!$this->fields->isAvailable()) {
            return [];
        }

        $warnings = [];
        if ($this->fields->resolveModuleOption() === null) {
            $warnings[] = 'Fields active but Module search option unresolved';
        }
        if ($this->fields->resolvePriorityOption() === null) {
            $warnings[] = 'Fields active but Priorité search option unresolved';
        }
        foreach ($warnings as $warning) {
            $this->log($warning);
        }
        return $warnings;
    }

    private function log(string $message): void
    {
        Toolbox::logInFile('projecttaskdashboard', '[' . date('c') . '] ' . $message . PHP_EOL);
    }
}
