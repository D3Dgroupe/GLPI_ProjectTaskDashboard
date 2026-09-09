<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Search;

/**
 * Temporarily force GLPI's full search builder for this dashboard only.
 *
 * This prevents SearchEngine output from rendering a second criteria builder
 * inside the green "Filter by ..." dropdown while keeping the user's global
 * preference untouched after the request finishes.
 */
final class SearchFormPreferenceScope
{
    private bool $active = false;
    private bool $existed = false;
    private mixed $original = null;

    public function enter(): void
    {
        if ($this->active) {
            return;
        }

        $this->existed = array_key_exists('glpishow_search_form', $_SESSION);
        $this->original = $this->existed ? $_SESSION['glpishow_search_form'] : null;
        $_SESSION['glpishow_search_form'] = 1;
        $this->active = true;
    }

    public function leave(): void
    {
        if (!$this->active) {
            return;
        }

        if ($this->existed) {
            $_SESSION['glpishow_search_form'] = $this->original;
        } else {
            unset($_SESSION['glpishow_search_form']);
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
