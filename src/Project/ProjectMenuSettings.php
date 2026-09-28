<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Project;

use Config;

/**
 * Issue #23: per-project emoji and "show in the Projet quick menu" flag.
 *
 * Stored in GLPI's own configuration table (context below, one key per
 * project) so the plugin still ships without any SQL schema of its own.
 */
final class ProjectMenuSettings
{
    public const CONFIG_CONTEXT = 'plugin:projecttaskdashboard';
    public const DEFAULT_EMOJI = '📁';
    public const EMOJI_MAX_LENGTH = 16;

    /** @var array<int, array{emoji: string, show_in_menu: bool}>|null */
    private ?array $cache = null;

    /**
     * @return array{emoji: string, show_in_menu: bool}
     */
    public function get(int $projectId): array
    {
        return $this->all()[$projectId] ?? ['emoji' => self::DEFAULT_EMOJI, 'show_in_menu' => true];
    }

    public function save(int $projectId, string $emoji, bool $showInMenu): void
    {
        if ($projectId <= 0) {
            return;
        }
        Config::setConfigurationValues(self::CONFIG_CONTEXT, [
            self::key($projectId) => json_encode([
                'emoji' => self::normalizeEmoji($emoji),
                'show_in_menu' => $showInMenu,
            ], JSON_THROW_ON_ERROR),
        ]);
        $this->cache = null;
    }

    public function delete(int $projectId): void
    {
        Config::deleteConfigurationValues(self::CONFIG_CONTEXT, [self::key($projectId)]);
        $this->cache = null;
    }

    public function deleteAll(): void
    {
        $keys = array_keys(Config::getConfigurationValues(self::CONFIG_CONTEXT));
        if ($keys !== []) {
            Config::deleteConfigurationValues(self::CONFIG_CONTEXT, $keys);
        }
        $this->cache = null;
    }

    /**
     * Apply the settings posted by the project form (see ProjectSettingsForm).
     * Ignores any input that didn't come from that form (API, massive
     * actions...) so it never resets settings by accident.
     */
    public function saveFromInput(int $projectId, array $input): bool
    {
        if (!isset($input['_ptd_menu_settings'])) {
            return false;
        }
        $this->save(
            $projectId,
            is_scalar($input['_ptd_emoji'] ?? null) ? (string) $input['_ptd_emoji'] : '',
            (string) ($input['_ptd_show_in_menu'] ?? '0') === '1'
        );
        return true;
    }

    public static function normalizeEmoji(string $emoji): string
    {
        $emoji = trim(strip_tags($emoji));
        $emoji = (string) preg_replace('/[\p{Cc}\s]+/u', '', $emoji);
        $emoji = mb_substr($emoji, 0, self::EMOJI_MAX_LENGTH);
        return $emoji !== '' ? $emoji : self::DEFAULT_EMOJI;
    }

    /**
     * @return array<int, array{emoji: string, show_in_menu: bool}>
     */
    private function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $this->cache = [];
        foreach (Config::getConfigurationValues(self::CONFIG_CONTEXT) as $key => $value) {
            if (!preg_match('/^project_(\d+)$/', (string) $key, $m)) {
                continue;
            }
            $data = json_decode((string) $value, true);
            if (!is_array($data)) {
                continue;
            }
            $this->cache[(int) $m[1]] = [
                'emoji' => self::normalizeEmoji((string) ($data['emoji'] ?? '')),
                'show_in_menu' => (bool) ($data['show_in_menu'] ?? true),
            ];
        }
        return $this->cache;
    }

    private static function key(int $projectId): string
    {
        return 'project_' . $projectId;
    }
}
