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
            // Read by giveItem() from the result row: no per-row lookup.
            'additionalfields' => ['url'],
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
        // Columns selected by the search engine itself (field `number` + additionalfields `url`).
        $key = 'ITEM_' . \ProjectTask::class . '_' . Config::FIELD_GITHUB;
        $row = is_array($data['raw'] ?? null) ? $data['raw'] : $data;
        $number = $row[$key] ?? null;
        $link = ($number === null || $number === '')
            ? null
            : ['number' => $number, 'url' => (string) ($row[$key . '_url'] ?? '')];
        // Exports (CSV, PDF, ODS, XLSX...) must not contain HTML: only the HTML views get the link.
        $html = in_array(\Search::$output_type, [\Search::HTML_OUTPUT, \Search::GLOBAL_SEARCH], true);
        return $html ? GithubLinkRenderer::issueCell($link) : GithubLinkRenderer::issueText($link);
    }
}
