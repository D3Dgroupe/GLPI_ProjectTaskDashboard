<?php

declare(strict_types=1);

// Issue #23: per-project emoji + "Afficher dans le menu rapide", stored in
// GLPI's config table (no plugin SQL schema) and edited on the project form.

class Config
{
    public static array $store = [];
    public static function getConfigurationValues(string $context, array $names = []): array { return self::$store[$context] ?? []; }
    public static function setConfigurationValues(string $context, array $values = []): void { self::$store[$context] = $values + (self::$store[$context] ?? []); }
    public static function deleteConfigurationValues(string $context, array $values = []): void { foreach ($values as $v) { unset(self::$store[$context][$v]); } }
}
class Project
{
    public static bool $canCreate = true;
    public array $fields = [];
    public bool $canUpdate = true;
    public static function canCreate(): bool { return self::$canCreate; }
    public function canUpdateItem(): bool { return $this->canUpdate; }
}
function htmlescape(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }

require __DIR__ . '/../src/Project/ProjectMenuSettings.php';
require __DIR__ . '/../src/Project/ProjectSettingsForm.php';

use GlpiPlugin\Projecttaskdashboard\Project\ProjectMenuSettings;
use GlpiPlugin\Projecttaskdashboard\Project\ProjectSettingsForm;

$s = new ProjectMenuSettings();
assert($s->get(5) === ['emoji' => '📁', 'show_in_menu' => true], 'defaults keep the current behaviour');

// Emoji normalisation.
assert(ProjectMenuSettings::normalizeEmoji('  🚀 ') === '🚀');
assert(ProjectMenuSettings::normalizeEmoji('') === '📁');
assert(ProjectMenuSettings::normalizeEmoji('<b>🔥</b>') === '🔥');
assert(ProjectMenuSettings::normalizeEmoji("🛠\n\t") === '🛠');
assert(ProjectMenuSettings::normalizeEmoji('👨‍👩‍👧') === '👨‍👩‍👧', 'ZWJ sequences must survive');
assert(mb_strlen(ProjectMenuSettings::normalizeEmoji(str_repeat('a', 50))) === ProjectMenuSettings::EMOJI_MAX_LENGTH);

// Only the project form's own input is applied.
assert($s->saveFromInput(5, ['name' => 'x']) === false);
assert(Config::$store === [], 'API / massive action input must not touch settings');
assert($s->saveFromInput(5, ['_ptd_menu_settings' => '1', '_ptd_emoji' => '🚀', '_ptd_show_in_menu' => '0']) === true);
assert($s->get(5) === ['emoji' => '🚀', 'show_in_menu' => false]);
assert(isset(Config::$store['plugin:projecttaskdashboard']['project_5']), 'stored in GLPI config, one key per project');
$s->saveFromInput(5, ['_ptd_menu_settings' => '1', '_ptd_emoji' => '', '_ptd_show_in_menu' => '1']);
assert($s->get(5) === ['emoji' => '📁', 'show_in_menu' => true]);

$s->save(6, '🧪', false);
$s->delete(5);
assert($s->get(5)['emoji'] === '📁' && $s->get(6)['emoji'] === '🧪');
$s->deleteAll();
assert((Config::$store['plugin:projecttaskdashboard'] ?? []) === [], 'uninstall must clean every key');

// Form.
$project = new Project();
$project->fields = ['id' => 6];
$s->save(6, '🧪', false);
ob_start();
(new ProjectSettingsForm())->render(['item' => $project, 'options' => []]);
$html = (string) ob_get_clean();
assert(str_contains($html, 'name="_ptd_emoji"') && str_contains($html, 'value="🧪"'));
assert(str_contains($html, 'name="_ptd_show_in_menu" value="0"'), 'unchecked switch must still post 0');
assert(!str_contains($html, ' checked'), 'hidden project: switch off');
assert(str_contains($html, 'name="_ptd_menu_settings"'));

$project->canUpdate = false;
ob_start();
(new ProjectSettingsForm())->render(['item' => $project, 'options' => []]);
$html = (string) ob_get_clean();
assert(str_contains($html, ' disabled') && !str_contains($html, '_ptd_menu_settings'), 'read-only users cannot change settings');

ob_start();
(new ProjectSettingsForm())->render(['item' => new stdClass(), 'options' => []]);
(new ProjectSettingsForm())->render(['item' => $project, 'options' => ['withtemplate' => 1]]);
assert(ob_get_clean() === '', 'only on real project forms');

// Hooks wiring.
$setup = file_get_contents(__DIR__ . '/../setup.php');
$hook = file_get_contents(__DIR__ . '/../hook.php');
assert(str_contains($setup, "Hooks::POST_ITEM_FORM]['projecttaskdashboard'] = 'plugin_projecttaskdashboard_post_item_form'"));
assert(str_contains($setup, "Project::class => 'plugin_projecttaskdashboard_project_pre_update'"), 'PRE_ITEM_UPDATE: ITEM_UPDATE is skipped when no project column changed');
assert(str_contains($setup, "Project::class => 'plugin_projecttaskdashboard_project_added'"));
assert(str_contains($setup, "Project::class => 'plugin_projecttaskdashboard_project_purged'"));
assert(str_contains($hook, '->deleteAll()'), 'uninstall cleans the stored settings');

echo "project menu settings ok\n";
