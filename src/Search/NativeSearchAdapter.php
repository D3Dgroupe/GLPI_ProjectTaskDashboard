<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Search;

use DisplayPreference;
use Glpi\Application\View\TemplateRenderer;
use Glpi\Search\Input\QueryBuilder;
use Glpi\Search\SearchEngine;
use GlpiPlugin\Projecttaskdashboard\Integration\FieldsIntegration;
use Project;
use ProjectTask;
use Session;

final class NativeSearchAdapter
{
    public function __construct(
        private readonly ProjectContext $projectContext = new ProjectContext(),
        private readonly FieldsIntegration $fields = new FieldsIntegration(),
        private readonly MineTaskProvider $mineTasks = new MineTaskProvider(),
        private readonly MineCriteriaExpander $mineExpander = new MineCriteriaExpander(),
        private readonly DashboardSearchSession $dashboardSession = new DashboardSearchSession(),
        private readonly ProjectSearchRightsScope $projectSearchRights = new ProjectSearchRightsScope(),
        private readonly SearchFormPreferenceScope $searchFormPreference = new SearchFormPreferenceScope(),
    ) {
    }

    public function readUserParams(array $request): array
    {
        return $this->dashboardSession->run(
            static fn(): array => QueryBuilder::manageParams(ProjectTask::class, $request, true)
        );
    }

    public function buildExecutionParams(Project $project, array $userParams): array
    {
        $params = $userParams;
        $userCriteria = $this->mineExpander->expand(
            array_values($params['criteria'] ?? []),
            $this->mineTasks->taskIds()
        );
        $params['criteria'] = [];
        if ($userCriteria !== []) {
            $params['criteria'][] = ['criteria' => $userCriteria];
        }
        $params['criteria'][] = $this->projectContext->criterion($project);
        return $params;
    }

    public function defaultColumns(): array
    {
        $priority = $this->fields->resolvePriorityOption();
        $module = $this->fields->resolveModuleOption();
        return array_values(array_filter([
            1, 14, 12, $priority, $module, 5, 8, 11, 13, 87, 88, 19,
        ], static fn($value): bool => $value !== null));
    }

    public function render(Project $project, array $userParams, array $hiddenParams, string $target): void
    {
        $this->dashboardSession->setCriteria($userParams['criteria'] ?? []);

        $this->dashboardSession->run(function () use ($project, $userParams, $hiddenParams, $target): void {
            $this->searchFormPreference->run(function () use ($project, $userParams, $hiddenParams, $target): void {
                $formParams = $userParams;
                $formParams['target'] = $target;
                $formParams['addhidden'] = $hiddenParams + [
                    // GLPI's native Search Table sends these hidden values back
                    // to /ajax/search.php on sort, pagination, page-size and refresh.
                    'ptd_project_id' => (int) $project->getID(),
                    // Keep execution-only criteria out of the global ProjectTask
                    // search session during native AJAX refreshes.
                    'usesession' => 0,
                ];

                echo "<div class='search_page row' data-testid='search-page'>";
                TemplateRenderer::getInstance()->display('layout/parts/saved_searches.html.twig', [
                    'itemtype' => ProjectTask::class,
                ]);
                echo "<div class='col search-container' data-glpi-search-container>";
                QueryBuilder::showGenericSearch(ProjectTask::class, $formParams);

                $executionParams = $this->buildExecutionParams($project, $userParams);
                $forcedDisplay = $this->hasDisplayPreferences() ? [] : $this->defaultColumns();
                $this->projectSearchRights->run(function () use ($executionParams, $forcedDisplay): void {
                    SearchEngine::showOutput(ProjectTask::class, $executionParams, $forcedDisplay);
                });
                echo '</div></div>';
            });
        });
    }

    private function hasDisplayPreferences(): bool
    {
        $prefs = DisplayPreference::getForTypeUser(
            ProjectTask::class,
            Session::getLoginUserID(),
            Session::getCurrentInterface()
        );
        return count($prefs) > 0;
    }
}
