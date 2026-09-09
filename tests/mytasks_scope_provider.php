<?php

declare(strict_types=1);

class ProjectTask { public static function getTable(): string { return 'glpi_projecttasks'; } }
class MineProviderStub { public function taskIds(): array { return [11, 22, 33]; } }
class ActiveProviderStub { public function ids(): array { return [1, 2]; } }
class_alias(MineProviderStub::class, 'GlpiPlugin\\Projecttaskdashboard\\Search\\MineTaskProvider');
class_alias(ActiveProviderStub::class, 'GlpiPlugin\\Projecttaskdashboard\\Project\\ActiveProjectProvider');
final class ScopeFakeDB
{
    public array $lastRequest = [];
    public function request(array $query): array
    {
        $this->lastRequest = $query;
        return [['id' => 11], ['id' => 33], ['id' => 11]];
    }
}
$GLOBALS['DB'] = new ScopeFakeDB();

require __DIR__ . '/../src/Search/MyTasksScopeProvider.php';

use GlpiPlugin\Projecttaskdashboard\Search\MyTasksScopeProvider;

$p = new MyTasksScopeProvider();
assert($p->taskIds() === [11, 33]);
assert($GLOBALS['DB']->lastRequest['WHERE'] === [
    'id' => [11, 22, 33],
    'projects_id' => [1, 2],
]);

echo "mytasks scope provider ok\n";
