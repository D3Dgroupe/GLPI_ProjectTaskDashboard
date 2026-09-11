<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Search;

use Glpi\Search\Provider\SQLProvider;
use Glpi\Search\SearchEngine;
use GlpiPlugin\Projecttaskdashboard\Config;
use Project;
use ProjectTask;
use Search;

final class NativeSearchCounter
{
    public function __construct(
        private readonly ProjectContext $projectContext = new ProjectContext(),
        private readonly CriteriaTransformer $transformer = new CriteriaTransformer(),
        private readonly MineTaskProvider $mineTasks = new MineTaskProvider(),
        private readonly MineCriteriaExpander $mineExpander = new MineCriteriaExpander(),
        private readonly ProjectSearchRightsScope $projectSearchRights = new ProjectSearchRightsScope(),
    ) {
    }

    public function count(Project $project, array $criteria): int
    {
        $criteria = $this->mineExpander->expand(
            array_values($criteria),
            $this->mineTasks->taskIds()
        );

        $executionCriteria = [];
        if ($criteria !== []) {
            $executionCriteria[] = ['criteria' => $criteria];
        }
        $executionCriteria[] = $this->projectContext->criterion($project);

        $params = [
            'criteria' => $executionCriteria,
            'display_type' => Search::HTML_OUTPUT,
            'showmassiveactions' => false,
            'show_pager' => false,
            'list_limit' => 1,
        ];

        return $this->projectSearchRights->run(function () use ($params): int {
            $data = SearchEngine::prepareDataForSearch(ProjectTask::class, $params);
            SQLProvider::constructSQL($data);
            SQLProvider::constructData($data, true);
            return (int) ($data['data']['totalcount'] ?? 0);
        });
    }

    public function counts(Project $project, array $criteria): array
    {
        $baseForStates = $this->transformer->withoutWidgetState($criteria);
        $stateCount = function (int $state) use ($project, $baseForStates): int {
            $candidate = $baseForStates;
            $candidate[] = ['field' => Config::FIELD_STATE, 'searchtype' => 'equals', 'value' => $state];
            return $this->count($project, $candidate);
        };

        $mineCriteria = $this->transformer->withoutMine($criteria);
        $mineCriteria[] = $this->transformer->mineGroup();

        return [
            'todo' => $stateCount(Config::STATE_TODO),
            'in_progress' => $stateCount(Config::STATE_IN_PROGRESS),
            'check' => $stateCount(Config::STATE_CHECK),
            'on_hold' => $stateCount(Config::STATE_ON_HOLD),
            'blocked' => $stateCount(Config::STATE_BLOCKED),
            'idea' => $stateCount(Config::STATE_IDEA),
            'mine' => $this->count($project, $mineCriteria),
        ];
    }
}
