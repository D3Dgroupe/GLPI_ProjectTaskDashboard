<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Search;

use GlpiPlugin\Projecttaskdashboard\Config;

final class CriteriaTransformer
{
    private const VALID_STATES = [Config::STATE_TODO, Config::STATE_IN_PROGRESS, Config::STATE_CHECK];

    public function applyWidgetAction(array $criteria, array $request): array
    {
        $action = (string) ($request['ptd_action'] ?? '');
        if ($action === '') {
            return $criteria;
        }

        if ($action === 'set_state') {
            $state = filter_var($request['ptd_state'] ?? null, FILTER_VALIDATE_INT);
            if (!is_int($state) || !in_array($state, self::VALID_STATES, true)) {
                return $criteria;
            }

            $current = $this->detectWidgetState($criteria)['state'];
            $criteria = $this->withoutWidgetState($criteria);
            if ($current !== $state) {
                $criteria[] = [
                    'field' => Config::FIELD_STATE,
                    'searchtype' => 'equals',
                    'value' => $state,
                ];
            }
            return array_values($criteria);
        }

        if ($action === 'toggle_mine') {
            if ($this->detectWidgetState($criteria)['mine']) {
                return array_values($this->withoutMine($criteria));
            }
            $criteria[] = $this->mineGroup();
            return array_values($criteria);
        }

        return $criteria;
    }

    public function detectWidgetState(array $criteria): array
    {
        $state = null;
        $mine = false;

        foreach ($criteria as $criterion) {
            if ($this->isWidgetState($criterion)) {
                $state = (int) $criterion['value'];
            }
            if ($this->isMineGroup($criterion)) {
                $mine = true;
            }
        }

        return ['state' => $state, 'mine' => $mine];
    }

    public function withoutWidgetState(array $criteria): array
    {
        return array_values(array_filter(
            $criteria,
            fn(array $criterion): bool => !$this->isWidgetState($criterion)
        ));
    }

    public function withoutMine(array $criteria): array
    {
        return array_values(array_filter(
            $criteria,
            fn(array $criterion): bool => !$this->isMineGroup($criterion)
        ));
    }

    public function mineGroup(): array
    {
        return [
            'criteria' => [
                [
                    'field' => Config::FIELD_TEAM_USER,
                    'searchtype' => 'equals',
                    'value' => 'myself',
                ],
                [
                    'link' => 'OR',
                    'field' => Config::FIELD_TEAM_GROUP,
                    'searchtype' => 'equals',
                    'value' => 'mygroups',
                ],
            ],
        ];
    }

    private function isWidgetState(array $criterion): bool
    {
        return !isset($criterion['criteria'])
            && (int) ($criterion['field'] ?? -1) === Config::FIELD_STATE
            && ($criterion['searchtype'] ?? '') === 'equals'
            && in_array((int) ($criterion['value'] ?? -1), self::VALID_STATES, true);
    }

    private function isMineGroup(array $criterion): bool
    {
        $children = $criterion['criteria'] ?? null;
        if (!is_array($children) || count($children) !== 2) {
            return false;
        }

        $first = $children[0] ?? [];
        $second = $children[1] ?? [];

        return (int) ($first['field'] ?? -1) === Config::FIELD_TEAM_USER
            && ($first['searchtype'] ?? '') === 'equals'
            && ($first['value'] ?? null) === 'myself'
            && (int) ($second['field'] ?? -1) === Config::FIELD_TEAM_GROUP
            && ($second['searchtype'] ?? '') === 'equals'
            && ($second['value'] ?? null) === 'mygroups'
            && strtoupper((string) ($second['link'] ?? '')) === 'OR';
    }
}
