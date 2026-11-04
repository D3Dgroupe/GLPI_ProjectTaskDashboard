# GLPI Project Task Dashboard

Plugin **GLPI 11** ajoutant un onglet **📊 Pilotage des tâches** à chaque projet afin de disposer d'une vue de pilotage proche de la vue native Tickets, sans modifier le cœur GLPI.

## Compatibilité

- GLPI : `>= 11.0.0` et `< 12.0.0`
- PHP : `>= 8.2`
- Plugin Fields : optionnel
- Schéma SQL propre au plugin : **une table**, `glpi_plugin_projecttaskdashboard_githublinks` (liens GitHub, depuis la 0.7.0) ; les réglages par projet sont stockés dans la configuration GLPI, contexte `plugin:projecttaskdashboard`

## Fonctionnalités V1

- Onglet `📊 Pilotage des tâches` dans les projets.
- Contexte projet forcé côté serveur et non supprimable.
- Moteur de recherche natif `ProjectTask` : filtres, tri, pagination, recherches sauvegardées, actions de masse et exports.
- Widgets contextuels :
  - 📌 À FAIRE : état ID `1`
  - ⏸️ EN VEILLE : état ID `5`
  - ❓ BLOQUÉ / ATTENTE : état ID `9`
  - 💡 IDÉE / BROUILLON : état ID `6`
  - 🔄 EN COURS : état ID `2`
  - 🔎 À CONTRÔLER : état ID `8`
  - 👤 MES TÂCHES : utilisateur courant **ou** groupes de l'utilisateur.
  - 📋 RESTE À FAIRE : états `1` + `2` + `8` + `9` (À faire, En cours, À contrôler, Bloqué / attente)
  - 🗂️ TOUTES : toutes les tâches (aucun filtre d'état)
- Couleurs d'état dans le tableau : la cellule `État` prend la couleur de son statut de projet (Configuration > Intitulés > Statuts de projet), et toute la ligne quand le statut est marqué `État terminé`.
- Édition directe dans le tableau : un clic sur une cellule `État`, `Type`, `Pourcentage effectué` ou `Date de fin planifiée` permet de la modifier sans ouvrir la tâche (enregistrement au changement, `Échap` pour annuler). Mêmes droits et mêmes règles que le formulaire de la tâche ; le pourcentage n'est pas modifiable s'il est calculé automatiquement.
- Ouverture des tâches en fenêtre modale depuis le tableau : on reste sur la liste (filtres, tri, page, défilement), qui est rafraîchie à la fermeture si la tâche a été enregistrée. Le bouton `Ouvrir la fiche complète` (ou Ctrl+clic) ouvre la fiche avec tous ses onglets dans un nouvel onglet.
- Menu `Projet` : emoji personnalisé par projet et choix des projets affichés dans le menu rapide, depuis le bas du formulaire du projet (`Emoji du projet`, `Afficher dans le menu rapide`). Par défaut : 📁 et affiché. Les projets terminés n'y apparaissent jamais ; masquer un projet du menu ne le retire pas de `Mes tâches`.
- Fields optionnel : détection dynamique de `Module` et `Priorité`.
- Colonnes équipe séparées en V1 : `Utilisateurs` et `Groupes`.
- L'onglet natif `Tâches de projet` reste inchangé.

## Lien GitHub

Depuis la version 0.7.0, le plugin conserve le lien entre une tâche de projet (ou un projet) et l'objet GitHub correspondant
(issue pour une tâche, dépôt pour un projet). Les liens sont **écrits uniquement par le connecteur GitHub** (via l'API ci-dessous) ;
l'interface GLPI ne les affiche qu'en **lecture seule** : aucun champ de saisie, aucune action de masse.

### Ce qui est affiché

- Colonne **GitHub** (`#numéro`, lien vers l'issue) **affichée par défaut** dans l'onglet Pilotage et dans Mes tâches pour les
  utilisateurs qui n'ont pas de colonnes personnelles (elle s'ajoute aux colonnes d'affichage globales) ; un utilisateur qui a choisi
  ses propres colonnes garde son choix (il peut l'ajouter ou la retirer). Elle est **optionnelle** (à ajouter via les colonnes
  d'affichage de la recherche) dans la liste native des tâches de projet. Elle est conservée au tri et au changement de page.
  Dans les exports (CSV, PDF, ODS, XLSX), la cellule contient le texte brut `#numéro` (vide sans lien), jamais de HTML ; le lien
  d'export de GLPI ne reprend que les critères, donc l'export contient la colonne si elle fait partie des colonnes d'affichage de
  l'utilisateur (personnelles, ou globales à défaut).
- Fiche d'une tâche : ligne **Issue GitHub** (« Aucune issue GitHub liée » sans lien).
- Fiche d'un projet : ligne **Dépôt GitHub** (« Aucun dépôt GitHub lié » sans lien).
- Seules les URL `https://github.com/...` deviennent des liens ; tout est échappé.

### API (High-Level API v2, `/api.php/v2.3`)

L'API v2 doit être activée (Configuration > Générale > API, ou `bin/console config:set enable_hlapi 1`) et un client OAuth créé.
Les routes sont enregistrées par le plugin sous `/Plugin/Projecttaskdashboard/` :

| Route | Rôle |
|-------|------|
| `GET /Plugin/Projecttaskdashboard/Status` | Détection du plugin : `{plugin, version, github_link}` (authentification requise). |
| `GET /Plugin/Projecttaskdashboard/GithubLink` | Liste des liens, filtrable (`itemtype` + `items_id`/`items_ids`, `kind` + `github_id`, `project_id`). Ne renvoie que les objets visibles par l'utilisateur de l'API. |
| `PUT /Plugin/Projecttaskdashboard/GithubLink` | Crée ou met à jour (idempotent) le lien d'un objet ; la réponse est plate : champs du lien + `created` (`true` à la création, `false` sinon). |

Il n'existe **pas** de route de suppression : un lien disparaît avec la purge de l'objet.

Droits : lecture = droit de voir l'objet ; écriture = droit de modifier l'objet (`ProjectTask` / `Project`) dans son entité. Aucun droit propre au plugin.

Codes d'erreur (champ `code` de la réponse) :

| HTTP | Code | Cas |
|------|------|-----|
| 400 | `invalid_itemtype`, `invalid_items_id`, `invalid_kind`, `kind_mismatch` (le type d'objet ne correspond pas au genre : `ProjectTask` = `issue`, `Project` = `repository`), `invalid_github_id`, `invalid_project_id`, `invalid_repo`, `invalid_number`, `invalid_url` | corps invalide (`PUT`) ou filtre invalide (`GET`) |
| 400 | `missing_filter` | `GET` sans aucun filtre (la table n'est jamais listée en entier) |
| 400 | `conflicting_filters` | `GET` avec plusieurs filtres incompatibles (un seul est accepté) |
| 400 | `too_many_ids` | `items_ids` dépasse 1000 identifiants |
| 403 | `forbidden` | objet visible mais non modifiable par l'utilisateur |
| 404 | `item_not_found` | objet inexistant **ou non visible** (pas d'oracle d'existence) |
| 409 | `github_id_already_linked` | cet identifiant GitHub est déjà lié à un autre objet |
| 409 | `item_linked_to_other_github_id` | l'objet est déjà lié à un autre identifiant GitHub (pas de re-pointage) |
| 409 | `item_in_trash` | l'objet est dans la corbeille |

Le plugin vide lui-même le cache des routes de l'API v2 (clé `hlapi_routes`) à l'installation, l'activation, la désactivation et la
désinstallation : les routes répondent immédiatement après activation.

### Prérequis du compte technique (production)

Le connecteur utilise un compte GLPI dédié, avec les droits que l'écran des profils permet réellement de configurer :

- Profil sur l'**entité racine, récursif** : **Projets** = *Voir tous* + *Mettre à jour* ; **Tâches de projet** = *Voir (acteur)* +
  *Mettre à jour (acteur)*. Ce sont les droits nécessaires **aux routes de lien GitHub** ; ils s'ajoutent à ceux que le connecteur
  GithubSync utilise déjà : il crée les sous-projets et les tâches, donc son compte garde la **création** sur Projets et Tâches de
  projet (ne pas la retirer). Aucun droit de suppression n'est nécessaire.
- La session API démarre sur le profil et l'entité par défaut du compte : le compte ne doit pas avoir d'entité par défaut, ou avoir
  l'entité racine comme entité par défaut. Sinon, le client envoie `GLPI-Entity: 0` et `GLPI-Entity-Recursive: true`.
- Client OAuth avec le scope `api` et l'adresse IP de l'hôte du connecteur dans les IP autorisées.

### Cycle de vie

- Mise à la corbeille d'une tâche/d'un projet : le lien est conservé ; **purge** : le lien est supprimé.
- Désinstallation du plugin : la table des liens et les réglages par projet sont supprimés (voir « Désinstallation »). Les liens sont
  reconstructibles par le connecteur.

## Champs Fields attendus

Les IDs sont des **indices de secours**, pas des dépendances rigides :

- `Module` : field hint ID `3`
- `Priorité` : field hint ID `4`

Priorités actuellement utilisées :

- 🟢 BASSE
- 🟡 NORMALE
- 🔴 HAUTE
- 🚨 CRITIQUE

## Installation

Le nom du répertoire doit impérativement être :

```text
projecttaskdashboard
```

Depuis la racine GLPI :

```bash
cd /var/www/glpi/plugins
git clone https://github.com/D3Dgroupe/GLPI_ProjectTaskDashboard.git projecttaskdashboard
cd /var/www/glpi
php bin/console glpi:plugin:install projecttaskdashboard
php bin/console glpi:plugin:activate projecttaskdashboard
```

L'installation peut aussi être effectuée depuis **Configuration > Plugins** après copie du dossier dans `plugins/projecttaskdashboard`.

## Mise à jour

```bash
cd /var/www/glpi/plugins/projecttaskdashboard
git pull
```

Puis **Configuration > Plugins** > **Mettre à jour** (ou `php bin/console glpi:plugin:install --force projecttaskdashboard` puis `php bin/console glpi:plugin:activate projecttaskdashboard`).

GLPI ne propose la mise à jour que si `PLUGIN_PROJECTTASKDASHBOARD_VERSION` (dans `setup.php`) diffère de la version enregistrée : toute évolution livrée doit donc incrémenter cette version.

### Mise à jour 0.6.3 → 0.7.0

La 0.7.0 ajoute la table des liens GitHub (créée par `plugin_projecttaskdashboard_install()`, sans toucher aux réglages existants)
et les routes d'API. **Après la mise à jour du code, le plugin est toujours inactif : l'activation est obligatoire.** L'onglet
Pilotage est indisponible entre la mise à jour du code et l'activation : le faire à un moment calme. Procédure :

1. Sauvegarder la base (la mise à jour crée une table ; rien n'est modifié ni supprimé).
2. `git pull` (ou déployer la 0.7.0) dans `plugins/projecttaskdashboard`.
3. Lancer les commandes console **avec l'utilisateur du serveur web** (pour que les fichiers de cache n'appartiennent pas à root) :
   ```bash
   cd /var/www/glpi
   sudo -u www-data php bin/console glpi:plugin:install projecttaskdashboard
   sudo -u www-data php bin/console glpi:plugin:activate projecttaskdashboard
   ```
4. Vérifier : `GET /api.php/v2.3/Plugin/Projecttaskdashboard/Status` (authentifié) renvoie `"version": "0.7.0"` ; un `PUT` puis un
   `GET` de test sur un lien ; dans Pilotage, un tri et un changement de page conservent la colonne GitHub ; la ligne « Issue GitHub »
   de la fiche d'une tâche ; le réglage d'emoji du projet ; la création d'une tâche depuis un ticket.

Retour arrière : désactiver le plugin et remettre le code 0.6.3 ; la table `glpi_plugin_projecttaskdashboard_githublinks` peut rester (ignorée
par la 0.6.3).

## Désinstallation

Le plugin crée une seule table, `glpi_plugin_projecttaskdashboard_githublinks`, qui ne contient que des liens entre une tâche/un projet et son objet GitHub (le connecteur sait les réécrire). Il peut être désactivé puis supprimé sans supprimer les tâches ou projets GLPI. La désinstallation supprime cette table et les réglages par projet (emoji, menu rapide) ; les tâches et projets ne sont pas touchés. La mise à la corbeille d'une tâche ou d'un projet conserve son lien ; sa purge le supprime.

```bash
cd /var/www/glpi
php bin/console glpi:plugin:deactivate projecttaskdashboard
php bin/console glpi:plugin:uninstall projecttaskdashboard
rm -rf plugins/projecttaskdashboard
```

## Recherches sauvegardées

Les recherches sauvegardées restent des `SavedSearch` natives de type `ProjectTask`. Le projet n'est jamais enregistré dans la recherche : il est réinjecté automatiquement à partir du projet actuellement ouvert. Une même recherche peut donc être réutilisée dans plusieurs projets sans fuite inter-projets.

## Environnement de développement jetable

`dev/` contient un GLPI 11.0.12 + MariaDB en Docker (`127.0.0.1:8088`), le plugin monté, des données et comptes de test, et des tests de
bout en bout de l'API (`php tests/e2e/github_link_api.php`). Voir [dev/README.md](dev/README.md). Ne jamais y mettre de donnée de production.

## Sécurité

Le plugin ne crée aucun ACL. Il réutilise les droits `Project` / `ProjectTask` et le moteur de recherche natif GLPI, y compris pour les compteurs. Les critères utilisateur sont groupés puis combinés par `AND` avec le projet courant afin d'empêcher un critère `OR` client de sortir du périmètre projet.

## Diagnostic

Les avertissements non bloquants sont écrits dans le canal de log `projecttaskdashboard`. Aucune description de tâche, aucun token de session et aucune requête complète n'est journalisé.

## État de validation

Les fichiers PHP sont vérifiés par `php -l` et le transformateur de critères dispose d'un smoke test autonome. La validation fonctionnelle complète doit être réalisée sur une instance GLPI 11 de test avant mise en production.
