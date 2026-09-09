# GLPI Project Task Dashboard V1.1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Faire du plugin `projecttaskdashboard` le point d’entrée principal de la gestion des projets et tâches dans GLPI 11 : création depuis le dashboard, navigation Projet simplifiée, secteur principal `Projet`, et vue globale sécurisée `Mes tâches`.

**Architecture:** La V1.1 reste entièrement dans le plugin, sans patch core et sans table SQL propre. La notion `projet visible + non terminé` est centralisée dans un provider commun au menu et à `Mes tâches`; la recherche globale réutilise `ProjectTask` avec un scope serveur obligatoire, y compris sur les refresh natifs `/ajax/search.php`. Les adaptations d’onglets restent purement visuelles et tous les scopes de session/droits restaurent exactement leur état d’origine.

**Tech Stack:** PHP `>= 8.2`, GLPI `>= 11.0.0` et `< 12.0.0`, Twig GLPI, moteur natif `Glpi\Search`, jQuery/JavaScript GLPI, hooks GLPI 11, Fields optionnel.

**Spec:** `docs/superpowers/specs/2026-09-09-glpi-project-task-dashboard-v1.1-design.md`

## Global Constraints

- Aucun fichier du core GLPI n’est modifié.
- Aucun nouveau schéma SQL propre au plugin.
- Les ACL natives `Project` / `ProjectTask` restent l’autorité finale.
- Un projet invisible ne doit jamais apparaître dans le menu ni autoriser une tâche dans `Mes tâches`.
- Projet actif = `ProjectState.is_finished = 0` ou `NULL`, y compris `projectstates_id = 0`.
- Fields reste optionnel; `Module` / `Priorité` sont omis si non résolus.
- Les critères utilisateur sont groupés avant tout critère obligatoire du plugin.
- Le navigateur transporte un contexte, jamais une liste d’autorisations.
- Tri, pagination, limite et refresh AJAX recalculent le scope côté serveur.
- `Tâches de projet` est seulement masqué dans la barre d’onglets; sa route reste intacte.
- Les copies `js/projecttaskdashboard.js` et `public/js/projecttaskdashboard.js` restent strictement identiques.

---

## File Map

### Create

- `src/Project/ActiveProjectProvider.php` — projets actifs réellement visibles.
- `src/Navigation/ProjectTabsManager.php` — forcetabs techniques.
- `src/Navigation/ProjectDashboardUrl.php` — URL d’un projet directement sur le dashboard.
- `src/Navigation/ProjectMenuManager.php` — secteur principal `Projet`.
- `src/Navigation/ProjectMenuCacheInvalidator.php` — invalidation de `$_SESSION['glpimenu']`.
- `src/Navigation/ProjectTaskCreateLink.php` — URL native et droit CREATE.
- `templates/dashboard/header.html.twig` — action `Ajouter une tâche`.
- `src/Search/TaskIdCriteriaBuilder.php` — critère ID de tâche, fail-closed inclus.
- `src/Search/MyTasksScopeProvider.php` — intersection affectations × projets actifs visibles.
- `src/Search/MyTasksCriteriaGuard.php` — garde obligatoire de la vue globale.
- `src/Search/ScopedProjectTaskSearchSession.php` — session de recherche générique par scope.
- `src/Search/MyTasksSearchSession.php` — session dédiée `mytasks`.
- `src/Search/MyTasksSearchAdapter.php` — QueryBuilder/SearchEngine global.
- `src/Search/MyTasksAjaxSearchContext.php` — garde AJAX `ptd_scope=mytasks`.
- `src/MyTasksRenderer.php` — rendu page globale.
- `front/mytasks.php` — route exacte `/plugins/projecttaskdashboard/front/mytasks.php`.
- tests dédiés sous `tests/`.

### Modify

- `setup.php`
- `hook.php`
- `src/DashboardRenderer.php`
- `src/Search/DashboardSearchSession.php`
- `src/Search/MineCriteriaExpander.php`
- `js/projecttaskdashboard.js`
- `public/js/projecttaskdashboard.js`
- `README.md` après validation runtime.

---

### Task 1: ActiveProjectProvider — une seule définition des projets en cours visibles

**Files:**
- Create: `src/Project/ActiveProjectProvider.php`
- Test: `tests/active_project_provider.php`

**Interfaces:**
- Produces: `ActiveProjectProvider::all(): array<int,array{id:int,name:string}>`
- Produces: `ActiveProjectProvider::ids(): array<int,int>`

- [ ] **Step 1: Write the failing test**

Créer des stubs globaux `Project`, `ProjectState` et DB. Les lignes DB comprennent : actif visible, terminé visible, actif invisible, projet sans état. Le test attendu :

```php
$p = new ActiveProjectProvider();
assert($p->all() === [
    ['id' => 4, 'name' => 'Alpha'],
    ['id' => 1, 'name' => 'Zulu'],
]);
assert($p->ids() === [4, 1]);
```

Le stub `Project::canViewItem()` retourne la visibilité configurée par ID.

- [ ] **Step 2: Run RED**

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/active_project_provider.php
```

Expected: FAIL, classe absente.

- [ ] **Step 3: Implement**

Créer `GlpiPlugin\Projecttaskdashboard\Project\ActiveProjectProvider`.

La requête candidate :

```php
$rows = $DB->request([
    'SELECT' => [
        Project::getTable() . '.id',
        Project::getTable() . '.name',
        ProjectState::getTable() . '.is_finished',
    ],
    'FROM' => Project::getTable(),
    'LEFT JOIN' => [
        ProjectState::getTable() => [
            'ON' => [
                Project::getTable() => 'projectstates_id',
                ProjectState::getTable() => 'id',
            ],
        ],
    ],
    'WHERE' => [Project::getTable() . '.is_deleted' => 0],
]);
```

Pour chaque ligne : exclure uniquement `is_finished == 1`, charger un `Project` par ID, exiger `canViewItem() === true`, normaliser le nom, puis trier `strnatcasecmp(name)` avec `id` comme tie-breaker. Un nom vide devient `Projet #<id>`.

- [ ] **Step 4: Run GREEN**

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/active_project_provider.php
php -l src/Project/ActiveProjectProvider.php
```

- [ ] **Step 5: Commit**

```bash
git add src/Project/ActiveProjectProvider.php tests/active_project_provider.php
git commit -m "feat: centralize visible active projects"
```

---

### Task 2: Navigation helpers — forcetabs et URL dashboard

**Files:**
- Create: `src/Navigation/ProjectTabsManager.php`
- Create: `src/Navigation/ProjectDashboardUrl.php`
- Modify: `src/DashboardRenderer.php`
- Test: `tests/project_navigation_contract.php`

**Interfaces:**
- `ProjectTabsManager::mainForcetab(): string`
- `ProjectTabsManager::dashboardForcetab(): string`
- `ProjectTabsManager::nativeTasksPrefix(): string`
- `ProjectDashboardUrl::__construct(ProjectTabsManager $tabs = new ProjectTabsManager())`
- `ProjectDashboardUrl::forProjectId(int $projectId): string`

- [ ] **Step 1: Write failing test with correct namespaced DashboardTab stub**

```php
<?php

declare(strict_types=1);

class Project {
    public static function getFormURLWithID(int $id): string {
        return '/front/project.form.php?id=' . $id;
    }
}
class ProjectTask {}
class DashboardTabStub {}
class_alias(DashboardTabStub::class, 'GlpiPlugin\\Projecttaskdashboard\\DashboardTab');

require __DIR__ . '/../src/Navigation/ProjectTabsManager.php';
require __DIR__ . '/../src/Navigation/ProjectDashboardUrl.php';

use GlpiPlugin\Projecttaskdashboard\Navigation\ProjectDashboardUrl;
use GlpiPlugin\Projecttaskdashboard\Navigation\ProjectTabsManager;

$tabs = new ProjectTabsManager();
assert($tabs->mainForcetab() === 'Project$main');
assert($tabs->nativeTasksPrefix() === 'ProjectTask$');
assert(str_ends_with($tabs->dashboardForcetab(), 'DashboardTab$1'));
$url = (new ProjectDashboardUrl($tabs))->forProjectId(42);
assert(str_contains($url, 'id=42'));
assert(str_contains($url, 'forcetab='));
```

- [ ] **Step 2: Run RED**

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/project_navigation_contract.php
```

- [ ] **Step 3: Implement exact forcetab API**

```php
public function mainForcetab(): string { return Project::class . '$main'; }
public function dashboardForcetab(): string { return DashboardTab::class . '$1'; }
public function nativeTasksPrefix(): string { return ProjectTask::class . '$'; }
```

`ProjectDashboardUrl::forProjectId()` rejette `<= 0` par `InvalidArgumentException`, utilise `Project::getFormURLWithID($id)`, puis ajoute `forcetab=rawurlencode($tabs->dashboardForcetab())` avec le bon séparateur `?`/`&`.

Modifier `DashboardRenderer` pour ne plus reconstruire localement le forcetab/target.

- [ ] **Step 4: Run GREEN + renderer regression**

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/project_navigation_contract.php
php -d zend.assertions=1 -d assert.exception=1 tests/renderer_contract.php
php -l src/DashboardRenderer.php
```

- [ ] **Step 5: Commit**

```bash
git add src/Navigation/ProjectTabsManager.php src/Navigation/ProjectDashboardUrl.php src/DashboardRenderer.php tests/project_navigation_contract.php
git commit -m "refactor: centralize project dashboard navigation"
```

---

### Task 3: Secteur principal `Projet` + cache session

**Files:**
- Create: `src/Navigation/ProjectMenuManager.php`
- Create: `src/Navigation/ProjectMenuCacheInvalidator.php`
- Modify: `setup.php`
- Modify: `hook.php`
- Test: `tests/project_menu_contract.php`
- Test: `tests/project_menu_cache.php`

**Interfaces:**
- `ProjectMenuManager::__construct(ActiveProjectProvider $projects = new ActiveProjectProvider(), ProjectDashboardUrl $dashboardUrl = new ProjectDashboardUrl())`
- `ProjectMenuManager::redefine(array $menu): array`
- `ProjectMenuCacheInvalidator::invalidate(object $item): void`

- [ ] **Step 1: Write RED contract**

Le test source exige `Hooks::REDEFINE_MENUS`, les callbacks du `hook.php`, `ActiveProjectProvider`, les labels `📋 Projets` / `👤 Mes tâches`, et les URLs dashboard. Un second bloc avec stubs DB/Project instancie le vrai provider et vérifie l’ordre `Alpha`, `Zulu`.

Assertions minimales :

```php
assert(isset($out['projecttaskdashboard_project']));
$content = $out['projecttaskdashboard_project']['content'];
assert($content['projects']['title'] === '📋 Projets');
assert(array_key_exists('project_4', $content));
assert(array_key_exists('project_1', $content));
assert(array_key_exists('mytasks', $content));
```

- [ ] **Step 2: Run RED**

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/project_menu_contract.php
```

- [ ] **Step 3: Implement menu manager**

Si `Project::canView()` est faux, retourner le menu inchangé. Sinon créer :

```php
$sector = [
    'title' => 'Projet',
    'icon' => Project::getIcon(),
    'content' => [
        'projects' => [
            'title' => '📋 Projets',
            'page' => Project::getSearchURL(false),
            'icon' => Project::getIcon(),
        ],
    ],
];
```

Dans un `try/catch (Throwable)`, ajouter `project_<id>` pour chaque `ActiveProjectProvider::all()`; `page` vient de `ProjectDashboardUrl::forProjectId()`.

Après ce bloc, ajouter `mytasks` seulement si `ProjectTask::canView()`; page exacte : `/plugins/projecttaskdashboard/front/mytasks.php`.

En cas d’erreur dynamique, garder les entrées statiques autorisées.

- [ ] **Step 4: Register hooks**

Dans `setup.php` :

```php
$PLUGIN_HOOKS[Hooks::REDEFINE_MENUS]['projecttaskdashboard']
    = 'plugin_projecttaskdashboard_redefine_menus';
$PLUGIN_HOOKS[Hooks::ITEM_ADD]['projecttaskdashboard']
    = 'plugin_projecttaskdashboard_project_menu_changed';
$PLUGIN_HOOKS[Hooks::ITEM_UPDATE]['projecttaskdashboard']
    = 'plugin_projecttaskdashboard_project_menu_changed';
$PLUGIN_HOOKS[Hooks::ITEM_DELETE]['projecttaskdashboard']
    = 'plugin_projecttaskdashboard_project_menu_changed';
$PLUGIN_HOOKS[Hooks::ITEM_PURGE]['projecttaskdashboard']
    = 'plugin_projecttaskdashboard_project_menu_changed';
$PLUGIN_HOOKS[Hooks::ITEM_RESTORE]['projecttaskdashboard']
    = 'plugin_projecttaskdashboard_project_menu_changed';
```

Dans `hook.php` :

```php
function plugin_projecttaskdashboard_redefine_menus(array $menu): array
{
    return (new \GlpiPlugin\Projecttaskdashboard\Navigation\ProjectMenuManager())->redefine($menu);
}

function plugin_projecttaskdashboard_project_menu_changed($item): void
{
    (new \GlpiPlugin\Projecttaskdashboard\Navigation\ProjectMenuCacheInvalidator())->invalidate($item);
}
```

- [ ] **Step 5: Implement and test cache invalidation**

`ProjectMenuCacheInvalidator` fait seulement :

```php
if ($item instanceof Project || $item instanceof ProjectState) {
    unset($_SESSION['glpimenu']);
}
```

Ne jamais appeler `Html::generateMenuSession(true)` dans un lifecycle hook.

Test : Project/ProjectState effacent la clé; `stdClass` ne la touche pas.

- [ ] **Step 6: Run GREEN**

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/project_menu_contract.php
php -d zend.assertions=1 -d assert.exception=1 tests/project_menu_cache.php
php -l setup.php
php -l hook.php
```

- [ ] **Step 7: Commit**

```bash
git add setup.php hook.php src/Navigation/ProjectMenuManager.php src/Navigation/ProjectMenuCacheInvalidator.php tests/project_menu_contract.php tests/project_menu_cache.php
git commit -m "feat: add project main menu"
```

---

### Task 4: Action native `Ajouter une tâche`

**Files:**
- Create: `src/Navigation/ProjectTaskCreateLink.php`
- Create: `templates/dashboard/header.html.twig`
- Modify: `src/DashboardRenderer.php`
- Test: `tests/project_task_create_link.php`
- Test: `tests/dashboard_create_action_contract.php`

**Interfaces:**
- `ProjectTaskCreateLink::url(Project $project): ?string`

- [ ] **Step 1: Write RED rights/URL test**

Stubs : `Project::getID()`, `Project::canViewItem()`, `ProjectTask::canCreate()`, `ProjectTask::getFormURL(false)`.

```php
assert($link->url($project) === '/front/projecttask.form.php?projects_id=42');
ProjectTask::$canCreate = false;
assert($link->url($project) === null);
```

Tester aussi projet non visible et ID `0` => `null`.

- [ ] **Step 2: Run RED**

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/project_task_create_link.php
```

- [ ] **Step 3: Implement helper**

Ordre exact : ID persisté, `canViewItem()`, `ProjectTask::canCreate()`, puis URL native :

```php
$url = ProjectTask::getFormURL(false);
return $url . (str_contains($url, '?') ? '&' : '?')
    . 'projects_id=' . (int) $project->getID();
```

Aucune POST custom, aucun formulaire plugin, aucun bypass ACL.

- [ ] **Step 4: Render header**

`DashboardRenderer` affiche avant les widgets :

```php
TemplateRenderer::getInstance()->display(
    '@projecttaskdashboard/dashboard/header.html.twig',
    ['create_url' => $this->createLink->url($project)]
);
```

Twig :

```twig
<div class="d-flex justify-content-between align-items-center mb-3 ptd-dashboard-header">
  <div class="fw-semibold">Pilotage des tâches</div>
  {% if create_url %}
    <a class="btn btn-primary" href="{{ create_url }}">
      <i class="ti ti-plus me-1"></i>Ajouter une tâche
    </a>
  {% endif %}
</div>
```

- [ ] **Step 5: Explicitly keep GLPI post-create behavior**

Ne pas modifier `front/projecttask.form.php`. GLPI 11 redirige lui-même après `add`; la spec autorise donc le comportement standard si aucun retour dashboard fiable n’existe sans fork core.

- [ ] **Step 6: Run GREEN**

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/project_task_create_link.php
php -d zend.assertions=1 -d assert.exception=1 tests/dashboard_create_action_contract.php
php -d zend.assertions=1 -d assert.exception=1 tests/renderer_contract.php
php -l src/Navigation/ProjectTaskCreateLink.php
php -l src/DashboardRenderer.php
```

- [ ] **Step 7: Commit**

```bash
git add src/Navigation/ProjectTaskCreateLink.php src/DashboardRenderer.php templates/dashboard/header.html.twig tests/project_task_create_link.php tests/dashboard_create_action_contract.php
git commit -m "feat: add task creation action to dashboard"
```

---

### Task 5: Critères ID réutilisables + scope métier `Mes tâches`

**Files:**
- Create: `src/Search/TaskIdCriteriaBuilder.php`
- Create: `src/Search/MyTasksScopeProvider.php`
- Create: `src/Search/MyTasksCriteriaGuard.php`
- Modify: `src/Search/MineCriteriaExpander.php`
- Test: `tests/task_id_criteria_builder.php`
- Test: `tests/mytasks_scope_provider.php`
- Test: `tests/mytasks_criteria_guard.php`
- Regression: `tests/mine_workaround.php`

**Interfaces:**
- `TaskIdCriteriaBuilder::criterion(array $taskIds, ?string $link = null, bool $hidden = false): array`
- `MyTasksScopeProvider::__construct(MineTaskProvider $mineTasks = new MineTaskProvider(), ActiveProjectProvider $projects = new ActiveProjectProvider())`
- `MyTasksScopeProvider::taskIds(): array<int,int>`
- `MyTasksCriteriaGuard::__construct(TaskIdCriteriaBuilder $builder = new TaskIdCriteriaBuilder())`
- `MyTasksCriteriaGuard::force(array $userCriteria, array $allowedTaskIds): array`

- [ ] **Step 1: RED TaskIdCriteriaBuilder**

```php
$b = new TaskIdCriteriaBuilder();
assert($b->criterion([]) === [
    'field' => 99002,
    'searchtype' => 'equals',
    'value' => -1,
]);
assert($b->criterion([11])['value'] === 11);
$many = $b->criterion([11, 22], 'AND', true);
assert(($many['_hidden'] ?? false) === true);
assert(($many['criteria'][1]['link'] ?? null) === 'OR');
```

- [ ] **Step 2: Implement builder and refactor MineCriteriaExpander**

Le builder déduplique/filtre les IDs > 0. Zéro ID => critère impossible `FIELD_TASK_ID_INTERNAL = -1`. Plusieurs IDs => groupe d’enfants reliés par OR. `hidden=true` place `_hidden=true` sur le critère/groupe racine.

`MineCriteriaExpander` reçoit le builder au constructeur et supprime sa logique privée dupliquée.

- [ ] **Step 3: RED MyTasksScopeProvider**

Configurer `MineTaskProvider::taskIds()` => `[11,22,33]`, `ActiveProjectProvider::ids()` => `[1,2]`, DB fake => tâches 11/33. Attendu `[11,33]`.

Production : si l’une des deux listes est vide, retourner immédiatement `[]`. Sinon requête `ProjectTask::getTable()` :

```php
'WHERE' => [
    'id' => $mineTaskIds,
    'projects_id' => $activeProjectIds,
]
```

Dédupliquer les IDs retournés.

- [ ] **Step 4: RED MyTasksCriteriaGuard**

```php
$user = [
    ['field' => 1, 'searchtype' => 'contains', 'value' => 'ivant'],
    ['link' => 'OR', 'field' => 12, 'searchtype' => 'equals', 'value' => 1],
];
$out = $guard->force($user, [11,22]);
assert($out[0]['criteria'] === $user);
assert(($out[1]['link'] ?? 'AND') === 'AND');
assert(($out[1]['_hidden'] ?? false) === true);
```

Avec `[]`, la garde est toujours présente et vaut ID `-1`.

- [ ] **Step 5: Run GREEN + regression**

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/task_id_criteria_builder.php
php -d zend.assertions=1 -d assert.exception=1 tests/mytasks_scope_provider.php
php -d zend.assertions=1 -d assert.exception=1 tests/mytasks_criteria_guard.php
php -d zend.assertions=1 -d assert.exception=1 tests/mine_workaround.php
```

- [ ] **Step 6: Commit**

```bash
git add src/Search/TaskIdCriteriaBuilder.php src/Search/MyTasksScopeProvider.php src/Search/MyTasksCriteriaGuard.php src/Search/MineCriteriaExpander.php tests/task_id_criteria_builder.php tests/mytasks_scope_provider.php tests/mytasks_criteria_guard.php tests/mine_workaround.php
git commit -m "refactor: add reusable secured task scope criteria"
```

---

### Task 6: Sessions de recherche séparées `dashboard` / `mytasks`

**Files:**
- Create: `src/Search/ScopedProjectTaskSearchSession.php`
- Create: `src/Search/MyTasksSearchSession.php`
- Modify: `src/Search/DashboardSearchSession.php`
- Test: `tests/scoped_search_sessions.php`
- Regression: `tests/mine_workaround.php`
- Regression: `tests/ajax_runtime_scope_contract.php`

**Interfaces:**
- `class ScopedProjectTaskSearchSession`
- `ScopedProjectTaskSearchSession::__construct(string $scopeKey)`
- public inherited methods: `enter()`, `leave()`, `run(callable): mixed`, `setCriteria(array): void`
- `final class DashboardSearchSession extends ScopedProjectTaskSearchSession`
- `final class MyTasksSearchSession extends ScopedProjectTaskSearchSession`

- [ ] **Step 1: RED isolation test**

Initialiser la recherche native avec field 87. Dans dashboard enregistrer field 12; dans mytasks enregistrer field 1. Après chaque scope, la recherche native doit redevenir field 87.

```php
assert($_SESSION['glpisearch'][ProjectTask::class]['criteria'][0]['field'] === 87);
assert($_SESSION['projecttaskdashboard']['search_scopes']['dashboard']['search']['criteria'][0]['field'] === 12);
assert($_SESSION['projecttaskdashboard']['search_scopes']['mytasks']['search']['criteria'][0]['field'] === 1);
```

Tester restauration identique après exception.

- [ ] **Step 2: Run RED**

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/scoped_search_sessions.php
```

- [ ] **Step 3: Implement exact inheritance**

`ScopedProjectTaskSearchSession` refuse une clé vide et stocke :

```text
$_SESSION['projecttaskdashboard']['search_scopes'][$scopeKey]['search']
$_SESSION['projecttaskdashboard']['search_scopes'][$scopeKey]['loaded_savedsearch']
```

Il snapshot/restaure exactement :

```text
$_SESSION['glpisearch'][ProjectTask::class]
$_SESSION['glpi_loaded_savedsearch']
```

Wrappers :

```php
final class DashboardSearchSession extends ScopedProjectTaskSearchSession
{
    public function __construct() { parent::__construct('dashboard'); }
}

final class MyTasksSearchSession extends ScopedProjectTaskSearchSession
{
    public function __construct() { parent::__construct('mytasks'); }
}
```

- [ ] **Step 4: Run GREEN + regressions**

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/scoped_search_sessions.php
php -d zend.assertions=1 -d assert.exception=1 tests/mine_workaround.php
php -d zend.assertions=1 -d assert.exception=1 tests/ajax_runtime_scope_contract.php
```

- [ ] **Step 5: Commit**

```bash
git add src/Search/ScopedProjectTaskSearchSession.php src/Search/DashboardSearchSession.php src/Search/MyTasksSearchSession.php tests/scoped_search_sessions.php
git commit -m "refactor: isolate project task search scopes"
```

---

### Task 7: Page globale `👤 Mes tâches`

**Files:**
- Create: `src/Search/MyTasksSearchAdapter.php`
- Create: `src/MyTasksRenderer.php`
- Create: `front/mytasks.php`
- Test: `tests/mytasks_search_adapter_contract.php`
- Test: `tests/mytasks_renderer_contract.php`

**Interfaces:**
- `MyTasksSearchAdapter::readUserParams(array $request): array`
- `MyTasksSearchAdapter::buildExecutionParams(array $userParams): array`
- `MyTasksSearchAdapter::defaultColumns(): array`
- `MyTasksSearchAdapter::render(array $userParams, string $target): void`
- `MyTasksRenderer::render(): void`

- [ ] **Step 1: RED adapter contract**

```php
assert(str_contains($adapter, 'MyTasksScopeProvider'));
assert(str_contains($adapter, 'MyTasksCriteriaGuard'));
assert(str_contains($adapter, 'MyTasksSearchSession'));
assert(str_contains($adapter, "'ptd_scope' => 'mytasks'"));
assert(str_contains($adapter, "'usesession' => 0"));
assert(str_contains($adapter, 'ProjectSearchRightsScope'));
```

- [ ] **Step 2: Implement adapter**

Réutiliser `QueryBuilder`, `SearchEngine`, `DisplayPreference`, `FieldsIntegration`, `SearchFormPreferenceScope`, `ProjectSearchRightsScope`.

`readUserParams()` s’exécute dans `MyTasksSearchSession`.

`buildExecutionParams()` :

1. `allowedTaskIds = MyTasksScopeProvider::taskIds()`;
2. expand les markers `FIELD_MINE_MARKER` avec ces mêmes IDs;
3. appelle `MyTasksCriteriaGuard::force($expandedUserCriteria, $allowedTaskIds)`.

`render()` ajoute aux hidden inputs :

```php
'ptd_scope' => 'mytasks',
'usesession' => 0,
```

Colonnes par défaut, dans cet ordre :

```php
[
    1,
    Config::FIELD_PROJECT,
    14,
    12,
    $priority,
    $module,
    5,
    8,
    11,
    13,
    87,
    88,
    19,
]
```

Après filtrage des `null` Fields. `SearchEngine::showOutput()` reste dans `ProjectSearchRightsScope::run()`; la garde ID obligatoire limite déjà le périmètre aux projets visibles actifs.

- [ ] **Step 3: Implement route exact**

`front/mytasks.php` :

```php
<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/inc/includes.php';

Session::checkCentralAccess();
Html::header('Mes tâches', $_SERVER['PHP_SELF'], 'projecttaskdashboard_project');
(new \GlpiPlugin\Projecttaskdashboard\MyTasksRenderer())->render();
Html::footer();
```

`MyTasksRenderer` exige `ProjectTask::canView()`. Il wrappe la recherche :

```html
<div class="projecttaskdashboard-mytasks"
     data-mytasks-target="/plugins/projecttaskdashboard/front/mytasks.php">
```

et affiche le titre `👤 Mes tâches` puis l’adapter.

- [ ] **Step 4: Run GREEN**

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/mytasks_search_adapter_contract.php
php -d zend.assertions=1 -d assert.exception=1 tests/mytasks_renderer_contract.php
php -l src/Search/MyTasksSearchAdapter.php
php -l src/MyTasksRenderer.php
php -l front/mytasks.php
```

- [ ] **Step 5: Commit**

```bash
git add src/Search/MyTasksSearchAdapter.php src/MyTasksRenderer.php front/mytasks.php tests/mytasks_search_adapter_contract.php tests/mytasks_renderer_contract.php
git commit -m "feat: add global my tasks view"
```

---

### Task 8: AJAX natif sécurisé pour `ptd_scope=mytasks`

**Files:**
- Create: `src/Search/MyTasksAjaxSearchContext.php`
- Modify: `setup.php`
- Test: `tests/mytasks_ajax_context.php`
- Regression: `tests/ajax_context_contract.php`
- Regression: `tests/ajax_runtime_scope_contract.php`

**Interfaces:**
- `MyTasksAjaxSearchContext::activateFromRequest(array &$request): void`
- `MyTasksAjaxSearchContext::restore(): void`

- [ ] **Step 1: RED runtime contract**

Le test source impose : `display_results`, `ProjectTask::class`, `ptd_scope === mytasks`, `MyTasksScopeProvider`, `MyTasksCriteriaGuard`, `MyTasksSearchSession`, les trois `enter()`, `usesession=0`, `register_shutdown_function`.

- [ ] **Step 2: Run RED**

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/mytasks_ajax_context.php
```

- [ ] **Step 3: Implement context**

Activation uniquement si :

```php
($request['action'] ?? '') === 'display_results'
&& ($request['itemtype'] ?? '') === ProjectTask::class
&& ($request['ptd_scope'] ?? '') === 'mytasks'
```

Traitement :

1. lire `criteria` ou `[]`;
2. recalculer `allowedTaskIds` côté serveur;
3. expand marker `Mes tâches` avec ces IDs;
4. `$request['criteria'] = $guard->force(...)`;
5. `$request['usesession'] = 0`;
6. entrer `MyTasksSearchSession`, `SearchFormPreferenceScope`, `ProjectSearchRightsScope`;
7. `register_shutdown_function([$this, 'restore'])`;
8. sur exception, restaurer puis relancer;
9. `restore()` quitte en ordre inverse.

- [ ] **Step 4: Dispatch in setup.php without cross-trigger**

```php
if (($_REQUEST['ptd_scope'] ?? '') === 'mytasks') {
    (new MyTasksAjaxSearchContext())->activateFromRequest($_REQUEST);
} elseif (isset($_REQUEST['ptd_project_id'])) {
    (new DashboardAjaxSearchContext())->activateFromRequest($_REQUEST);
}
```

- [ ] **Step 5: Run GREEN + regressions**

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/mytasks_ajax_context.php
php -d zend.assertions=1 -d assert.exception=1 tests/ajax_context_contract.php
php -d zend.assertions=1 -d assert.exception=1 tests/ajax_runtime_scope_contract.php
php -l setup.php
php -l src/Search/MyTasksAjaxSearchContext.php
```

- [ ] **Step 6: Commit**

```bash
git add setup.php src/Search/MyTasksAjaxSearchContext.php tests/mytasks_ajax_context.php
git commit -m "feat: secure my tasks ajax refreshes"
```

---

### Task 9: Onglets Projet + SavedSearch + liens Projet depuis `Mes tâches`

**Files:**
- Modify: `js/projecttaskdashboard.js`
- Modify: `public/js/projecttaskdashboard.js`
- Create: `tests/project_tabs_contract.js`
- Modify: `tests/search_transport_contract.js`

**Interfaces:** Browser only; aucune route serveur n’est supprimée.

- [ ] **Step 1: RED JS contract**

```js
const fs = require('fs');
const path = require('path');
const src = fs.readFileSync(path.join(__dirname, '..', 'js', 'projecttaskdashboard.js'), 'utf8');

for (const needle of [
  'Project$main',
  'ProjectTask$',
  'DashboardTab$1',
  'normalizeProjectTabs',
  'normalizeMyTasksProjectLinks',
]) {
  if (!src.includes(needle)) throw new Error('missing ' + needle);
}
if (src.includes('Tâches de projet')) {
  throw new Error('must not match translated labels');
}
```

- [ ] **Step 2: Run RED**

```bash
node tests/project_tabs_contract.js
```

- [ ] **Step 3: Implement idempotent `normalizeProjectTabs(context)`**

Parser les `href` avec `new URL(anchor.href, window.location.origin)` et `searchParams.get('forcetab')`.

La fonction ne fait rien tant qu’elle n’a pas trouvé **dans la même barre d’onglets** :

- `Project$main`;
- le dashboard dont le forcetab se termine par `DashboardTab$1`.

Seulement alors :

- masquer le `<li>` dont forcetab commence par `ProjectTask$`;
- déplacer le `<li>` dashboard immédiatement après le `<li>` `Project$main`.

Cette double condition empêche le JS de masquer un onglet ProjectTask sur une autre page GLPI.

Exécuter à DOM ready et `glpi.tab.loaded.projecttaskdashboard`.

- [ ] **Step 4: Preserve SavedSearch on global page**

Handler :

```text
.projecttaskdashboard-mytasks .savedsearches-item a
```

Lire `savedsearches_id`, empêcher la navigation vers la liste native générique, puis naviguer vers :

```text
/plugins/projecttaskdashboard/front/mytasks.php?savedsearches_id=<id>
```

Ne pas utiliser `reloadTab()` ici.

- [ ] **Step 5: Rewrite Project links inside MyTasks results**

`normalizeMyTasksProjectLinks(context)` ne cible que les liens sous `.projecttaskdashboard-mytasks` dont l’URL est `/front/project.form.php` et possède `id`. Ajouter/écraser :

```js
url.searchParams.set(
  'forcetab',
  'GlpiPlugin\\Projecttaskdashboard\\DashboardTab$1'
);
anchor.href = url.toString();
```

Ainsi la colonne `Projet` ouvre directement `📊 Pilotage des tâches`, comme exigé par la spec.

Appeler cette normalisation au chargement initial et après l’événement de refresh/rechargement disponible; au minimum rappeler depuis le handler global `glpi.tab.loaded.projecttaskdashboard` et après DOM ready. Pendant validation runtime, confirmer aussi après un tri AJAX; si GLPI remplace le tableau sans émettre cet événement, attacher la normalisation au callback/mécanisme de refresh déjà utilisé par `Search.Table` plutôt que via polling.

- [ ] **Step 6: Keep root/public JS identical**

```bash
cmp js/projecttaskdashboard.js public/js/projecttaskdashboard.js
```

Expected: exit 0.

- [ ] **Step 7: Run JS regressions**

```bash
node tests/project_tabs_contract.js
node tests/search_transport_contract.js
php -d zend.assertions=1 -d assert.exception=1 tests/widget_reload.php
```

- [ ] **Step 8: Commit**

```bash
git add js/projecttaskdashboard.js public/js/projecttaskdashboard.js tests/project_tabs_contract.js tests/search_transport_contract.js
git commit -m "feat: streamline project navigation ui"
```

---

### Task 10: Full verification on GLPI 11.0.8 + documentation

**Files:**
- Modify after runtime success: `README.md`
- No new production behavior in this task.

- [ ] **Step 1: Syntax check all plugin PHP**

```bash
find src front -name '*.php' -print0 | xargs -0 -n1 php -l
php -l setup.php
php -l hook.php
```

Expected: all `No syntax errors detected`.

- [ ] **Step 2: Run existing and V1.1 tests**

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/smoke.php
php -d zend.assertions=1 -d assert.exception=1 tests/mine_workaround.php
php -d zend.assertions=1 -d assert.exception=1 tests/project_search_rights_scope.php
php -d zend.assertions=1 -d assert.exception=1 tests/renderer_contract.php
php -d zend.assertions=1 -d assert.exception=1 tests/ajax_context_contract.php
php -d zend.assertions=1 -d assert.exception=1 tests/ajax_criteria_guard.php
php -d zend.assertions=1 -d assert.exception=1 tests/ajax_runtime_scope_contract.php
php -d zend.assertions=1 -d assert.exception=1 tests/search_form_scope_contract.php
php -d zend.assertions=1 -d assert.exception=1 tests/search_rights_integration.php
php -d zend.assertions=1 -d assert.exception=1 tests/active_project_provider.php
php -d zend.assertions=1 -d assert.exception=1 tests/project_navigation_contract.php
php -d zend.assertions=1 -d assert.exception=1 tests/project_menu_contract.php
php -d zend.assertions=1 -d assert.exception=1 tests/project_menu_cache.php
php -d zend.assertions=1 -d assert.exception=1 tests/project_task_create_link.php
php -d zend.assertions=1 -d assert.exception=1 tests/dashboard_create_action_contract.php
php -d zend.assertions=1 -d assert.exception=1 tests/task_id_criteria_builder.php
php -d zend.assertions=1 -d assert.exception=1 tests/mytasks_scope_provider.php
php -d zend.assertions=1 -d assert.exception=1 tests/mytasks_criteria_guard.php
php -d zend.assertions=1 -d assert.exception=1 tests/scoped_search_sessions.php
php -d zend.assertions=1 -d assert.exception=1 tests/mytasks_search_adapter_contract.php
php -d zend.assertions=1 -d assert.exception=1 tests/mytasks_renderer_contract.php
php -d zend.assertions=1 -d assert.exception=1 tests/mytasks_ajax_context.php
node tests/project_tabs_contract.js
node tests/search_transport_contract.js
```

- [ ] **Step 3: Deploy test branch**

```bash
cd /var/www/html/glpi/plugins/projecttaskdashboard
git fetch origin
git checkout feature/project-task-dashboard-v1.1
git pull origin feature/project-task-dashboard-v1.1
su -s /bin/sh www-data -c 'php /var/www/html/glpi/bin/console cache:clear'
```

Puis `Ctrl+F5` navigateur.

- [ ] **Step 4: Runtime menu acceptance**

Valider : secteur `Projet`; `📋 Projets`; uniquement projets visibles non terminés; ordre alphabétique; disparition après passage en statut terminé; projet inaccessible absent; clic projet => dashboard; `👤 Mes tâches` seulement avec droit.

- [ ] **Step 5: Runtime tabs/create acceptance**

Valider :

```text
Projet
📊 Pilotage des tâches
Équipe projet
...
```

`Tâches de projet` n’est plus visible, mais une URL directe `forcetab=ProjectTask$...` fonctionne. `Ajouter une tâche` ouvre `/front/projecttask.form.php?projects_id=<id>` avec projet prérempli; créer réellement une tâche et vérifier le bon `projects_id`.

- [ ] **Step 6: Runtime `Mes tâches` security matrix**

Cas réels :

1. affectation utilisateur directe + projet actif visible => visible;
2. affectation groupe + projet actif visible => visible;
3. non affectée => absente;
4. affectée mais projet terminé => absente;
5. affectée mais projet inaccessible => absente.

Tester ensuite recherche texte, tri, tri inverse, pagination, limite, SavedSearch. Dans Network, `/ajax/search.php` doit inclure :

```text
ptd_scope: mytasks
usesession: 0
```

Le périmètre ne doit jamais s’élargir.

- [ ] **Step 7: Verify Project link after AJAX**

Depuis `Mes tâches`, cliquer le nom du projet avant puis après un tri AJAX; les deux doivent ouvrir le bon projet directement sur `📊 Pilotage des tâches`.

- [ ] **Step 8: Fields optional**

Avec Fields actif, vérifier `Module` / `Priorité`. Le fallback sans options résolues doit omettre ces colonnes sans fatal. Ne pas introduire de dépendance à Fields.

- [ ] **Step 9: Update README only with validated facts**

Documenter le menu principal, projets dynamiques, création de tâche, onglet natif masqué, vue globale sécurisée et l’environnement réellement testé. Ne pas déclarer exports/actions de masse comme validés sans les rejouer.

```bash
git add README.md
git commit -m "docs: document project dashboard v1.1"
```

- [ ] **Step 10: Fresh final verification**

Rejouer Steps 1–2 après le dernier commit et vérifier :

```bash
git status --short
```

Expected: vide. Ne déclarer la V1.1 terminée qu’après ces résultats frais + validation runtime.

---

## Dependency Order

```text
Task 1 ActiveProjectProvider
  ├─> Task 3 Project menu
  └─> Task 5 MyTasks scope

Task 2 Navigation helpers
  ├─> Task 3 Project menu
  ├─> Task 4 Create task
  └─> Task 9 Project links/tabs

Task 5 Secured task criteria
  └─> Task 7 MyTasks page
       └─> Task 8 MyTasks AJAX

Task 6 Scoped sessions
  ├─> Task 7 MyTasks page
  └─> Task 8 MyTasks AJAX

Tasks 1–9
  └─> Task 10 Runtime acceptance
```

## Security Review Checklist

- `ActiveProjectProvider` appelle `Project::canViewItem()` avant exposition.
- `MyTasksScopeProvider` calcule une intersection affectations × projets actifs visibles.
- liste vide => `FIELD_TASK_ID_INTERNAL = -1`, jamais absence de filtre.
- critères client groupés avant garde obligatoire.
- `ptd_scope=mytasks` n’est qu’un marqueur de contexte.
- IDs autorisés recalculés côté serveur à chaque AJAX.
- `ProjectSearchRightsScope` est temporaire et restaure les droits initiaux.
- sessions `dashboard` et `mytasks` sont indépendantes.
- aucune route native `ProjectTask` supprimée.
- aucun fichier du core GLPI modifié.

## Definition of Done

1. tous les tests et `php -l` sont verts;
2. menu principal validé sur GLPI réel;
3. onglets + création native validés;
4. `Mes tâches` passe les cinq cas de sécurité;
5. tri/pagination/limite AJAX restent bornés;
6. lien Projet vers dashboard reste correct après AJAX;
7. Fields absent/non résolu ne casse rien;
8. README ne décrit que les capacités réellement validées;
9. branche propre avant PR/merge.
