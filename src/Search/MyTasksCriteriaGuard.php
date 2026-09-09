<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Search;

final class MyTasksCriteriaGuard
{
    public function __construct(private readonly TaskIdCriteriaBuilder $builder = new TaskIdCriteriaBuilder())
    {
    }

    public function force(array $userCriteria, array $allowedTaskIds): array
    {
        $execution = [];
        if ($userCriteria !== []) {
            $execution[] = ['criteria' => array_values($userCriteria)];
        }

        $execution[] = $this->builder->criterion($allowedTaskIds, 'AND', true);
        return $execution;
    }
}
