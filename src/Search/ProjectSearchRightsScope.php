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
    private bool $active = false;
    private bool $profileExists = false;
    private int $original = 0;

    public function enter(): void
    {
        if ($this->active) {
            return;
        }

        $this->profileExists = isset($_SESSION['glpiactiveprofile'])
            && is_array($_SESSION['glpiactiveprofile'])
            && array_key_exists('project', $_SESSION['glpiactiveprofile']);
        $this->original = $this->profileExists
            ? (int) $_SESSION['glpiactiveprofile']['project']
            : 0;

        $_SESSION['glpiactiveprofile']['project'] = ($this->original | Project::READALL) & ~Project::READMY;
        $this->active = true;
    }

    public function leave(): void
    {
        if (!$this->active) {
            return;
        }

        if ($this->profileExists) {
            $_SESSION['glpiactiveprofile']['project'] = $this->original;
        } else {
            unset($_SESSION['glpiactiveprofile']['project']);
        }

        $this->active = false;
    }

    public function run(callable $callback): mixed
    {
        $owned = !$this->active;
        if ($owned) {
            $this->enter();
        }

        try {
            return $callback();
        } finally {
            if ($owned) {
                $this->leave();
            }
        }
    }
}
