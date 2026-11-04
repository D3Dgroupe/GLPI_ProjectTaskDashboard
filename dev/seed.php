<?php
/**
 * Données de test pour le GLPI jetable. Exécuté DANS le conteneur par up.sh :
 *   php /opt/ptd/seed.php
 * Utilise uniquement l'API PHP de GLPI (classes métier), jamais de SQL direct.
 * Les utilisateurs sont créés avant par `bin/console user:create`. Sortie : JSON sur stdout.
 */
declare(strict_types=1);

chdir('/var/www/glpi');
require '/var/www/glpi/vendor/autoload.php';
$kernel = new Glpi\Kernel\Kernel('production');
$kernel->boot();

$tech = getenv('PTD_TECH_LOGIN');
$ro = getenv('PTD_RO_LOGIN');

function must(object $item, array $input): int
{
    $id = $item->add($input);
    if (!$id) {
        fwrite(STDERR, 'Echec add ' . $item::class . ' ' . json_encode($input) . "\n");
        exit(1);
    }
    return (int) $id;
}

// Session administrateur (super-admin 'glpi') pour créer les objets.
$auth = new Auth();
if (!$auth->login('glpi', 'glpi', true)) {
    fwrite(STDERR, "Connexion glpi impossible\n");
    exit(1);
}
Session::init($auth);
Session::changeActiveEntities(0, true);

// Entités : racine (0) + enfant « Autre ».
$entityOther = must(new Entity(), ['name' => 'Autre', 'entities_id' => 0]);
Session::changeActiveEntities('all', true);

// Profils : un compte technique réaliste (voir tout + mettre à jour), un en lecture seule.
$mkProfile = static function (string $name, array $rights): int {
    $id = must(new Profile(), ['name' => $name, 'interface' => 'central', '_profile_wizard' => 0]);
    ProfileRight::updateProfileRights($id, $rights);
    return $id;
};
$profTech = $mkProfile('PTD technique', [
    // Ce qu'un administrateur peut réellement configurer dans l'écran des profils (comme en production) :
    // Projets = « Voir tous » + « Mettre à jour » ; Tâches de projet = « Voir (acteur) » + « Mettre à jour (acteur) ».
    // (ProjectTask::getRights ne propose pas le bit UPDATE=2 : ne pas l'accorder ici.)
    'project' => Project::READALL | UPDATE,
    'projecttask' => ProjectTask::READMY | ProjectTask::UPDATEMY,
]);
$profRo = $mkProfile('PTD lecture seule', [
    'project' => Project::READALL,
    'projecttask' => READ,
]);

$userId = static function (string $login): int {
    $u = new User();
    if (!$u->getFromDBbyName($login)) {
        fwrite(STDERR, "Utilisateur $login introuvable\n");
        exit(1);
    }
    return (int) $u->getID();
};
$uTech = $userId($tech);
$uRo = $userId($ro);
// Droits : entité racine, récursifs (donc visibles aussi dans « Autre »).
must(new Profile_User(), ['users_id' => $uTech, 'profiles_id' => $profTech, 'entities_id' => 0, 'is_recursive' => 1]);
must(new Profile_User(), ['users_id' => $uRo, 'profiles_id' => $profRo, 'entities_id' => 0, 'is_recursive' => 1]);

// Projets et tâches. Entité racine.
$mkProject = static fn(string $name, int $entity, int $parent = 0): int
    => must(new Project(), ['name' => $name, 'entities_id' => $entity, 'projects_id' => $parent]);
$mkTask = static fn(string $name, int $project, int $entity): int
    => must(new ProjectTask(), ['name' => $name, 'projects_id' => $project, 'entities_id' => $entity]);

$parent = $mkProject('PTD Projet parent', 0);
$childA = $mkProject('PTD Sous-projet A', 0, $parent);
$childB = $mkProject('PTD Sous-projet B', 0, $parent);
$tasks = [
    $mkTask('PTD Tâche 1', $parent, 0),
    $mkTask('PTD Tâche 2', $childA, 0),
    $mkTask('PTD Tâche 3', $childA, 0),
    $mkTask('PTD Tâche 4', $childB, 0),
];
// Entité enfant.
$otherProject = $mkProject('PTD Projet autre entité', $entityOther);
$otherTask = $mkTask('PTD Tâche autre entité', $otherProject, $entityOther);

// Client OAuth de test (grant password, scope api). Secret relu en clair via GLPIKey.
$clientId = must(new OAuthClient(), [
    'name' => 'PTD tests',
    'is_active' => 1,
    'is_confidential' => 1,
    'grants' => ['password'],
    'scopes' => ['api'],
]);
$client = new OAuthClient();
$client->getFromDB($clientId);
$secret = (new GLPIKey())->decrypt($client->fields['secret']);

echo json_encode([
    'entity_other_id' => $entityOther,
    'user_tech_id' => $uTech,
    'user_ro_id' => $uRo,
    'project_parent_id' => $parent,
    'project_child_a_id' => $childA,
    'project_child_b_id' => $childB,
    'task_ids' => $tasks,
    'project_other_id' => $otherProject,
    'task_other_id' => $otherTask,
    'oauth_client_id' => $client->fields['identifier'],
    'oauth_client_secret' => $secret,
], JSON_UNESCAPED_SLASHES) . "\n";
