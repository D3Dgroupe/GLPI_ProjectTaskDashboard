<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Search;

use ProjectTask;

final class DashboardSearchSession
{
    private const ROOT_KEY = 'projecttaskdashboard';
    private const SEARCH_KEY = 'search';
    private const SAVED_SEARCH_KEY = 'loaded_savedsearch';

    public function run(callable $callback): mixed
    {
        $nativeSearchExists = isset($_SESSION['glpisearch'])
            && array_key_exists(ProjectTask::class, $_SESSION['glpisearch']);
        $nativeSearch = $nativeSearchExists
            ? $_SESSION['glpisearch'][ProjectTask::class]
            : null;

        $nativeSavedExists = array_key_exists('glpi_loaded_savedsearch', $_SESSION);
        $nativeSaved = $nativeSavedExists ? $_SESSION['glpi_loaded_savedsearch'] : null;

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

        try {
            return $callback();
        } finally {
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

            if ($nativeSearchExists) {
                $_SESSION['glpisearch'][ProjectTask::class] = $nativeSearch;
            } else {
                unset($_SESSION['glpisearch'][ProjectTask::class]);
            }

            if ($nativeSavedExists) {
                $_SESSION['glpi_loaded_savedsearch'] = $nativeSaved;
            } else {
                unset($_SESSION['glpi_loaded_savedsearch']);
            }
        }
    }

    public function setCriteria(array $criteria): void
    {
        $_SESSION[self::ROOT_KEY][self::SEARCH_KEY]['criteria'] = array_values($criteria);
    }
}
