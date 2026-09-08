<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Search;

use Project;

/**
 * Run the native ProjectTask Search Engine without its extra global
 * Project-team restriction.
 *
 * The dashboard already checks that the current Project is viewable and
 * injects its ID as a mandatory server-side criterion. This temporary scope
 * only prevents SQLProvider from hiding task rows because the user/group is
 * absent from the Project team. Original profile rights are always restored.
 */
final class ProjectSearchRightsScope
{
    public function run(callable $callback): mixed
    {
        $profileExists = isset($_SESSION['glpiactiveprofile'])
            && is_array($_SESSION['glpiactiveprofile'])
            && array_key_exists('project', $_SESSION['glpiactiveprofile']);
        $original = $profileExists
            ? (int) $_SESSION['glpiactiveprofile']['project']
            : 0;

        $_SESSION['glpiactiveprofile']['project'] = ($original | Project::READALL) & ~Project::READMY;

        try {
            return $callback();
        } finally {
            if ($profileExists) {
                $_SESSION['glpiactiveprofile']['project'] = $original;
            } else {
                unset($_SESSION['glpiactiveprofile']['project']);
            }
        }
    }
}
