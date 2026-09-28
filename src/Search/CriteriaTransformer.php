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

    /** "RESTE À FAIRE" widget: every state that still needs work. */
    public const REMAINING_STATES = [
        Config::STATE_TODO,
        Config::STATE_IN_PROGRESS,
        Config::STATE_CHECK,
        Config::STATE_BLOCKED,
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

        if ($action === 'set_remaining') {
            $current = $this->detectWidgetState($criteria)['remaining'];
            $criteria = $this->withoutWidgetState($criteria);
            if (!$current) {
                $criteria[] = $this->remainingGroup();
            }
            return array_values($criteria);
        }

        if ($action === 'set_all') {
            return $this->withoutWidgetState($criteria);
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
        $remaining = false;
        $mine = false;

        foreach ($criteria as $criterion) {
            if ($this->isWidgetState($criterion)) {
                $state = (int) $criterion['value'];
            }
            if ($this->isRemainingGroup($criterion)) {
                $remaining = true;
            }
            if ($this->isMineMarker($criterion)) {
                $mine = true;
            }
        }

        return ['state' => $state, 'remaining' => $remaining, 'mine' => $mine];
    }

    public function withoutWidgetState(array $criteria): array
    {
        return array_values(array_filter(
            $criteria,
            fn(array $criterion): bool => !$this->isWidgetState($criterion)
                && !$this->isRemainingGroup($criterion)
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

    public function remainingGroup(): array
    {
        $group = [];
        foreach (self::REMAINING_STATES as $i => $state) {
            $criterion = ['field' => Config::FIELD_STATE, 'searchtype' => 'equals', 'value' => $state];
            if ($i > 0) {
                $criterion = ['link' => 'OR'] + $criterion;
            }
            $group[] = $criterion;
        }
        return ['link' => 'AND', 'criteria' => $group];
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

    private function isRemainingGroup(array $criterion): bool
    {
        if (!isset($criterion['criteria']) || !is_array($criterion['criteria'])) {
            return false;
        }

        $states = [];
        foreach ($criterion['criteria'] as $sub) {
            if (
                !is_array($sub)
                || isset($sub['criteria'])
                || (int) ($sub['field'] ?? -1) !== Config::FIELD_STATE
                || ($sub['searchtype'] ?? '') !== 'equals'
            ) {
                return false;
            }
            $states[] = (int) ($sub['value'] ?? -1);
        }
        sort($states);
        $expected = self::REMAINING_STATES;
        sort($expected);
        return $states === $expected;
    }
}
