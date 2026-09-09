<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/inc/includes.php';

Session::checkCentralAccess();
Html::header('Mes tâches', $_SERVER['PHP_SELF'], 'projecttaskdashboard_project');
(new \GlpiPlugin\Projecttaskdashboard\MyTasksRenderer())->render();
Html::footer();
