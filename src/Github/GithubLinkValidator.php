<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Github;

/**
 * Pure validation/normalisation of a GitHub link payload (no GLPI call).
 *
 * Errors are \InvalidArgumentException whose message is the machine code:
 * invalid_itemtype, invalid_items_id, kind_mismatch, invalid_github_id,
 * invalid_repo, invalid_number, invalid_url.
 */
final class GithubLinkValidator
{
    public const KIND_BY_ITEMTYPE = [
        'ProjectTask' => 'issue',
        'Project' => 'repository',
    ];
    public const URL_PREFIX = 'https://github.com/';
    private const MAX_GITHUB_ID = '9223372036854775807';
    private const MAX_URL_LENGTH = 1024;

    /**
     * @param array<string, mixed> $input
     * @return array{itemtype: string, items_id: int, kind: string, github_id: int, repository: string, number: ?int, url: string}
     */
    public static function validate(array $input): array
    {
        $itemtype = self::text($input['itemtype'] ?? null);
        if (!isset(self::KIND_BY_ITEMTYPE[$itemtype])) {
            throw new \InvalidArgumentException('invalid_itemtype');
        }
        $kind = self::text($input['kind'] ?? null);
        if ($kind !== self::KIND_BY_ITEMTYPE[$itemtype]) {
            throw new \InvalidArgumentException('kind_mismatch');
        }
        $itemsId = self::positiveInt($input['items_id'] ?? null, self::MAX_GITHUB_ID);
        if ($itemsId === null || $itemsId > 2147483647) {
            throw new \InvalidArgumentException('invalid_items_id');
        }
        $githubId = self::positiveInt($input['github_id'] ?? null, self::MAX_GITHUB_ID);
        if ($githubId === null) {
            throw new \InvalidArgumentException('invalid_github_id');
        }

        $repository = self::text($input['repository'] ?? null);
        if (preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repository) !== 1) {
            throw new \InvalidArgumentException('invalid_repo');
        }

        $rawNumber = $input['number'] ?? null;
        if ($kind === 'issue') {
            $number = self::positiveInt($rawNumber, '2147483647');
            if ($number === null) {
                throw new \InvalidArgumentException('invalid_number');
            }
        } else {
            if ($rawNumber !== null && $rawNumber !== '') {
                throw new \InvalidArgumentException('invalid_number');
            }
            $number = null;
        }

        $url = self::text($input['url'] ?? null);
        if (
            strlen($url) <= strlen(self::URL_PREFIX)
            || strlen($url) > self::MAX_URL_LENGTH
            || !str_starts_with($url, self::URL_PREFIX)
            || preg_match('/[\s\x00-\x1f\x7f<>"\'\\\\]/', $url) === 1
        ) {
            throw new \InvalidArgumentException('invalid_url');
        }

        return [
            'itemtype' => $itemtype,
            'items_id' => $itemsId,
            'kind' => $kind,
            'github_id' => $githubId,
            'repository' => $repository,
            'number' => $number,
            'url' => $url,
        ];
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    /** Strictly positive decimal integer (int or digit string) up to $max, else null. */
    private static function positiveInt(mixed $value, string $max): ?int
    {
        if (is_int($value)) {
            $value = (string) $value;
        } elseif (is_string($value)) {
            $value = trim($value);
        } else {
            return null;
        }
        if (preg_match('/^[1-9][0-9]*$/', $value) !== 1) {
            return null;
        }
        if (strlen($value) > strlen($max) || (strlen($value) === strlen($max) && strcmp($value, $max) > 0)) {
            return null;
        }
        return (int) $value;
    }
}
