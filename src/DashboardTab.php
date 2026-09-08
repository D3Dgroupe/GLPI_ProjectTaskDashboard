<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard;

use CommonGLPI;
use Project;

final class DashboardTab extends CommonGLPI
{
    public static function getTypeName($nb = 0): string
    {
        return '📊 Pilotage des tâches';
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($withtemplate || !$item instanceof Project) {
            return '';
        }
        return self::createTabEntry(self::getTypeName());
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if (!$withtemplate && $item instanceof Project) {
            (new DashboardRenderer())->render($item);
        }
        return true;
    }

    public static function getIcon(): string
    {
        return 'ti ti-layout-dashboard';
    }
}
