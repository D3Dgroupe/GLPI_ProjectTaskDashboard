<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard;

use ProjectState;
use Toolbox;

/**
 * Resolves the dashboard's state widgets (À FAIRE / EN COURS / À CONTRÔLER)
 * to the same color an admin configures on the matching ProjectState in
 * Configuration > Dropdowns > Project statuses — the same color GLPI itself
 * uses to paint ProjectTask cards on the Kanban.
 */
final class WidgetStateColors
{
    /**
     * @param array<string, int|null> $stateIdsByKey widget key => ProjectState id (null for widgets with no single state, e.g. "mine")
     * @return array<string, array{bg: string, fg: string}|null>
     */
    public function forStates(array $stateIdsByKey): array
    {
        $colors = [];
        foreach ($stateIdsByKey as $key => $stateId) {
            $colors[$key] = $stateId === null ? null : $this->colorFor($stateId);
        }
        return $colors;
    }

    /**
     * @return array{bg: string, fg: string}|null
     */
    private function colorFor(int $stateId): ?array
    {
        $state = ProjectState::getById($stateId);
        $bg = ($state !== false) ? ($state->fields['color'] ?? null) : null;
        if (!is_string($bg) || $bg === '') {
            return null;
        }

        return ['bg' => $bg, 'fg' => Toolbox::getFgColor($bg)];
    }
}
