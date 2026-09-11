<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Search;

use GlpiPlugin\Projecttaskdashboard\Config;

final class CriteriaTransformer
{
    private const VALID_STATES = [
        Config::STATE_TODO,
        Config::STATE_IN_PROGRESS,
        Config::STATE_CHECK,
        Config::STATE_ON_HOLD,
        Config::STATE_BLOCKED,
        Config::STATE_IDEA,
    ];

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
            if ($this->isMineMarker($criterion)) {
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
            fn(array $criterion): bool => !$this->isMineMarker($criterion)
        ));
    }

    public function mineGroup(): array
    {
        return [
            'field' => Config::FIELD_MINE_MARKER,
            'searchtype' => 'equals',
            'value' => 1,
        ];
    }

    private function isWidgetState(array $criterion): bool
    {
        return !isset($criterion['criteria'])
            && (int) ($criterion['field'] ?? -1) === Config::FIELD_STATE
            && ($criterion['searchtype'] ?? '') === 'equals'
            && in_array((int) ($criterion['value'] ?? -1), self::VALID_STATES, true);
    }

    private function isMineMarker(array $criterion): bool
    {
        return !isset($criterion['criteria'])
            && (int) ($criterion['field'] ?? -1) === Config::FIELD_MINE_MARKER
            && ($criterion['searchtype'] ?? '') === 'equals'
            && (int) ($criterion['value'] ?? 0) === 1;
    }
}
