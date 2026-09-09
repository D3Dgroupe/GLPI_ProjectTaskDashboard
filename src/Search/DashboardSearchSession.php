<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Search;

use ProjectTask;

final class DashboardSearchSession
{
    private const ROOT_KEY = 'projecttaskdashboard';
    private const SEARCH_KEY = 'search';
    private const SAVED_SEARCH_KEY = 'loaded_savedsearch';

    private bool $active = false;
    private bool $nativeSearchExists = false;
    private mixed $nativeSearch = null;
    private bool $nativeSavedExists = false;
    private mixed $nativeSaved = null;

    public function enter(): void
    {
        if ($this->active) {
            return;
        }

        $this->nativeSearchExists = isset($_SESSION['glpisearch'])
            && array_key_exists(ProjectTask::class, $_SESSION['glpisearch']);
        $this->nativeSearch = $this->nativeSearchExists
            ? $_SESSION['glpisearch'][ProjectTask::class]
            : null;

        $this->nativeSavedExists = array_key_exists('glpi_loaded_savedsearch', $_SESSION);
        $this->nativeSaved = $this->nativeSavedExists ? $_SESSION['glpi_loaded_savedsearch'] : null;

        $pluginSearchExists = isset($_SESSION[self::ROOT_KEY])
            && array_key_exists(self::SEARCH_KEY, $_SESSION[self::ROOT_KEY]);
        if ($pluginSearchExists) {
            $_SESSION['glpisearch'][ProjectTask::class] = $_SESSION[self::ROOT_KEY][self::SEARCH_KEY];
        } else {
            unset($_SESSION['glpisearch'][ProjectTask::class]);
        }

        $pluginSavedExists = isset($_SESSION[self::ROOT_KEY])
            && array_key_exists(self::SAVED_SEARCH_KEY, $_SESSION[self::ROOT_KEY]);
        if ($pluginSavedExists) {
            $_SESSION['glpi_loaded_savedsearch'] = $_SESSION[self::ROOT_KEY][self::SAVED_SEARCH_KEY];
        } else {
            unset($_SESSION['glpi_loaded_savedsearch']);
        }

        $this->active = true;
    }

    public function leave(): void
    {
        if (!$this->active) {
            return;
        }

        if (isset($_SESSION['glpisearch']) && array_key_exists(ProjectTask::class, $_SESSION['glpisearch'])) {
            $_SESSION[self::ROOT_KEY][self::SEARCH_KEY] = $_SESSION['glpisearch'][ProjectTask::class];
        } else {
            unset($_SESSION[self::ROOT_KEY][self::SEARCH_KEY]);
        }

        if (array_key_exists('glpi_loaded_savedsearch', $_SESSION)) {
            $_SESSION[self::ROOT_KEY][self::SAVED_SEARCH_KEY] = $_SESSION['glpi_loaded_savedsearch'];
        } else {
            unset($_SESSION[self::ROOT_KEY][self::SAVED_SEARCH_KEY]);
        }

        if ($this->nativeSearchExists) {
            $_SESSION['glpisearch'][ProjectTask::class] = $this->nativeSearch;
        } else {
            unset($_SESSION['glpisearch'][ProjectTask::class]);
        }

        if ($this->nativeSavedExists) {
            $_SESSION['glpi_loaded_savedsearch'] = $this->nativeSaved;
        } else {
            unset($_SESSION['glpi_loaded_savedsearch']);
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

    public function setCriteria(array $criteria): void
    {
        $_SESSION[self::ROOT_KEY][self::SEARCH_KEY]['criteria'] = array_values($criteria);
    }
}
