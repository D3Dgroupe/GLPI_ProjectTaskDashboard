<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard;

use Dropdown;
use ProjectState;
use Toolbox;

/**
 * Every ProjectState with its configured color (Configuration > Intitulés >
 * Statuts de projet) and its "État terminé" flag, handed to the JS that
 * paints the État cell — and the whole row for finished states — in the
 * dashboard / "Mes tâches" tables.
 */
final class ProjectStatePalette
{
    /**
     * @return list<array{name: string, bg: string, fg: string, finished: bool}>
     */
    public function all(): array
    {
        global $DB;

        $palette = [];
        foreach ($DB->request(['FROM' => ProjectState::getTable()]) as $row) {
            $bg = $row['color'] ?? null;
            if (!is_string($bg) || $bg === '') {
                continue;
            }
            $palette[] = [
                // Translated like the search engine displays it in the État cell.
                'name' => (string) Dropdown::getDropdownName(ProjectState::getTable(), (int) $row['id'], false, true, false),
                'bg' => $bg,
                'fg' => Toolbox::getFgColor($bg),
                'finished' => (bool) ($row['is_finished'] ?? false),
            ];
        }
        return $palette;
    }

    public function dataAttribute(): string
    {
        return 'data-ptd-state-palette="' . htmlescape(json_encode($this->all(), JSON_THROW_ON_ERROR)) . '"';
    }
}
