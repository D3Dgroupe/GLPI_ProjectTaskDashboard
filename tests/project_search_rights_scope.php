<?php

declare(strict_types=1);

namespace {
    if (!class_exists('Project')) {
        class Project
        {
            public const READMY = 1;
            public const READALL = 1024;
        }
    }
}

namespace GlpiPlugin\Projecttaskdashboard\Search {
    require __DIR__ . '/../src/Search/ProjectSearchRightsScope.php';

    $scope = new ProjectSearchRightsScope();

    $_SESSION = [
        'glpiactiveprofile' => [
            'project' => \Project::READMY | 2 | 4,
        ],
    ];
    $original = $_SESSION['glpiactiveprofile']['project'];

    $inside = $scope->run(static function (): int {
        return (int) $_SESSION['glpiactiveprofile']['project'];
    });

    $expectedInside = ($original | \Project::READALL) & ~\Project::READMY;
    assert($inside === $expectedInside, 'scope must grant READALL and clear READMY during ProjectTask search');
    assert($_SESSION['glpiactiveprofile']['project'] === $original, 'scope must restore original rights after success');

    try {
        $scope->run(static function (): void {
            throw new \RuntimeException('boom');
        });
        assert(false, 'exception expected');
    } catch (\RuntimeException $e) {
        assert($e->getMessage() === 'boom');
    }
    assert($_SESSION['glpiactiveprofile']['project'] === $original, 'scope must restore original rights after exception');

    echo "project search rights scope ok\n";
}
