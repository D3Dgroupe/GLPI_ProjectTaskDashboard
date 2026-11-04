<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Github;

/**
 * Pure decision rule for a link write (no GLPI call, unit-testable).
 * Never re-points: an item already linked to GitHub id A is refused for id B.
 */
final class GithubLinkPolicy
{
    public const CREATE = 'create';
    public const UPDATE_METADATA = 'update_metadata';
    public const NOOP = 'noop';
    public const ITEM_LINKED_TO_OTHER = 'item_linked_to_other_github_id';
    public const GITHUB_ID_ALREADY_LINKED = 'github_id_already_linked';

    /**
     * @param array<string, mixed>|null $existingForItem row of the item (itemtype, items_id), if any
     * @param array<string, mixed>|null $existingForGithub row holding (kind, github_id), if any
     * @param array<string, mixed> $validated output of GithubLinkValidator::validate()
     */
    public static function decide(?array $existingForItem, ?array $existingForGithub, array $validated): string
    {
        if (
            $existingForGithub !== null
            && ((string) $existingForGithub['itemtype'] !== (string) $validated['itemtype']
                || (int) $existingForGithub['items_id'] !== (int) $validated['items_id'])
        ) {
            return self::GITHUB_ID_ALREADY_LINKED;
        }
        if ($existingForItem === null) {
            return self::CREATE;
        }
        if (
            (string) $existingForItem['kind'] !== (string) $validated['kind']
            || (int) $existingForItem['github_id'] !== (int) $validated['github_id']
        ) {
            return self::ITEM_LINKED_TO_OTHER;
        }
        $sameNumber = ($existingForItem['number'] === null ? null : (int) $existingForItem['number'])
            === ($validated['number'] === null ? null : (int) $validated['number']);
        if (
            $sameNumber
            && (string) $existingForItem['repository'] === (string) $validated['repository']
            && (string) $existingForItem['url'] === (string) $validated['url']
        ) {
            return self::NOOP;
        }
        return self::UPDATE_METADATA;
    }
}
