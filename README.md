# GLPI Project Task Dashboard

Plugin **GLPI 11** ajoutant un onglet **📊 Pilotage des tâches** à chaque projet afin de disposer d'une vue de pilotage proche de la vue native Tickets, sans modifier le cœur GLPI.

## Compatibilité

- GLPI : `>= 11.0.0` et `< 12.0.0`
- PHP : `>= 8.2`
- Plugin Fields : optionnel
- Schéma SQL propre au plugin : **aucun**

## Fonctionnalités V1

- Onglet `📊 Pilotage des tâches` dans les projets.
- Contexte projet forcé côté serveur et non supprimable.
- Moteur de recherche natif `ProjectTask` : filtres, tri, pagination, recherches sauvegardées, actions de masse et exports.
- Widgets contextuels :
  - 📌 À FAIRE : état ID `1`
  - 🔄 EN COURS : état ID `2`
  - 🔎 À CONTRÔLER : état ID `8`
  - 👤 MES TÂCHES : utilisateur courant **ou** groupes de l'utilisateur.
- Fields optionnel : détection dynamique de `Module` et `Priorité`.
- Colonnes équipe séparées en V1 : `Utilisateurs` et `Groupes`.
- L'onglet natif `Tâches de projet` reste inchangé.

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

## Désinstallation

Le plugin ne crée aucune table métier. Il peut être désactivé puis supprimé sans supprimer les tâches ou projets GLPI.

```bash
cd /var/www/glpi
php bin/console glpi:plugin:deactivate projecttaskdashboard
php bin/console glpi:plugin:uninstall projecttaskdashboard
rm -rf plugins/projecttaskdashboard
```

## Recherches sauvegardées

Les recherches sauvegardées restent des `SavedSearch` natives de type `ProjectTask`. Le projet n'est jamais enregistré dans la recherche : il est réinjecté automatiquement à partir du projet actuellement ouvert. Une même recherche peut donc être réutilisée dans plusieurs projets sans fuite inter-projets.

## Sécurité

Le plugin ne crée aucun ACL. Il réutilise les droits `Project` / `ProjectTask` et le moteur de recherche natif GLPI, y compris pour les compteurs. Les critères utilisateur sont groupés puis combinés par `AND` avec le projet courant afin d'empêcher un critère `OR` client de sortir du périmètre projet.

## Diagnostic

Les avertissements non bloquants sont écrits dans le canal de log `projecttaskdashboard`. Aucune description de tâche, aucun token de session et aucune requête complète n'est journalisé.

## État de validation

Les fichiers PHP sont vérifiés par `php -l` et le transformateur de critères dispose d'un smoke test autonome. La validation fonctionnelle complète doit être réalisée sur une instance GLPI 11 de test avant mise en production.
