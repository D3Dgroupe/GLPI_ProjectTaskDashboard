<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Github;

use Project;
use ProjectTask;

/** Read-only GitHub line at the bottom of the ProjectTask and Project forms (POST_ITEM_FORM). */
final class GithubLinkFormLine
{
    public function render(array $params): void
    {
        $item = $params['item'] ?? null;
        if (!$item instanceof Project && !$item instanceof ProjectTask) {
            return;
        }
        $id = (int) ($item->fields['id'] ?? 0);
        if ($id <= 0 || (int) ($params['options']['withtemplate'] ?? 0) > 0) {
            return;
        }
        $link = GithubLink::findForItem($item::class, $id);
        echo $item instanceof Project
            ? GithubLinkRenderer::projectFormLine($link)
            : GithubLinkRenderer::taskFormLine($link);
    }
}
