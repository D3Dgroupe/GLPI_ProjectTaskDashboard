<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Task;

final class InlineEditUrl
{
    public static function get(): string
    {
        global $CFG_GLPI;
        return $CFG_GLPI['root_doc'] . '/plugins/projecttaskdashboard/front/task-inline-edit.php';
    }
}
