<?php
// Crée (idempotent) dans le GLPI jetable un conteneur Fields « dom » sur ProjectTask avec les champs attendus par le
// plugin (FIELDS_*_HINT_ID) : ids 1-3 = champs factices, 4 = « Priorité » (dropdown), 5 = « Module » (dropdown ITILCategory),
// + une catégorie ITIL « Module Compta » et un ticket de test. Via les classes du plugin Fields / du cœur (pas de SQL).
// Lancé par `WITH_FIELDS=1 bash dev/up.sh` (le script est envoyé sur l'entrée standard de `php`).
chdir('/var/www/glpi');
require '/var/www/glpi/vendor/autoload.php';
$kernel = new Glpi\Kernel\Kernel('production');
$kernel->boot();

$u = new User();
$u->getFromDB(2); // glpi (super-admin)
$auth = new Auth();
$auth->auth_succeded = true;
$auth->user = $u;
Session::init($auth);
Session::changeActiveEntities(0, true);

$container = new PluginFieldsContainer();
$found = $container->find(['label' => 'PTD tâches']);
if ($found === []) {
    $cid = $container->add([
        'name' => 'ptdtasks', 'label' => 'PTD tâches', 'type' => 'dom', 'itemtypes' => ['ProjectTask'],
        'is_active' => 1, 'entities_id' => 0, 'is_recursive' => 1, 'subtype' => '',
    ]);
    $field = new PluginFieldsField();
    $make = static function (string $label, string $type, int $rank) use ($field, $cid): int {
        // is_readonly doit être 0 : sinon Fields ignore les valeurs envoyées à la création.
        return (int) $field->add([
            'label' => $label, 'type' => $type, 'plugin_fields_containers_id' => $cid, 'is_active' => 1,
            'ranking' => $rank, 'mandatory' => 0, 'multiple' => 0, 'default_value' => '', 'is_readonly' => 0,
        ]);
    };
    $make('Dummy A', 'text', 1);
    $make('Dummy B', 'text', 2);
    $make('Dummy C', 'text', 3);
    $priority = $make('Priorité', 'dropdown', 4);
    $make('Module', 'dropdown-ITILCategory', 5);
    $field->getFromDB($priority);
    $class = PluginFieldsDropdown::getClassname($field->fields['name']);
    foreach (['🟢 BASSE', '🟡 NORMALE', '🔴 HAUTE', '🚨 CRITIQUE'] as $value) {
        (new $class())->add(['name' => $value]);
    }
}

$category = new ITILCategory();
$cat = $category->find(['name' => 'Module Compta']);
$catId = $cat === [] ? (int) $category->add(['name' => 'Module Compta', 'entities_id' => 0, 'is_recursive' => 1]) : (int) array_key_first($cat);
$ticket = new Ticket();
$tk = $ticket->find(['name' => 'Ticket test flux Fields']);
$tid = $tk === [] ? (int) $ticket->add([
    'name' => 'Ticket test flux Fields', 'content' => 'Contenu test', 'itilcategories_id' => $catId,
    'entities_id' => 0, '_users_id_requester' => 2,
]) : (int) array_key_first($tk);
echo json_encode(['fields_container' => array_key_first((new PluginFieldsContainer())->find(['label' => 'PTD tâches'])), 'category' => $catId, 'ticket' => $tid]), "\n";
