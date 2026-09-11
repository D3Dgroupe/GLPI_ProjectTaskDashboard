<?php

declare(strict_types=1);

// The state widgets (À FAIRE / EN COURS / À CONTRÔLER) must be painted with
// the same color an admin configures on the matching ProjectState
// (Configuration > Dropdowns > Project statuses) — the same color GLPI
// itself uses for ProjectTask cards on the Kanban — instead of a plain
// white/bordered card. The "mine" widget has no single state, so it must
// stay uncolored.

$colorsClass = file_get_contents(__DIR__ . '/../src/WidgetStateColors.php');
$renderer = file_get_contents(__DIR__ . '/../src/DashboardRenderer.php');
$widgetsTemplate = file_get_contents(__DIR__ . '/../templates/dashboard/widgets.html.twig');
$css = file_get_contents(__DIR__ . '/../css/projecttaskdashboard.css');

assert($colorsClass !== false && $renderer !== false && $widgetsTemplate !== false && $css !== false);

assert(str_contains($colorsClass, 'ProjectState::getById'), 'colors must come from the real ProjectState dictionary, not a hardcoded map');
assert(str_contains($colorsClass, 'Toolbox::getFgColor'), 'text color must use GLPI\'s own contrast helper, not a custom guess');

assert(str_contains($renderer, 'WidgetStateColors'), 'DashboardRenderer must resolve widget colors');
assert(str_contains($renderer, "'state_colors' =>"), 'widget colors must be passed to the template');
assert(str_contains($renderer, "'mine' => null"), 'the mine widget must not be tied to a ProjectState color');

assert(str_contains($widgetsTemplate, 'attribute(state_colors, item.key)'), 'template must look up the color for each widget');
assert(str_contains($widgetsTemplate, 'ptd-widget-colored'), 'colored widgets must be flagged for CSS styling');
assert(str_contains($widgetsTemplate, '--ptd-widget-bg') && str_contains($widgetsTemplate, '--ptd-widget-fg'), 'colors must be passed through as CSS custom properties');

assert(str_contains($css, '.ptd-widget.ptd-widget-colored'), 'CSS must style colored widgets');
assert(str_contains($css, 'var(--ptd-widget-bg)') && str_contains($css, 'var(--ptd-widget-fg)'), 'CSS must consume the per-widget color custom properties');

echo "widget state colors contract ok\n";
