<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Project;

use Project;

/**
 * Issue #23: "Emoji" and "Afficher dans le menu rapide" fields at the bottom
 * of the project form (Hooks::POST_ITEM_FORM), saved with the project.
 */
final class ProjectSettingsForm
{
    public function __construct(private readonly ProjectMenuSettings $settings = new ProjectMenuSettings())
    {
    }

    public function render(array $params): void
    {
        $item = $params['item'] ?? null;
        if (!$item instanceof Project || (int) ($params['options']['withtemplate'] ?? 0) > 0) {
            return;
        }

        $id = (int) ($item->fields['id'] ?? 0);
        $settings = $id > 0
            ? $this->settings->get($id)
            : ['emoji' => ProjectMenuSettings::DEFAULT_EMOJI, 'show_in_menu' => true];
        $canEdit = $id > 0 ? $item->canUpdateItem() : Project::canCreate();
        $disabled = $canEdit ? '' : ' disabled';
        $rand = mt_rand();

        echo '<div class="card-body border-top ptd-project-menu-settings">';
        echo '<div class="row">';

        echo '<div class="form-field row col-12 col-sm-6 mb-2">';
        echo '<label class="col-form-label col-xxl-5 text-xxl-end" for="ptd_emoji_' . $rand . '">Emoji du projet</label>';
        echo '<div class="col-xxl-7 field-container">';
        echo '<input type="text" class="form-control" id="ptd_emoji_' . $rand . '" name="_ptd_emoji"'
            . ' maxlength="' . ProjectMenuSettings::EMOJI_MAX_LENGTH . '"'
            . ' placeholder="' . htmlescape(ProjectMenuSettings::DEFAULT_EMOJI) . '"'
            . ' value="' . htmlescape($settings['emoji']) . '"' . $disabled . '>';
        echo '<div class="form-text">Affiché devant le nom du projet dans le menu Projet. Sous Windows : touche Windows + <kbd>.</kbd> pour choisir un emoji.</div>';
        echo '</div></div>';

        echo '<div class="form-field row col-12 col-sm-6 mb-2">';
        echo '<label class="col-form-label col-xxl-5 text-xxl-end" for="ptd_show_in_menu_' . $rand . '">Afficher dans le menu rapide</label>';
        echo '<div class="col-xxl-7 field-container">';
        echo '<input type="hidden" name="_ptd_show_in_menu" value="0">';
        echo '<label class="form-check form-switch mt-2">';
        echo '<input type="checkbox" class="form-check-input" id="ptd_show_in_menu_' . $rand . '" name="_ptd_show_in_menu" value="1"'
            . ($settings['show_in_menu'] ? ' checked' : '') . $disabled . '>';
        echo '</label>';
        echo '<div class="form-text">Menu Projet en haut de l\'écran (les projets terminés n\'y apparaissent jamais).</div>';
        echo '</div></div>';

        echo '</div>';
        if ($canEdit) {
            echo '<input type="hidden" name="_ptd_menu_settings" value="1">';
        }
        echo '</div>';
    }
}
