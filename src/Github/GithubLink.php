<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Github;

use CommonDBTM;
use DBConnection;

/**
 * Link between a ProjectTask / Project and its GitHub object (issue / repository).
 *
 * Rows are only ever written through upsertForItem() with an already validated
 * payload (see GithubLinkValidator) and removed by deleteForItem() on purge.
 * Uniqueness is enforced by the database: (itemtype, items_id) and (kind, github_id).
 */
final class GithubLink extends CommonDBTM
{
    public const TABLE = 'glpi_plugin_projecttaskdashboard_githublinks';
    public const ERROR_ALREADY_LINKED = 'github_id_already_linked';
    private const MYSQL_DUPLICATE_KEY = 1062;

    public static function getTable($classname = null): string
    {
        return self::TABLE;
    }

    public static function getTypeName($nb = 0): string
    {
        return 'Lien GitHub';
    }

    /** CREATE TABLE statement following GLPI conventions (used by install/upgrade). */
    public static function getCreateTableSql(): string
    {
        $charset = DBConnection::getDefaultCharset();
        $collation = DBConnection::getDefaultCollation();
        $sign = DBConnection::getDefaultPrimaryKeySignOption();
        $table = self::TABLE;

        return "CREATE TABLE `$table` (
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `itemtype` varchar(100) NOT NULL,
            `items_id` int {$sign} NOT NULL,
            `kind` varchar(20) NOT NULL,
            `github_id` bigint unsigned NOT NULL,
            `repository` varchar(255) NOT NULL,
            `number` int unsigned DEFAULT NULL,
            `url` varchar(1024) NOT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unicity_item` (`itemtype`, `items_id`),
            UNIQUE KEY `unicity_github` (`kind`, `github_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC;";
    }

    /** @return array<string, mixed>|null */
    public static function findForItem(string $itemtype, int $itemsId): ?array
    {
        return self::fetchOne(['itemtype' => $itemtype, 'items_id' => $itemsId]);
    }

    /**
     * @param int[] $ids
     * @return array<int, array<string, mixed>> rows indexed by items_id
     */
    public static function findForItems(string $itemtype, array $ids): array
    {
        global $DB;

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0)));
        if ($ids === []) {
            return [];
        }
        $rows = [];
        foreach ($DB->request(['FROM' => self::TABLE, 'WHERE' => ['itemtype' => $itemtype, 'items_id' => $ids]]) as $row) {
            $rows[(int) $row['items_id']] = $row;
        }
        return $rows;
    }

    /** @return array<string, mixed>|null */
    public static function findByGithub(string $kind, int $githubId): ?array
    {
        return self::fetchOne(['kind' => $kind, 'github_id' => $githubId]);
    }

    /**
     * Idempotent create-or-update of the link of an item.
     *
     * @param array<string, mixed> $validated output of GithubLinkValidator::validate()
     * @return array{row: array<string, mixed>, created: bool}
     * @throws \DomainException message github_id_already_linked when the GitHub object
     *                          is already linked to ANOTHER item
     */
    public static function upsertForItem(array $validated): array
    {
        global $DB;

        $itemtype = (string) $validated['itemtype'];
        $itemsId = (int) $validated['items_id'];
        $kind = (string) $validated['kind'];
        $githubId = (int) $validated['github_id'];
        $data = [
            'kind' => $kind,
            'github_id' => $githubId,
            'repository' => (string) $validated['repository'],
            'number' => $validated['number'] === null ? null : (int) $validated['number'],
            'url' => (string) $validated['url'],
        ];

        // Two attempts: the second one handles a concurrent insert that won the race.
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $owner = self::findByGithub($kind, $githubId);
            if ($owner !== null && ((string) $owner['itemtype'] !== $itemtype || (int) $owner['items_id'] !== $itemsId)) {
                throw new \DomainException(self::ERROR_ALREADY_LINKED);
            }

            $existing = self::findForItem($itemtype, $itemsId);
            $now = date('Y-m-d H:i:s');
            try {
                if ($existing === null) {
                    $DB->insert(self::TABLE, $data + [
                        'itemtype' => $itemtype,
                        'items_id' => $itemsId,
                        'date_creation' => $now,
                        'date_mod' => $now,
                    ]);
                    return ['row' => self::findForItem($itemtype, $itemsId) ?? [], 'created' => true];
                }
                if (self::sameLink($existing, $data)) {
                    return ['row' => $existing, 'created' => false];
                }
                $DB->update(self::TABLE, $data + ['date_mod' => $now], ['id' => (int) $existing['id']]);
                return ['row' => self::findForItem($itemtype, $itemsId) ?? [], 'created' => false];
            } catch (\Throwable $e) {
                if (!self::isDuplicateKey($e) || $attempt === 1) {
                    throw $e;
                }
                // Duplicate key: loop to re-read and decide (same item -> idempotent, other item -> 409).
            }
        }
        throw new \LogicException('unreachable');
    }

    public static function deleteForItem(string $itemtype, int $itemsId): void
    {
        global $DB;

        if ($itemsId <= 0) {
            return;
        }
        $DB->delete(self::TABLE, ['itemtype' => $itemtype, 'items_id' => $itemsId]);
    }

    /**
     * @param array<string, mixed> $criteria
     * @return array<string, mixed>|null
     */
    private static function fetchOne(array $criteria): ?array
    {
        global $DB;

        $row = $DB->request(['FROM' => self::TABLE, 'WHERE' => $criteria, 'LIMIT' => 1])->current();
        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $data
     */
    private static function sameLink(array $row, array $data): bool
    {
        return (string) $row['kind'] === $data['kind']
            && (int) $row['github_id'] === $data['github_id']
            && (string) $row['repository'] === $data['repository']
            && ($row['number'] === null ? null : (int) $row['number']) === $data['number']
            && (string) $row['url'] === $data['url'];
    }

    private static function isDuplicateKey(\Throwable $e): bool
    {
        return (int) $e->getCode() === self::MYSQL_DUPLICATE_KEY
            || str_contains($e->getMessage(), 'Duplicate entry');
    }
}
