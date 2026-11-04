<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Search;

use GlpiPlugin\Projecttaskdashboard\Config;
use ProjectTask;
use Session;

/**
 * Which columns the Pilotage / Mes tâches views force on top of GLPI's display preferences.
 *  - no preferences at all: the view's default column list (which includes the GitHub column);
 *  - only GLPI's global defaults (no personal choice): those columns plus GitHub, so it shows by default;
 *  - personal preferences: nothing forced, the user's choice (including removing GitHub) is respected.
 */
final class DefaultColumnsPolicy
{
    /**
     * @param array<int,int|string> $defaults the view's default columns
     * @return array<int,int|string>
     */
    public function forcedDisplay(array $defaults): array
    {
        global $DB;

        $interface = Session::getCurrentInterface();
        $personal = $DB->request([
            'COUNT' => 'cpt',
            'FROM' => 'glpi_displaypreferences',
            'WHERE' => [
                'itemtype' => ProjectTask::class,
                'interface' => $interface,
                'users_id' => Session::getLoginUserID(),
            ],
        ])->current();
        if ((int) ($personal['cpt'] ?? 0) > 0) {
            return [];
        }

        $any = \DisplayPreference::getForTypeUser(ProjectTask::class, Session::getLoginUserID(), $interface);
        // forcedisplay replaces the preferences: keep GLPI's global default columns and append GitHub.
        return $any === [] ? $defaults : array_values(array_unique([...$any, Config::FIELD_GITHUB]));
    }
}
