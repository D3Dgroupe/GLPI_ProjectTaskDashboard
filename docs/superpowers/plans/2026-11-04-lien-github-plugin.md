# Lien GitHub dédié — Plan 1 : plugin Project Task Dashboard

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Le plugin stocke, expose par l'API v2 et affiche un lien GitHub dédié pour les tâches et sous-projets synchronisés (version 0.7.0).

**Architecture:** Une table propre au plugin (liens objet GLPI ↔ objet GitHub) gérée par une classe modèle, une logique de validation en PHP pur testable seule, un contrôleur d'API v2 enregistré par le hook `api_controllers` (GLPI ≥ 11.0.0), et un affichage (option de recherche + colonne, lignes de fiche). Développement et vérification sur un GLPI 11 jetable en Docker.

**Tech Stack:** PHP ≥ 8.2, GLPI 11.0.x (API v2 « High-Level », attributs `Glpi\Api\HL\Route`, `RouteVersion`, `Doc\Route` ; référence : `src/Glpi/Api/HL/Controller/ProjectController.php` du dépôt glpi-project/glpi, branche 11.0/bugfixes), MariaDB, Docker 26.

**Spec:** `docs/superpowers/specs/2026-11-04-lien-github-design.md` (§2, §4–§6, §7 bis, §8). Le plan 2 (adaptation du connecteur GithubSync) sera écrit après ce plan, à partir de la route réellement livrée.

## Global Constraints

- Types liés : `ProjectTask` (genre `issue`) et `Project` (genre `repository`) uniquement ; unicité (type, ID GLPI) et (genre, ID GitHub).
- ID GitHub sur 64 bits ; URL acceptée seulement si elle commence par `https://github.com/`.
- API : lecture si droit de **voir** l'objet, écriture si droit de **modifier** l'objet (droits et entités natifs) ; objet existant et pas à la corbeille pour écrire ; **aucune suppression par l'API** ; ID GitHub déjà lié à un autre objet → refus avec code d'erreur distinct, jamais d'écrasement.
- Route de statut légère (version du plugin, aucune donnée) pour la détection par le connecteur.
- Lien en lecture seule dans l'interface ; liens ouverts dans un nouvel onglet avec `rel="noopener noreferrer"` ; tout texte affiché échappé.
- Corbeille : le lien est conservé ; **purge** de la tâche ou du projet : le lien est supprimé.
- Aucune régression : onglet *Pilotage*, *Mes tâches*, création de tâche depuis ticket, réglages emoji / menu rapide de la fiche projet.
- Version `0.7.0` dans `setup.php` (GLPI ne propose la mise à jour que si la version change) ; README corrigé (le plugin crée désormais une table de liens, sans effet sur tâches/projets).
- Aucune donnée de production touchée pendant ce plan : tout se vérifie sur le GLPI jetable.
- Git : branches `feature/*` depuis `develop`, PR vers `develop` ; commits terminés par `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

- Un utilisateur sans droit de modification sur la tâche qui tente d'écrire un lien → refus (403), aucune ligne créée → Task 3.
- Écriture d'un lien sur une tâche d'une autre entité que celle de l'appelant → refus → Task 3.
- Deux écritures concurrentes du même lien (idempotence, contrainte d'unicité en base, pas d'erreur 500) → Task 2 et Task 3.
- Un nom de dépôt ou un titre contenant du HTML / des guillemets dans les données du lien → affiché échappé → Task 4.
- Mise à jour du plugin depuis 0.6.3 sur une base existante (la table est créée, les réglages existants survivent) puis désinstallation (seule la table des liens est supprimée) → Task 2.

---

## File Structure

```
dev/docker-compose.yml  dev/up.sh  dev/down.sh  dev/README.md     (GLPI jetable)
setup.php  hook.php                                                 (version, hooks, install/uninstall)
src/Github/GithubLink.php            (modèle, accès table)
src/Github/GithubLinkValidator.php   (validation pure, sans GLPI)
src/Github/GithubLinkRenderer.php    (rendu HTML pur, sans GLPI)
src/Api/GithubLinkController.php     (contrôleur API v2)
src/Github/GithubLinkSearchOption.php (option de recherche / colonne)
tests/github_link_validator.php  tests/github_link_renderer.php    (tests PHP purs, lancés avec `php`)
tests/e2e/github_link_api.php                                      (tests HTTP de bout en bout contre le GLPI jetable)
README.md  CHANGELOG.md (créé s'il n'existe pas)
```

---

### Task 1: GLPI 11 jetable en Docker

**Files:** Create `dev/docker-compose.yml`, `dev/up.sh`, `dev/down.sh`, `dev/README.md`.

**Interfaces:**
- Produces: `dev/up.sh` démarre MariaDB + GLPI 11.0.x (même version mineure que la production, transmise par le contrôleur ; à défaut la dernière 11.0.x), installe GLPI en ligne de commande, monte le dépôt dans `plugins/projecttaskdashboard`, installe et active le plugin, crée : un client OAuth de test (grant `password`, scope `api`), un utilisateur technique avec droit de modification sur les projets/tâches de l'entité racine, un utilisateur **sans** droit de modification, deux entités, un projet parent, deux sous-projets et quelques tâches ; écrit dans `dev/.env.test` (ignoré par Git) l'URL de base de l'API v2, les identifiants de test et les IDs créés. `dev/down.sh` détruit tout (volumes compris). Port local seulement (127.0.0.1).
- `dev/README.md` : démarrage, arrêt, réinstallation du plugin après modification, récupération des journaux PHP.

- [ ] **Step 1:** écrire les fichiers ; ajouter `dev/.env.test` et les volumes au `.gitignore`.
- [ ] **Step 2:** `bash dev/up.sh` puis vérifier : `curl -s <base>/doc.json` répond 200 ; obtention d'un jeton OAuth avec l'utilisateur technique ; `GET /Project/{id}` renvoie le projet parent ; le plugin est actif (`GET /Setup/Plugin`).
- [ ] **Step 3:** `bash dev/down.sh` puis `bash dev/up.sh` : l'environnement se recrée à l'identique (idempotence du script).
- [ ] **Step 4:** Commit `chore: GLPI 11 jetable pour le développement du plugin`.

### Task 2: Table des liens, modèle, validation, cycle de vie

**Files:** Create `src/Github/GithubLink.php`, `src/Github/GithubLinkValidator.php`, `tests/github_link_validator.php` ; Modify `hook.php` (install/uninstall), `setup.php` (version `0.7.0`, hooks de purge), `README.md`.

**Interfaces:**
- Produces (validator, PHP pur) : `GithubLinkValidator::validate(array $input): array` qui renvoie l'entrée normalisée ou lève `\InvalidArgumentException` avec un code parmi `invalid_itemtype`, `kind_mismatch`, `invalid_github_id`, `invalid_url`, `invalid_number`, `invalid_repo` ; champs d'entrée : `itemtype`, `items_id`, `kind`, `github_id`, `repository` (`owner/name`), `number` (obligatoire et > 0 pour `issue`, absent pour `repository`), `url`.
- Produces (modèle) : `GithubLink::getTable(): string` ; `GithubLink::findForItem(string $itemtype, int $itemsId): ?array` ; `GithubLink::findForItems(string $itemtype, array $ids): array` ; `GithubLink::findByGithub(string $kind, int $githubId): ?array` ; `GithubLink::upsertForItem(array $validated): array{row: array, created: bool}` (idempotent ; si l'ID GitHub est déjà lié à un AUTRE objet → `\DomainException` code `github_id_already_linked`) ; `GithubLink::deleteForItem(string $itemtype, int $itemsId): void` (utilisé uniquement par la purge).
- Install / mise à jour : création de la table si absente (index uniques sur (itemtype, items_id) et (kind, github_id)), sans toucher aux réglages existants ; désinstallation : suppression de la table en plus des réglages actuels.
- Hooks `ITEM_PURGE` pour `ProjectTask` et `Project` → `deleteForItem` (le hook de purge existant sur `Project` est conservé et appelé aussi).

- [ ] **Step 1: Tests (PHP pur)** `tests/github_link_validator.php` : URL non GitHub refusée (`http://github.com`, `https://github.com.evil.tld/x`, `javascript:`) ; `ProjectTask` + `repository` → `kind_mismatch` ; issue sans numéro → `invalid_number` ; `github_id` 0, négatif, non numérique, > 2^63−1 → refus ; dépôt sans `/` → `invalid_repo` ; entrée valide normalisée (types entiers, espaces retirés). Lancement : `php tests/github_link_validator.php` → code de sortie 0 si tout passe.
- [ ] **Step 2:** lancer → FAIL. **Step 3:** implémenter validator, modèle, install/uninstall, hooks de purge, version 0.7.0, README. **Step 4:** `php tests/github_link_validator.php` → PASS ; `php -l` sur tous les fichiers modifiés.
- [ ] **Step 5 (GLPI jetable) :** installer d'abord la version 0.6.3 (checkout de `main` dans le conteneur), créer un réglage emoji sur un projet, puis passer à la branche et lancer la mise à jour du plugin : la table existe, le réglage emoji est intact ; purger une tâche liée (lien inséré en base par une requête de test) : le lien disparaît ; mettre une tâche liée à la corbeille : le lien reste ; désinstaller : seule la table des liens et les réglages du plugin disparaissent, tâches et projets intacts. Consigner les commandes et sorties dans le rapport.
- [ ] **Step 6:** Commit `feat: table des liens GitHub et cycle de vie`.

### Task 3: Contrôleur d'API v2

**Files:** Create `src/Api/GithubLinkController.php`, `tests/e2e/github_link_api.php` ; Modify `setup.php` (hook `$PLUGIN_HOOKS[Hooks::API_CONTROLLERS]['projecttaskdashboard'] = [GithubLinkController::class]`).

**Interfaces (chemins exacts ; JSON) :**
- `GET /Plugin/Projecttaskdashboard/Status` → `200 {"plugin":"projecttaskdashboard","version":"0.7.0","github_link":true}` (authentifié, aucune donnée métier).
- `GET /Plugin/Projecttaskdashboard/GithubLink?itemtype=ProjectTask&items_id=12` → `200 [lien]` ou `[]` ; variantes de filtre : `items_ids=12,13,14` (avec `itemtype`), `kind=issue&github_id=123`, `project_id=29` (liens des tâches de ce projet, usage principal du connecteur). Les liens d'objets que l'appelant ne peut pas voir sont omis.
- `PUT /Plugin/Projecttaskdashboard/GithubLink` corps `{itemtype, items_id, kind, github_id, repository, number?, url}` → `200 {lien, "created": bool}` (idempotent).
- Lien (forme JSON) : `{"id","itemtype","items_id","kind","github_id","repository","number","url","date_creation","date_mod"}`.
- Erreurs : `400 {"status":"ERROR_BAD_REQUEST","code":"<code du validateur>"}` ; `403` si droit de voir/modifier manquant ; `404` si l'objet GLPI n'existe pas ; `409 {"code":"github_id_already_linked"}` ; `409 {"code":"item_in_trash"}` pour une écriture sur un objet à la corbeille. Aucune route `DELETE`.
- Documentation : les routes et le schéma `GithubLink` apparaissent dans `doc.json` (attributs `Doc\Route` / schéma connu du contrôleur), avec `RouteVersion` introduite à la version de l'API de l'instance (2.3) ou celle requise par GLPI pour un plugin (au choix du constructeur selon le code de GLPI).

- [ ] **Step 1: Tests de bout en bout** `tests/e2e/github_link_api.php` (PHP CLI + curl, lit `dev/.env.test`, sortie ≠ 0 en cas d'échec) : `status_requires_auth_and_returns_version` ; `put_creates_then_is_idempotent` (2ᵉ PUT identique → `created:false`, une seule ligne) ; `put_updates_url_and_repository_on_rename` ; `get_by_item_and_by_project_id_and_by_github_id` ; `put_conflict_when_github_id_linked_to_other_item` (409, première ligne inchangée) ; `put_forbidden_for_user_without_update_right` (403, aucune ligne) ; `put_forbidden_on_other_entity` ; `put_rejected_on_trashed_item` (409) ; `put_404_on_missing_item` ; `bad_requests_return_validator_codes` (un cas par code) ; `get_hides_links_of_items_not_visible` ; `no_delete_route` (`DELETE` → 405 ou 404) ; `routes_listed_in_doc_json`.
- [ ] **Step 2:** lancer contre le GLPI jetable → FAIL. **Step 3:** implémenter le contrôleur (s'inspirer des contrôleurs de GLPI pour attributs, réponses et contrôle des droits ; utiliser `GithubLinkValidator` et `GithubLink`). **Step 4:** `php tests/e2e/github_link_api.php` → PASS ; `php -l` ; réinstaller le plugin et relancer (pas d'état parasite).
- [ ] **Step 5:** Commit `feat: route API v2 des liens GitHub`.

### Task 4: Affichage (colonne, fiches)

**Files:** Create `src/Github/GithubLinkRenderer.php`, `src/Github/GithubLinkSearchOption.php`, `tests/github_link_renderer.php` ; Modify `hook.php` (`plugin_projecttaskdashboard_getAddSearchOptions`, `plugin_projecttaskdashboard_post_item_form`), et si nécessaire les vues *Pilotage* / *Mes tâches* pour afficher la colonne par défaut (`src/Search/NativeSearchAdapter.php`, `src/Search/MyTasksSearchAdapter.php`, `src/Config.php`).

**Interfaces:**
- `GithubLinkRenderer::issueCell(?array $link): string` → `''` si pas de lien, sinon `<a href="<url échappée>" target="_blank" rel="noopener noreferrer">#<numéro></a>` ; `GithubLinkRenderer::taskFormLine(?array $link): string` (« Issue GitHub ») ; `GithubLinkRenderer::projectFormLine(?array $link): string` (« Dépôt GitHub », texte = `owner/name`, lien vers la page du dépôt) ; tout contenu échappé.
- Option de recherche du plugin sur `ProjectTask` (ID réservé dans `Config`, nom « GitHub »), jointure sur la table des liens, affichage via le renderer, triable par numéro, filtrable « contient / est vide ». Colonne présente par défaut dans *Pilotage des tâches* et *Mes tâches*, ajoutable dans les listes natives.
- Fiches : ligne en lecture seule ajoutée par `post_item_form` pour `ProjectTask` et `Project` **sans** modifier le rendu actuel des réglages emoji / menu rapide de la fiche projet.

- [ ] **Step 1: Tests (PHP pur)** `tests/github_link_renderer.php` : `issue_cell_empty_without_link` ; `issue_cell_escapes_url_and_number` (URL contenant `"` et `<script>` → échappée) ; `project_line_shows_repo_and_links_home_page` ; `lines_use_noopener` ; `no_html_injection_from_repository_name`.
- [ ] **Step 2–4:** FAIL → implémenter → PASS ; `php -l`.
- [ ] **Step 5 (GLPI jetable) :** avec des liens créés par l'API (Task 3), vérifier dans le navigateur (ou par récupération HTML authentifiée) : colonne « GitHub » présente dans *Pilotage* et *Mes tâches* avec `#numéro` cliquable et cellule vide pour une tâche manuelle ; ligne « Issue GitHub » sur la fiche tâche ; ligne « Dépôt GitHub » sur la fiche projet, réglages emoji/menu toujours fonctionnels ; option « GitHub » ajoutable dans Projets → Tâches, tri et filtre « est vide » fonctionnels ; création de tâche depuis un ticket toujours fonctionnelle. Captures d'écran ou extraits HTML joints au rapport.
- [ ] **Step 6:** Commit `feat: affichage des liens GitHub`.

### Task 5: Documentation et livraison

**Files:** Modify `README.md`, Create/Modify `CHANGELOG.md`.

- [ ] **Step 1:** README : section « Lien GitHub » (ce qui est affiché, lecture seule, routes d'API et droits, effet de la désinstallation) ; procédure de mise à jour 0.6.3 → 0.7.0 ; mention du GLPI jetable `dev/`.
- [ ] **Step 2:** CHANGELOG 0.7.0.
- [ ] **Step 3:** suite complète : `php tests/github_link_validator.php && php tests/github_link_renderer.php && php tests/e2e/github_link_api.php` ; `php -l` sur `src/`, `front/`, `hook.php`, `setup.php` ; contrôle de non-régression manuel des vues existantes sur le GLPI jetable.
- [ ] **Step 4:** Commit `docs: lien GitHub, version 0.7.0` ; PR vers `develop`.

---

## Self-review (spec → tâches)

- §2 affichage → Task 4 ; §4 modèle et cycle de vie → Task 2 ; §5 API (statut, lecture, écriture, droits, unicité, pas de suppression, doc) → Task 3 ; §7 bis (corbeille/purge, route de statut, unicité = dérive côté connecteur) → Tasks 2–3 ; §8 GLPI jetable → Task 1 ; version 0.7.0 et README → Tasks 2 et 5.
- Hors de ce plan : adaptation du connecteur (plan 2), installation sur la production et recette réelle (après le plan 2), issue #39.
- Noms cohérents : `GithubLink`, `GithubLinkValidator`, `GithubLinkRenderer`, `GithubLinkController`, `GithubLinkSearchOption`, route `/Plugin/Projecttaskdashboard/...`.
