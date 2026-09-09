# GLPI Project Task Dashboard V1.1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Faire du plugin `projecttaskdashboard` le point d’entrée principal des projets/tâches GLPI 11 en ajoutant création de tâche depuis le dashboard, réorganisation visuelle des onglets, secteur principal `Projet` et vue globale sécurisée `Mes tâches`.

**Architecture:** La V1.1 reste sans patch core et sans table SQL propre au plugin. Les projets actifs et visibles sont centralisés dans un provider unique, la navigation/menu est isolée des renderers, et la vue globale `Mes tâches` réutilise le moteur natif `ProjectTask` avec un scope serveur obligatoire reconstruit aussi sur `/ajax/search.php`. Les scopes temporaires de recherche/droits sont isolés par contexte et restaurent exactement l’état de session initial.

**Tech Stack:** PHP 8.2+, GLPI 11.0.x, Twig GLPI, moteur natif `Glpi\Search`, JavaScript/jQuery GLPI, hooks plugins GLPI 11, plugin Fields optionnel.

**Spec:** `docs/superpowers/specs/2026-09-09-glpi-project-task-dashboard-v1.1-design.md`

## Global Constraints

- GLPI cible : `>= 11.0.0` et `< 12.0.0`.
- PHP : `>= 8.2.0`.
- Aucun patch du core GLPI.
- Aucun nouveau schéma SQL propre au plugin.
- Les ACL natives `Project` / `ProjectTask` restent l’autorité finale.
- Un projet invisible ne doit jamais apparaître dans le menu ni autoriser une tâche dans `Mes tâches`.
- Un projet est actif lorsque son `ProjectState.is_finished` vaut `0` ou `NULL`, y compris le cas sans état (`projectstates_id = 0`).
- Fields reste optionnel : l’absence de `Module` / `Priorité` ne doit jamais casser le rendu.
- Les critères utilisateur sont toujours groupés avant les critères de sécurité obligatoires.
- Les appels AJAX natifs de tri/pagination/limite doivent reconstruire le scope côté serveur.
- `Tâches de projet` est masqué visuellement uniquement ; sa route et son `forcetab` restent accessibles.
- Les assets servis par GLPI 11 restent présents sous `public/js` et `public/css`; les copies racine existantes `js/` et `css/` restent synchronisées pendant la V1.1.

---

## File Structure

### New files

- `src/Project/ActiveProjectProvider.php` — source unique des projets visibles + non terminés.
- `src/Navigation/ProjectTabsManager.php` — identifiants techniques des onglets Projet.
- `src/Navigation/ProjectDashboardUrl.php` — construction centralisée d’une URL ouvrant un projet sur le dashboard.
- `src/Navigation/ProjectMenuManager.php` — construction du nouveau secteur principal `Projet`.
- `src/Navigation/ProjectMenuCacheInvalidator.php` — invalidation du menu session après changement Projet/ProjectState.
- `src/Navigation/ProjectTaskCreateLink.php` — droit d’affichage et URL du formulaire natif de création.
- `templates/dashboard/header.html.twig` — bandeau du dashboard avec action `Ajouter une tâche`.
- `src/Search/TaskIdCriteriaBuilder.php` — critère ID de tâche réutilisable, y compris fail-closed `-1`.
- `src/Search/MyTasksScopeProvider.php` — intersection tâches assignées × projets actifs visibles.
- `src/Search/MyTasksCriteriaGuard.php` — groupe les critères utilisateur puis ajoute le scope obligatoire.
- `src/Search/ScopedProjectTaskSearchSession.php` — isolation générique d’un contexte de recherche `ProjectTask`.
- `src/Search/MyTasksSearchSession.php` — scope session dédié `mytasks`.
- `src/Search/MyTasksSearchAdapter.php` — QueryBuilder + SearchEngine de la vue globale.
- `src/Search/MyTasksAjaxSearchContext.php` — reconstruction serveur de `ptd_scope=mytasks` lors des refresh AJAX.
- `src/MyTasksRenderer.php` — rendu de la page globale.
- `front/mytasks.php` — route `/plugins/projecttaskdashboard/front/mytasks.php`.
- Tests dédiés sous `tests/` décrits dans chaque tâche.

### Modified files

- `setup.php` — hooks navigation, contexte AJAX global, assets.
- `hook.php` — callbacks `REDEFINE_MENUS` et invalidation lifecycle.
- `src/DashboardRenderer.php` — header de création + helpers de navigation.
- `src/Search/DashboardSearchSession.php` — wrapper du scope générique `dashboard`.
- `src/Search/MineCriteriaExpander.php` — délégation de la construction des critères ID.
- `js/projecttaskdashboard.js` et `public/js/projecttaskdashboard.js` — ordre/masquage onglets + SavedSearch de `Mes tâches`.
- `README.md` — fonctionnalités et tests V1.1 après validation runtime.

---

### Task 1: Centraliser les projets actifs et visibles

**Files:**
- Create: `src/Project/ActiveProjectProvider.php`
- Test: `tests/active_project_provider.php`

**Interfaces:**
- Produces: `ActiveProjectProvider::all(): array<int,array{id:int,name:string}>`
- Produces: `ActiveProjectProvider::ids(): array<int,int>`
- Used by: menu principal et `MyTasksScopeProvider`.

- [ ] **Step 1: Write the failing test**

Créer `tests/active_project_provider.php` avec des stubs GLPI isolés. Le test doit simuler quatre projets : visible actif, visible terminé, invisible actif, sans état. Le provider attendu retourne uniquement les projets visibles non terminés, triés alphabétiquement.

```php
<?php

declare(strict_types=1);

class ProjectState { public static function getTable(): string { return 'glpi_projectstates'; } }
class Project {
    public static array $rows = [];
    public static function getTable(): string { return 'glpi_projects'; }
    public function getFromDB(int $id): bool { $this->fields = self::$rows[$id]; return true; }
    public function canViewItem(): bool { return (bool) ($this->fields['visible'] ?? false); }
    public function getID(): int { return (int) $this->fields['id']; }
    public array $fields = [];
}
final class FakeDB {
    public function request(array $query): array {
        return [
            ['id' => 1, 'name' => 'Zulu', 'is_finished' => 0],
            ['id' => 2, 'name' => 'Clos', 'is_finished' => 1],
            ['id' => 3, 'name' => 'Secret', 'is_finished' => 0],
            ['id' => 4, 'name' => 'Alpha', 'is_finished' => null],
        ];
    }
}
$GLOBALS['DB'] = new FakeDB();
Project::$rows = [
    1 => ['id' => 1, 'visible' => true],
    2 => ['id' => 2, 'visible' => true],
    3 => ['id' => 3, 'visible' => false],
    4 => ['id' => 4, 'visible' => true],
];

require __DIR__ . '/../src/Project/ActiveProjectProvider.php';

use GlpiPlugin\Projecttaskdashboard\Project\ActiveProjectProvider;

$p = new ActiveProjectProvider();
assert($p->all() === [
    ['id' => 4, 'name' => 'Alpha'],
    ['id' => 1, 'name' => 'Zulu'],
]);
assert($p->ids() === [4, 1]);
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/active_project_provider.php
```

Expected: FAIL car `src/Project/ActiveProjectProvider.php` n’existe pas.

- [ ] **Step 3: Implement the provider**

Créer `GlpiPlugin\Projecttaskdashboard\Project\ActiveProjectProvider`.

La requête DB doit :

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

Puis, pour chaque ligne, exclure explicitement `is_finished = 1`, charger `Project`, appeler `canViewItem()`, normaliser `id/name`, puis trier avec `strnatcasecmp` sur `name` et `id` comme tie-breaker.

Ne pas faire confiance à une liste d’IDs transmise par le navigateur.

- [ ] **Step 4: Run test to verify it passes**

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/active_project_provider.php
php -l src/Project/ActiveProjectProvider.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Project/ActiveProjectProvider.php tests/active_project_provider.php
git commit -m "feat: centralize visible active projects"
```

---

### Task 2: Centraliser les identifiants d’onglets et URLs dashboard

**Files:**
- Create: `src/Navigation/ProjectTabsManager.php`
- Create: `src/Navigation/ProjectDashboardUrl.php`
- Test: `tests/project_navigation_contract.php`
- Modify: `src/DashboardRenderer.php`

**Interfaces:**
- Produces: `ProjectTabsManager::mainForcetab(): string`
- Produces: `ProjectTabsManager::dashboardForcetab(): string`
- Produces: `ProjectTabsManager::nativeTasksPrefix(): string`
- Produces: `ProjectDashboardUrl::forProjectId(int $projectId): string`

- [ ] **Step 1: Write the failing navigation test**

```php
<?php

declare(strict_types=1);

class Project {
    public static function getFormURLWithID(int $id): string { return '/front/project.form.php?id=' . $id; }
}
class ProjectTask {}
class CommonGLPI {}
class DashboardTab extends CommonGLPI {}

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

Expected: FAIL classes missing.

- [ ] **Step 3: Implement minimal navigation helpers**

`ProjectTabsManager` returns exactly:

```php
public function mainForcetab(): string { return Project::class . '$main'; }
public function dashboardForcetab(): string { return DashboardTab::class . '$1'; }
public function nativeTasksPrefix(): string { return ProjectTask::class . '$'; }
```

`ProjectDashboardUrl::forProjectId()` rejects `<= 0` with `InvalidArgumentException`, calls `Project::getFormURLWithID($id)`, puis ajoute `forcetab=<urlencoded dashboardForcetab>` en respectant `?`/`&`.

Modifier `DashboardRenderer` pour utiliser `ProjectTabsManager` / `ProjectDashboardUrl` au lieu de reconstruire localement `DashboardTab::class . '$1'` et l’URL du projet.

- [ ] **Step 4: Run GREEN + regression renderer**

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/project_navigation_contract.php
php -d zend.assertions=1 -d assert.exception=1 tests/renderer_contract.php
php -l src/DashboardRenderer.php
```

- [ ] **Step 5: Commit**

```bash
git add src/Navigation src/DashboardRenderer.php tests/project_navigation_contract.php
git commit -m "refactor: centralize project dashboard navigation"
```

---

### Task 3: Ajouter le secteur principal `Projet` et invalider son cache

**Files:**
- Create: `src/Navigation/ProjectMenuManager.php`
- Create: `src/Navigation/ProjectMenuCacheInvalidator.php`
- Modify: `setup.php`
- Modify: `hook.php`
- Test: `tests/project_menu_contract.php`
- Test: `tests/project_menu_cache.php`

**Interfaces:**
- Consumes: `ActiveProjectProvider::all()` and `ProjectDashboardUrl::forProjectId()`.
- Produces: `ProjectMenuManager::redefine(array $menu): array`.
- Produces: `ProjectMenuCacheInvalidator::invalidate(object $item): void`.

- [ ] **Step 1: Write failing menu contract**

Le test source doit imposer les hooks et les entrées statiques, tandis qu’un test avec stubs vérifie l’ordre dynamique.

```php
$setup = file_get_contents(__DIR__ . '/../setup.php');
$hook = file_get_contents(__DIR__ . '/../hook.php');
$manager = @file_get_contents(__DIR__ . '/../src/Navigation/ProjectMenuManager.php');

assert(str_contains($setup, 'Hooks::REDEFINE_MENUS'));
assert(str_contains($hook, 'plugin_projecttaskdashboard_redefine_menus'));
assert(str_contains($manager, "'📋 Projets'"));
assert(str_contains($manager, "'👤 Mes tâches'"));
assert(str_contains($manager, 'ActiveProjectProvider'));
```

Ajouter dans le même test une instance de manager avec un provider fake retournant `Alpha`, `Zulu`; vérifier que les clés dynamiques sont dans cet ordre et que chaque `page` contient le bon `forcetab`.

- [ ] **Step 2: Run RED**

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/project_menu_contract.php
```

Expected: FAIL.

- [ ] **Step 3: Implement `ProjectMenuManager`**

Le secteur doit utiliser une clé dédiée, par exemple `projecttaskdashboard_project`, avec structure GLPI 11 :

```php
$menu['projecttaskdashboard_project'] = [
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

Si l’utilisateur ne peut pas consulter les projets, retourner `$menu` inchangé. Ajouter ensuite `project_<id>` pour chaque projet de `ActiveProjectProvider::all()`. Ajouter `mytasks` seulement si `ProjectTask` est consultable; sa page vaut exactement `/plugins/projecttaskdashboard/front/mytasks.php`.

Encapsuler le chargement dynamique des projets dans `try/catch (Throwable)`: en cas d’erreur, conserver le secteur avec les entrées statiques autorisées.

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

`ProjectMenuCacheInvalidator` invalide uniquement pour `Project` et `ProjectState` en faisant `unset($_SESSION['glpimenu']);`. Ne jamais appeler `Html::generateMenuSession(true)` depuis ces callbacks.

- [ ] **Step 5: Write and run cache test**

```php
$_SESSION['glpimenu'] = ['cached' => true];
(new ProjectMenuCacheInvalidator())->invalidate(new Project());
assert(!array_key_exists('glpimenu', $_SESSION));

$_SESSION['glpimenu'] = ['cached' => true];
(new ProjectMenuCacheInvalidator())->invalidate(new stdClass());
assert(isset($_SESSION['glpimenu']));
```

Run:

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/project_menu_contract.php
php -d zend.assertions=1 -d assert.exception=1 tests/project_menu_cache.php
php -l setup.php
php -l hook.php
```

- [ ] **Step 6: Commit**

```bash
git add setup.php hook.php src/Navigation/ProjectMenuManager.php src/Navigation/ProjectMenuCacheInvalidator.php tests/project_menu_contract.php tests/project_menu_cache.php
git commit -m "feat: add project main menu"
```

---

### Task 4: Ajouter `+ Ajouter une tâche` au dashboard

**Files:**
- Create: `src/Navigation/ProjectTaskCreateLink.php`
- Create: `templates/dashboard/header.html.twig`
- Modify: `src/DashboardRenderer.php`
- Test: `tests/project_task_create_link.php`
- Test: `tests/dashboard_create_action_contract.php`

**Interfaces:**
- Produces: `ProjectTaskCreateLink::url(Project $project): ?string`.

- [ ] **Step 1: Write failing URL/rights test**

Stub `ProjectTask::canCreate()` et `ProjectTask::getFormURL(false)` pour deux cas. Vérifier :

```php
assert($link->url($project) === '/front/projecttask.form.php?projects_id=42');
ProjectTask::$canCreate = false;
assert($link->url($project) === null);
```

Le helper doit aussi retourner `null` si le projet n’est pas persisté ou pas visible.

- [ ] **Step 2: Run RED**

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/project_task_create_link.php
```

- [ ] **Step 3: Implement `ProjectTaskCreateLink`**

Le service :

1. vérifie `$project->getID() > 0`;
2. vérifie `$project->canViewItem()`;
3. vérifie `(new ProjectTask())->canCreate()`;
4. construit l’URL native via `ProjectTask::getFormURL(false)` + `projects_id=<id>`.

Aucune POST custom, aucune duplication du formulaire, aucun bypass ACL.

- [ ] **Step 4: Render the dashboard header**

Dans `DashboardRenderer`, calculer `$createUrl = $this->createLink->url($project)` puis afficher avant les widgets :

```php
TemplateRenderer::getInstance()->display(
    '@projecttaskdashboard/dashboard/header.html.twig',
    ['project' => $project, 'create_url' => $createUrl]
);
```

Template :

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

- [ ] **Step 5: Run GREEN + renderer regression**

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/project_task_create_link.php
php -d zend.assertions=1 -d assert.exception=1 tests/dashboard_create_action_contract.php
php -d zend.assertions=1 -d assert.exception=1 tests/renderer_contract.php
php -l src/Navigation/ProjectTaskCreateLink.php
php -l src/DashboardRenderer.php
```

- [ ] **Step 6: Commit**

```bash
git add src/Navigation/ProjectTaskCreateLink.php src/DashboardRenderer.php templates/dashboard/header.html.twig tests/project_task_create_link.php tests/dashboard_create_action_contract.php
git commit -m "feat: add task creation action to dashboard"
```

---

### Task 5: Extraire la construction des critères ID et créer le scope sécurisé `Mes tâches`

**Files:**
- Create: `src/Search/TaskIdCriteriaBuilder.php`
- Create: `src/Search/MyTasksScopeProvider.php`
- Create: `src/Search/MyTasksCriteriaGuard.php`
- Modify: `src/Search/MineCriteriaExpander.php`
- Test: `tests/task_id_criteria_builder.php`
- Test: `tests/mytasks_scope_provider.php`
- Test: `tests/mytasks_criteria_guard.php`
- Modify test: `tests/mine_workaround.php`

**Interfaces:**
- Produces: `TaskIdCriteriaBuilder::criterion(array $taskIds, ?string $link = null, bool $hidden = false): array`.
- Produces: `MyTasksScopeProvider::taskIds(): array<int,int>`.
- Produces: `MyTasksCriteriaGuard::force(array $userCriteria, array $allowedTaskIds): array`.

- [ ] **Step 1: Write failing builder test**

Vérifier exactement :

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

- [ ] **Step 2: Run RED, then implement builder and delegate from `MineCriteriaExpander`**

`MineCriteriaExpander::taskIdCriterion()` doit disparaître au profit du builder injecté au constructeur. Rejouer `tests/mine_workaround.php` pour garantir l’absence de régression.

- [ ] **Step 3: Write failing scope-provider test**

Avec un `MineTaskProvider` fake retournant `[11,22,33]` et un `ActiveProjectProvider` fake retournant IDs `[1,2]`, le faux DB renvoie seulement tâches `11` et `33`; vérifier que `MyTasksScopeProvider::taskIds()` vaut `[11,33]`.

Le provider production doit échouer fermé : si la liste de tâches assignées ou de projets actifs visibles est vide, retourner `[]` sans requête large.

La requête finale filtre `ProjectTask::getTable()` sur :

```php
'WHERE' => [
    'id' => $mineTaskIds,
    'projects_id' => $activeProjectIds,
]
```

- [ ] **Step 4: Write failing guard test**

```php
$user = [
    ['field' => 1, 'searchtype' => 'contains', 'value' => 'ivant'],
    ['link' => 'OR', 'field' => 12, 'searchtype' => 'equals', 'value' => 1],
];
$out = $guard->force($user, [11, 22]);
assert($out[0]['criteria'] === $user);
assert(($out[1]['_hidden'] ?? false) === true);
assert(($out[1]['link'] ?? 'AND') === 'AND');
```

Avec `allowedTaskIds=[]`, le second critère doit forcer `FIELD_TASK_ID_INTERNAL = -1`.

- [ ] **Step 5: Run GREEN**

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

### Task 6: Isoler les sessions de recherche `dashboard` et `mytasks`

**Files:**
- Create: `src/Search/ScopedProjectTaskSearchSession.php`
- Create: `src/Search/MyTasksSearchSession.php`
- Modify: `src/Search/DashboardSearchSession.php`
- Test: `tests/scoped_search_sessions.php`
- Regression: `tests/mine_workaround.php`
- Regression: `tests/ajax_runtime_scope_contract.php`

**Interfaces:**
- Produces base methods: `enter()`, `leave()`, `run(callable): mixed`, `setCriteria(array): void`.
- `DashboardSearchSession` uses namespace `dashboard`.
- `MyTasksSearchSession` uses namespace `mytasks`.

- [ ] **Step 1: Write failing isolation test**

Le test initialise une recherche native `ProjectTask` avec field `87`, entre dans `DashboardSearchSession`, pose field `12`, sort, puis entre dans `MyTasksSearchSession`, pose field `1`, sort. Assertions :

```php
assert($_SESSION['glpisearch'][ProjectTask::class]['criteria'][0]['field'] === 87);
assert($_SESSION['projecttaskdashboard']['search_scopes']['dashboard']['search']['criteria'][0]['field'] === 12);
assert($_SESSION['projecttaskdashboard']['search_scopes']['mytasks']['search']['criteria'][0]['field'] === 1);
```

Tester aussi restauration après exception.

- [ ] **Step 2: Run RED**

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/scoped_search_sessions.php
```

- [ ] **Step 3: Move current `DashboardSearchSession` mechanics into generic scope**

`ScopedProjectTaskSearchSession` reçoit au constructeur un `$scopeKey` non vide et stocke :

```text
$_SESSION['projecttaskdashboard']['search_scopes'][$scopeKey]['search']
$_SESSION['projecttaskdashboard']['search_scopes'][$scopeKey]['loaded_savedsearch']
```

Les snapshots natifs restent exactement `$_SESSION['glpisearch'][ProjectTask::class]` et `$_SESSION['glpi_loaded_savedsearch']`.

`DashboardSearchSession` devient un wrapper mince qui construit le parent/composant avec `dashboard`; `MyTasksSearchSession` avec `mytasks`.

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

### Task 7: Construire la page globale `Mes tâches`

**Files:**
- Create: `src/Search/MyTasksSearchAdapter.php`
- Create: `src/MyTasksRenderer.php`
- Create: `front/mytasks.php`
- Test: `tests/mytasks_renderer_contract.php`
- Test: `tests/mytasks_search_adapter_contract.php`

**Interfaces:**
- `MyTasksSearchAdapter::readUserParams(array $request): array`
- `MyTasksSearchAdapter::buildExecutionParams(array $userParams): array`
- `MyTasksSearchAdapter::defaultColumns(): array`
- `MyTasksSearchAdapter::render(array $userParams, string $target): void`
- `MyTasksRenderer::render(): void`

- [ ] **Step 1: Write failing adapter contract**

Le test doit imposer :

```php
assert(str_contains($adapter, 'MyTasksScopeProvider'));
assert(str_contains($adapter, 'MyTasksCriteriaGuard'));
assert(str_contains($adapter, 'MyTasksSearchSession'));
assert(str_contains($adapter, "'ptd_scope' => 'mytasks'"));
assert(str_contains($adapter, "'usesession' => 0"));
assert(str_contains($adapter, 'ProjectSearchRightsScope'));
```

- [ ] **Step 2: Implement `MyTasksSearchAdapter`**

Réutiliser les mêmes primitives que `NativeSearchAdapter` : `QueryBuilder`, `SearchEngine`, `DisplayPreference`, `SearchFormPreferenceScope`, `ProjectSearchRightsScope`, FieldsIntegration.

Différences obligatoires :

- aucun projet unique forcé;
- `buildExecutionParams()` récupère `allowedTaskIds = MyTasksScopeProvider::taskIds()` et appelle `MyTasksCriteriaGuard::force(...)`;
- si un critère utilisateur contient le marker `FIELD_MINE_MARKER`, l’expander le traite avec la même liste `allowedTaskIds`;
- `addhidden` contient `ptd_scope=mytasks` et `usesession=0`;
- colonnes par défaut : `1, Config::FIELD_PROJECT, 14, 12, priority, module, 5, 8, 11, 13, 87, 88, 19`.

L’exécution `SearchEngine::showOutput()` reste dans `ProjectSearchRightsScope::run()`, car le scope obligatoire par IDs a déjà été limité aux projets réellement visibles.

- [ ] **Step 3: Implement renderer and route**

`front/mytasks.php` doit charger GLPI avec le chemin relatif standard du plugin, vérifier la session centrale, appeler :

```php
Html::header('Mes tâches', $_SERVER['PHP_SELF'], 'projecttaskdashboard_project');
(new \GlpiPlugin\Projecttaskdashboard\MyTasksRenderer())->render();
Html::footer();
```

`MyTasksRenderer` refuse l’accès si l’utilisateur ne peut pas consulter `ProjectTask`, puis affiche :

```html
<div class="projecttaskdashboard-mytasks" data-mytasks-target="/plugins/projecttaskdashboard/front/mytasks.php">
```

un titre `👤 Mes tâches`, puis `MyTasksSearchAdapter`.

- [ ] **Step 4: Run tests**

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/mytasks_renderer_contract.php
php -d zend.assertions=1 -d assert.exception=1 tests/mytasks_search_adapter_contract.php
php -l src/Search/MyTasksSearchAdapter.php
php -l src/MyTasksRenderer.php
php -l front/mytasks.php
```

- [ ] **Step 5: Commit**

```bash
git add src/Search/MyTasksSearchAdapter.php src/MyTasksRenderer.php front/mytasks.php tests/mytasks_renderer_contract.php tests/mytasks_search_adapter_contract.php
git commit -m "feat: add global my tasks view"
```

---

### Task 8: Sécuriser tri/pagination AJAX de `Mes tâches`

**Files:**
- Create: `src/Search/MyTasksAjaxSearchContext.php`
- Modify: `setup.php`
- Test: `tests/mytasks_ajax_context.php`
- Regression: `tests/ajax_context_contract.php`
- Regression: `tests/ajax_runtime_scope_contract.php`

**Interfaces:**
- Produces: `MyTasksAjaxSearchContext::activateFromRequest(array &$request): void`.
- Produces: `MyTasksAjaxSearchContext::restore(): void`.

- [ ] **Step 1: Write failing runtime contract**

Le test source doit exiger :

```php
assert(str_contains($ctx, "($request['ptd_scope'] ?? '') === 'mytasks'"));
assert(str_contains($ctx, "'display_results'"));
assert(str_contains($ctx, 'ProjectTask::class'));
assert(str_contains($ctx, 'MyTasksScopeProvider'));
assert(str_contains($ctx, 'MyTasksCriteriaGuard'));
assert(str_contains($ctx, 'myTasksSession->enter()'));
assert(str_contains($ctx, 'projectSearchRights->enter()'));
assert(str_contains($ctx, 'searchFormPreference->enter()'));
assert(str_contains($ctx, "['usesession'] = 0"));
assert(str_contains($ctx, 'register_shutdown_function'));
```

- [ ] **Step 2: Run RED**

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/mytasks_ajax_context.php
```

- [ ] **Step 3: Implement AJAX context**

Le contexte ne s’active que si :

```php
($request['action'] ?? '') === 'display_results'
&& ($request['itemtype'] ?? '') === ProjectTask::class
&& ($request['ptd_scope'] ?? '') === 'mytasks'
```

Algorithme exact :

1. lire `criteria` utilisateur;
2. recalculer `allowedTaskIds` via `MyTasksScopeProvider` côté serveur;
3. expand les markers `Mes tâches` avec cette même liste;
4. remplacer `$request['criteria']` par `MyTasksCriteriaGuard::force(...)`;
5. forcer `$request['usesession'] = 0`;
6. entrer `MyTasksSearchSession`, `SearchFormPreferenceScope`, `ProjectSearchRightsScope`;
7. enregistrer `restore()` au shutdown;
8. restaurer en ordre inverse, même en exception.

- [ ] **Step 4: Dispatch from `setup.php` without breaking dashboard AJAX**

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

### Task 9: Réordonner/masquer les onglets Projet et conserver les SavedSearch de `Mes tâches`

**Files:**
- Modify: `js/projecttaskdashboard.js`
- Modify: `public/js/projecttaskdashboard.js`
- Test: `tests/project_tabs_contract.js`
- Modify: `tests/search_transport_contract.js`

**Interfaces:**
- Browser behavior only; no change to native route `ProjectTask`.

- [ ] **Step 1: Write failing JS contract for technical forcetabs**

Créer `tests/project_tabs_contract.js` :

```js
const fs = require('fs');
const src = fs.readFileSync(require('path').join(__dirname, '..', 'js', 'projecttaskdashboard.js'), 'utf8');
const required = [
  'Project$main',
  'ProjectTask$',
  'DashboardTab$1',
  'normalizeProjectTabs',
  'URLSearchParams',
];
for (const needle of required) {
  if (!src.includes(needle)) throw new Error('missing ' + needle);
}
if (src.includes("Tâches de projet")) throw new Error('must not match translated tab label');
console.log('project tabs contract ok');
```

- [ ] **Step 2: Run RED**

```bash
node tests/project_tabs_contract.js
```

- [ ] **Step 3: Implement `normalizeProjectTabs()`**

Le JS doit parcourir les liens d’onglets, parser `href` avec `new URL()`, lire `forcetab`, puis :

- `Project$main` => onglet principal;
- préfixe `ProjectTask$` => onglet natif à masquer via le conteneur `<li>`;
- suffixe `DashboardTab$1` => onglet plugin à déplacer juste après le principal.

Le traitement doit être idempotent et s’exécuter :

```js
$(function () { normalizeProjectTabs(document); });
$(document).on('glpi.tab.loaded.projecttaskdashboard', function () {
  normalizeProjectTabs(document);
});
```

Ne jamais désactiver le lien `ProjectTask` côté serveur.

- [ ] **Step 4: Add SavedSearch handling for global page**

Ajouter un handler sur :

```text
.projecttaskdashboard-mytasks .savedsearches-item a
```

Extraire `savedsearches_id`, empêcher la navigation vers la recherche native générique, puis rediriger vers `data-mytasks-target?savedsearches_id=<id>`.

Ne pas utiliser `reloadTab()` sur cette page : elle est une page complète.

- [ ] **Step 5: Keep public/root JS identical**

Après édition :

```bash
cmp js/projecttaskdashboard.js public/js/projecttaskdashboard.js
```

Expected: code 0.

- [ ] **Step 6: Run JS regressions**

```bash
node tests/project_tabs_contract.js
node tests/search_transport_contract.js
php -d zend.assertions=1 -d assert.exception=1 tests/widget_reload.php
```

- [ ] **Step 7: Commit**

```bash
git add js/projecttaskdashboard.js public/js/projecttaskdashboard.js tests/project_tabs_contract.js tests/search_transport_contract.js
git commit -m "feat: streamline project tabs navigation"
```

---

### Task 10: Full regression, runtime acceptance, documentation

**Files:**
- Modify after runtime validation: `README.md`
- No production behavior should be added in this task.

**Interfaces:**
- Consumes all previous tasks.

- [ ] **Step 1: Run syntax verification on every changed PHP file**

```bash
find src front -name '*.php' -print0 | xargs -0 -n1 php -l
php -l setup.php
php -l hook.php
```

Expected: every file reports `No syntax errors detected`.

- [ ] **Step 2: Run V1 + V1.1 contract suite**

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
php -d zend.assertions=1 -d assert.exception=1 tests/mytasks_renderer_contract.php
php -d zend.assertions=1 -d assert.exception=1 tests/mytasks_search_adapter_contract.php
php -d zend.assertions=1 -d assert.exception=1 tests/mytasks_ajax_context.php
node tests/project_tabs_contract.js
node tests/search_transport_contract.js
```

Expected: all PASS.

- [ ] **Step 3: Deploy branch on the GLPI 11.0.8 test instance**

```bash
cd /var/www/html/glpi/plugins/projecttaskdashboard
git fetch origin
git checkout feature/project-task-dashboard-v1.1
git pull origin feature/project-task-dashboard-v1.1
su -s /bin/sh www-data -c 'php /var/www/html/glpi/bin/console cache:clear'
```

Puis navigateur `Ctrl+F5`.

- [ ] **Step 4: Runtime acceptance — menu principal**

Valider dans GLPI :

1. `Projet` apparaît au même niveau que `Parc`, `Assistance`, etc.;
2. `📋 Projets` ouvre la liste native;
3. seuls les projets visibles non terminés sont listés;
4. ordre alphabétique;
5. un projet terminé disparaît après modification + nouvelle navigation;
6. un projet inaccessible n’apparaît pas;
7. clic projet ouvre directement `📊 Pilotage des tâches`;
8. `👤 Mes tâches` est présent uniquement avec les droits nécessaires.

- [ ] **Step 5: Runtime acceptance — dashboard/onglets/création**

Valider :

1. ordre `Projet` → `📊 Pilotage des tâches` → autres onglets;
2. `Tâches de projet` absent de la barre;
3. URL native avec `forcetab=ProjectTask$...` fonctionne encore;
4. `+ Ajouter une tâche` apparaît avec CREATE;
5. clic ouvre `/front/projecttask.form.php?projects_id=<courant>`;
6. le formulaire affiche bien le projet prérempli;
7. création réelle produit une tâche liée au bon projet.

- [ ] **Step 6: Runtime acceptance — `Mes tâches`**

Créer/identifier quatre cas réels :

- tâche affectée directement à l’utilisateur dans projet actif visible → visible;
- tâche affectée uniquement à un groupe de l’utilisateur → visible;
- tâche non affectée à l’utilisateur/groupe → absente;
- tâche affectée mais projet terminé ou invisible → absente.

Puis tester : recherche texte, tri colonne Projet/État, tri inverse, pagination, changement de limite, SavedSearch. Dans Network, les refresh `/ajax/search.php` doivent transporter :

```text
ptd_scope: mytasks
usesession: 0
```

et les résultats doivent rester dans le périmètre autorisé.

- [ ] **Step 7: Runtime acceptance — Fields optional**

Tester une fois avec Fields actif et Module/Priorité résolus, puis vérifier au minimum par contract/fallback que l’absence de Fields ne déclenche aucune fatal et que les colonnes optionnelles sont simplement omises.

- [ ] **Step 8: Update README only after runtime success**

Mettre à jour `README.md` avec :

- secteur principal `Projet`;
- projets actifs visibles dynamiques;
- bouton `Ajouter une tâche`;
- onglet natif masqué visuellement;
- vue globale `Mes tâches` sécurisée;
- environnement runtime validé GLPI `11.0.8`, PHP `8.2.31`, Fields `1.24.4` si ces versions sont toujours celles réellement testées.

Ne pas déclarer export/actions de masse validés si ces scénarios n’ont pas été rejoués.

- [ ] **Step 9: Commit documentation**

```bash
git add README.md
git commit -m "docs: document project dashboard v1.1"
```

- [ ] **Step 10: Final verification before completion**

Rejouer les commandes de Step 1 et Step 2 après le dernier commit. Vérifier `git status --short` vide. Ne déclarer la V1.1 terminée que sur résultats frais et tests runtime confirmés.

---

## Implementation Order / Dependencies

```text
Task 1 ActiveProjectProvider
  ├─> Task 3 Project menu
  └─> Task 5 MyTasksScopeProvider

Task 2 Project navigation helpers
  ├─> Task 3 Project menu
  ├─> Task 4 Add task action
  └─> Task 9 tab UI

Task 5 secured task criteria
  └─> Task 7 MyTasks page
       └─> Task 8 MyTasks AJAX

Task 6 scoped search sessions
  ├─> Task 7 MyTasks page
  └─> Task 8 MyTasks AJAX

Tasks 1–9
  └─> Task 10 full runtime acceptance
```

## Security Review Checklist

Avant merge, confirmer explicitement :

- `ActiveProjectProvider` appelle bien `Project::canViewItem()` pour chaque projet exposé.
- `MyTasksScopeProvider` retourne une intersection, jamais l’union des tâches assignées et projets visibles.
- liste vide de projets ou tâches => critère impossible `id=-1`, jamais « pas de filtre ».
- les critères client sont groupés avant le scope obligatoire.
- `ptd_scope=mytasks` est seulement un marqueur de contexte; il ne contient aucune autorisation.
- les IDs autorisés sont recalculés côté serveur à chaque refresh AJAX.
- `ProjectSearchRightsScope` n’existe que pendant l’exécution de recherche et restaure les droits initiaux.
- `DashboardSearchSession` et `MyTasksSearchSession` ne partagent pas leurs critères persistés.
- aucune route native `ProjectTask` n’est supprimée ou remplacée.
- aucune modification dans `/var/www/html/glpi/src` ou autre core GLPI.

## Definition of Done

La V1.1 est terminée uniquement lorsque :

1. tous les tests contractuels/syntaxiques sont verts;
2. menu principal, onglets et création sont validés sur GLPI réel;
3. `Mes tâches` passe les cas utilisateur direct, groupe, projet terminé et projet invisible;
4. tri/pagination/limite AJAX restent sécurisés;
5. le test Fields optionnel ne casse pas le rendu;
6. le README reflète uniquement ce qui a réellement été validé;
7. la branche est propre (`git status --short` vide) avant PR/merge.
