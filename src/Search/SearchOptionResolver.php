<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Search;

use Search;

final class SearchOptionResolver
{
    /** @var null|callable(string): array */
    private $provider;

    public function __construct(?callable $provider = null)
    {
        $this->provider = $provider;
    }

    public function options(string $itemtype): array
    {
        if ($this->provider !== null) {
            return ($this->provider)($itemtype);
        }
        return Search::getOptions($itemtype);
    }
}
