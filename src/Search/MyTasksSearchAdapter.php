<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Search;

use DisplayPreference;
use Glpi\Application\View\TemplateRenderer;
use Glpi\Search\Input\QueryBuilder;
use Glpi\Search\SearchEngine;
use GlpiPlugin\Projecttaskdashboard\Config;
use GlpiPlugin\Projecttaskdashboard\Integration\FieldsIntegration;
use ProjectTask;
use Session;

final class MyTasksSearchAdapter
{
    public function __construct(
        private readonly FieldsIntegration $fields = new FieldsIntegration(),
        private readonly MyTasksScopeProvider $scope = new MyTasksScopeProvider(),
        private readonly MyTasksCriteriaGuard $criteriaGuard = new MyTasksCriteriaGuard(),
        private readonly MineCriteriaExpander $mineExpander = new MineCriteriaExpander(),
        private readonly MyTasksSearchSession $searchSession = new MyTasksSearchSession(),
        private readonly ProjectSearchRightsScope $projectSearchRights = new ProjectSearchRightsScope(),
    ) {
    }

    public function readUserParams(array $request): array
    {
        return $this->searchSession->run(
            static fn(): array => QueryBuilder::manageParams(ProjectTask::class, $request, true)
        );
    }

    public function buildExecutionParams(array $userParams): array
    {
        $params = $userParams;
        $allowedTaskIds = $this->scope->taskIds();
        $userCriteria = $this->mineExpander->expand(
            array_values($params['criteria'] ?? []),
            $allowedTaskIds
        );
        $params['criteria'] = $this->criteriaGuard->force($userCriteria, $allowedTaskIds);
        return $params;
    }

    public function defaultColumns(): array
    {
        $priority = $this->fields->resolvePriorityOption();
        $module = $this->fields->resolveModuleOption();
        return array_values(array_filter([
            1,
            Config::FIELD_PROJECT,
            14,
            12,
            $priority,
            $module,
            5,
            8,
            11,
            13,
            87,
            88,
            19,
        ], static fn($value): bool => $value !== null));
    }

    public function render(array $userParams, string $target): void
    {
        $this->searchSession->setCriteria($userParams['criteria'] ?? []);

        $this->searchSession->run(function () use ($userParams, $target): void {
            $formParams = $userParams;
            $formParams['target'] = $target;
            $formParams['addhidden'] = [
                'ptd_scope' => 'mytasks',
                'usesession' => 0,
            ];

            echo "<div class='search_page row search-no-forced-height' data-testid='search-page'>";
            TemplateRenderer::getInstance()->display('layout/parts/saved_searches.html.twig', [
                'itemtype' => ProjectTask::class,
            ]);
            echo "<div class='col search-container' data-glpi-search-container>";
            // Match GLPI's own SearchEngine::show(): only render the full inline
            // criteria form when the user's own preference asks for it. Otherwise
            // SearchEngine::showOutput() below renders GLPI's native collapsed
            // "Search" button + dropdown builder on its own (same as Tickets).
            // Rendering both here would show two criteria builders at once.
            if ($_SESSION['glpishow_search_form'] ?? true) {
                QueryBuilder::showGenericSearch(ProjectTask::class, $formParams);
            }

            $executionParams = $this->buildExecutionParams($userParams);
            $forcedDisplay = $this->hasDisplayPreferences() ? [] : $this->defaultColumns();
            $this->projectSearchRights->run(function () use ($executionParams, $forcedDisplay): void {
                SearchEngine::showOutput(ProjectTask::class, $executionParams, $forcedDisplay);
            });
            echo '</div></div>';
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
