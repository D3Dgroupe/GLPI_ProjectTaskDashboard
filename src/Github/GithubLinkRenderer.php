<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Github;

/**
 * HTML rendering of GitHub links (list cell, task / project form lines).
 * Pure PHP (no GLPI call). Everything is escaped; only https://github.com/ URLs become links.
 */
final class GithubLinkRenderer
{
    private const GITHUB_PREFIX = 'https://github.com/';

    public static function issueCell(?array $link): string
    {
        if ($link === null || $link === []) {
            return '';
        }
        $label = '#' . self::esc((string) ($link['number'] ?? ''));
        return self::anchor((string) ($link['url'] ?? ''), $label) ?? $label;
    }

    /** Plain-text cell for non-HTML outputs (CSV, PDF, ODS, XLSX...): `#number`, '' without link. */
    public static function issueText(?array $link): string
    {
        if ($link === null || $link === []) {
            return '';
        }
        return '#' . (string) ($link['number'] ?? '');
    }

    public static function taskFormLine(?array $link): string
    {
        $value = ($link === null || $link === [])
            ? '<span class="text-muted">Aucune issue GitHub liée</span>'
            : self::issueCell($link);
        return self::formLine('Issue GitHub', $value);
    }

    public static function projectFormLine(?array $link): string
    {
        if ($link === null || $link === []) {
            $value = '<span class="text-muted">Aucun dépôt GitHub lié</span>';
        } else {
            $name = self::esc((string) ($link['repository'] ?? ''));
            $value = self::anchor((string) ($link['url'] ?? ''), $name) ?? $name;
        }
        return self::formLine('Dépôt GitHub', $value);
    }

    private static function formLine(string $label, string $valueHtml): string
    {
        return '<div class="card-body border-top ptd-github-line"><div class="row">'
            . '<div class="form-field row col-12 mb-2">'
            . '<label class="col-form-label col-xxl-2 text-xxl-end">' . self::esc($label) . '</label>'
            . '<div class="col-xxl-10 field-container pt-2">' . $valueHtml . '</div>'
            . '</div></div></div>';
    }

    private static function anchor(string $url, string $escapedLabel): ?string
    {
        if (!str_starts_with($url, self::GITHUB_PREFIX) || preg_match('/[\x00-\x20]/', $url) === 1) {
            return null;
        }
        return '<a href="' . self::esc($url) . '" target="_blank" rel="noopener noreferrer">' . $escapedLabel . '</a>';
    }

    private static function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
