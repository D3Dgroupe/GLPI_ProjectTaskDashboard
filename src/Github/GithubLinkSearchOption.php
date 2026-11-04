<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Github;

use GlpiPlugin\Projecttaskdashboard\Config;

/** Definition and rendering of the "GitHub" ProjectTask search option. */
final class GithubLinkSearchOption
{
    /** @return array<string, mixed> */
    public static function definition(): array
    {
        return [
            'table' => GithubLink::TABLE,
            'field' => 'number',
            'name' => 'GitHub',
            'datatype' => 'number',
            'massiveaction' => false,
            'joinparams' => [
                'jointype' => 'itemtype_item',
                'condition' => ['NEWTABLE.itemtype' => 'ProjectTask'],
            ],
        ];
    }

    /** Cell HTML for a result row ('' when the row is not for this option or has no link). */
    public static function giveItem(string $type, int $id, array $data): string
    {
        if ($type !== \ProjectTask::class || $id !== Config::FIELD_GITHUB) {
            return '';
        }
        $taskId = (int) ($data['id'] ?? 0);
        $link = $taskId > 0 ? GithubLink::findForItem(\ProjectTask::class, $taskId) : null;
        // Exports (CSV, PDF, ODS, XLSX...) must not contain HTML: only the HTML views get the link.
        $html = in_array(\Search::$output_type, [\Search::HTML_OUTPUT, \Search::GLOBAL_SEARCH], true);
        return $html ? GithubLinkRenderer::issueCell($link) : GithubLinkRenderer::issueText($link);
    }
}
