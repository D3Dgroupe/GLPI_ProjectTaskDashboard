# GLPI Project Task Dashboard — Design V1.1

Date: 2026-09-09

## Objectif

Étendre le plugin `projecttaskdashboard` pour qu'il devienne le point d'entrée principal de la gestion des projets et des tâches dans GLPI, sans modifier le core GLPI.

La V1.1 ajoute quatre évolutions cohérentes :

1. créer une tâche directement depuis `📊 Pilotage des tâches` ;
2. placer `📊 Pilotage des tâches` juste après l'onglet `Projet` ;
3. masquer visuellement l'onglet natif `Tâches de projet` sans bloquer son URL ;
4. ajouter un menu principal `Projet` au même niveau que `Parc`, `Assistance`, etc., avec une vue globale `👤 Mes tâches` propre au plugin.

## Contraintes

- GLPI cible : 11.0.x.
- Aucun patch du core GLPI.
- Les ACL natives GLPI restent l'autorité finale.
- Les projets et tâches invisibles pour l'utilisateur ne doivent jamais apparaître dans le menu ou dans les résultats.
- Le plugin doit continuer à fonctionner si le plugin Fields est absent.
- Le mécanisme existant de recherche `ProjectTask`, de widgets et d'isolation du contexte projet doit être réutilisé autant que possible.

## Définition d'un projet en cours

Un projet est considéré comme `en cours` lorsque son `ProjectState` n'est pas marqué terminé.

Règle fonctionnelle :

```text
ProjectState.is_finished = 0
OU ProjectState.is_finished IS NULL
```

Le plugin ne doit pas dépendre du libellé du statut (`En cours`, `Nouveau`, etc.).

Cette même définition est utilisée :

- dans le menu principal dynamique ;
- dans la vue globale `👤 Mes tâches`.

## 1. Création de tâche depuis le dashboard

### Interface

Le dashboard d'un projet affiche en haut un bouton :

```text
+ Ajouter une tâche
```

Le bouton n'est visible que si l'utilisateur possède le droit natif de création d'une `ProjectTask` dans le contexte concerné.

### Comportement

Le bouton ouvre le formulaire natif GLPI en page complète :

```text
/front/projecttask.form.php?projects_id=<ID_PROJET>
```

Le projet courant est donc prérempli par le mécanisme natif GLPI.

Le plugin ne recrée aucun formulaire de tâche. Les champs natifs, les champs Fields, les équipes, les dates, les droits et les validations continuent d'être gérés par GLPI.

### Retour après création

Le plugin doit préparer un retour vers `📊 Pilotage des tâches` du projet concerné en utilisant en priorité le mécanisme de retour natif GLPI. Il ne modifie pas le traitement POST natif de `projecttask.form.php`.

Si le mécanisme natif ne permet pas un retour fiable sans modifier le core, le comportement GLPI standard après création est conservé : ce point ne doit pas justifier un fork de `projecttask.form.php`.

## 2. Réorganisation des onglets Projet

### Cible visuelle

Avant :

```text
Projet
Tâches de projet
📊 Pilotage des tâches
Équipe projet
Projets
Kanban
Coûts
...
```

Après :

```text
Projet
📊 Pilotage des tâches
Équipe projet
Projets
Kanban
Coûts
...
```

### Principe

GLPI crée ses onglets natifs dans `Project::defineTabs()`, puis ajoute les onglets de plugins enregistrés avec `addtabon`.

La V1.1 ne modifie pas `Project.php`.

Le plugin applique une adaptation d'interface côté navigateur, limitée aux fiches `Project` :

- repérer les onglets via leur identifiant technique `forcetab` et non leur libellé traduit ;
- identifier l'onglet principal par `Project$main` ;
- identifier l'onglet natif des tâches par le préfixe `ProjectTask$` ;
- identifier le dashboard par la classe `DashboardTab` et son `forcetab` ;
- déplacer `DashboardTab` juste après `Project$main` ;
- masquer uniquement le lien vers l'onglet natif `ProjectTask`.

### Garantie de repli

L'onglet natif `Tâches de projet` reste accessible par son URL directe et par son `forcetab` technique.

Si le plugin est désactivé, aucune modification du core ou de la base n'ayant été faite, l'interface native GLPI réapparaît normalement.

## 3. Menu principal `Projet`

### Position

Le plugin ajoute un nouveau secteur principal `Projet` via le hook officiel `REDEFINE_MENUS`.

Il doit apparaître au même niveau que les secteurs principaux GLPI tels que `Parc`, `Assistance`, `Gestion`, etc.

Le secteur n'est affiché que pour un utilisateur pouvant consulter les projets. L'entrée `👤 Mes tâches` est en plus conditionnée à un droit de lecture des tâches de projet suffisant pour exécuter la vue globale.

### Structure

Le menu est volontairement limité à deux niveaux, conformément au rendu natif GLPI 11 :

```text
Projet
├── 📋 Projets
├── 📁 <Projet en cours A>
├── 📁 <Projet en cours B>
├── 📁 <Projet en cours C>
└── 👤 Mes tâches
```

### Entrée `📋 Projets`

Ouvre la liste native GLPI des projets.

### Entrées dynamiques de projets

Chaque projet en cours visible par l'utilisateur apparaît sur une ligne.

Règles :

- uniquement les projets accessibles à l'utilisateur courant ;
- uniquement les projets non terminés ;
- tri alphabétique par nom ;
- un clic ouvre directement la fiche du projet avec `forcetab=<DashboardTab>$1` afin d'afficher `📊 Pilotage des tâches`.

Le menu ne propose pas un sous-niveau `Ajouter une tâche`. La création se fait dans le dashboard du projet afin que le rattachement soit sans ambiguïté.

### Cache du menu

Le menu GLPI étant conservé en session, le plugin doit éviter un menu durablement obsolète après modification d'un projet.

La stratégie d'implémentation doit invalider le cache de menu de la session courante lorsqu'un `Project` est créé, modifié, supprimé ou purgé, sans forcer une régénération récursive depuis le hook `REDEFINE_MENUS`.

En cas d'échec de construction des entrées dynamiques, le menu reste utilisable avec les entrées statiques autorisées (`📋 Projets` et, si les droits le permettent, `👤 Mes tâches`).

## 4. Vue globale `👤 Mes tâches`

### Route

La route V1.1 est :

```text
/plugins/projecttaskdashboard/front/mytasks.php
```

Cette page est autonome par rapport au dashboard d'un projet.

### Sémantique

La vue affiche :

```text
(tâche affectée directement à l'utilisateur courant
 OU tâche affectée à un groupe de l'utilisateur courant)
AND projet visible par l'utilisateur
AND projet non terminé
```

Elle ne doit pas s'appuyer sur la page native GLPI `Mes tâches`, car le comportement observé sur GLPI 11 dépend de l'appartenance à l'équipe du projet et ne correspond pas au besoin métier validé.

### Recherche

La vue réutilise le moteur natif `ProjectTask` et les composants déjà construits par le plugin :

- recherche GLPI ;
- tri ;
- pagination ;
- limite de lignes ;
- recherches sauvegardées lorsque compatibles ;
- colonnes Fields si le plugin Fields est présent ;
- logique utilisateur/groupe déjà implémentée par `MineTaskProvider` / `MineCriteriaExpander` ou leur abstraction commune.

### Contexte AJAX

La vue globale doit utiliser un marqueur de transport propre, distinct du dashboard par projet, par exemple :

```text
ptd_scope=mytasks
```

Lors des appels natifs `/ajax/search.php` déclenchés par tri, pagination, limite de lignes ou rafraîchissement, le plugin reconstruit côté serveur les critères obligatoires `mes tâches + projets visibles + projets non terminés` avant l'exécution de la recherche.

Le navigateur ne fournit donc jamais lui-même la liste des projets autorisés comme source d'autorité.

### Contexte de sécurité

Contrairement au dashboard d'un projet, la vue globale ne force pas un unique `projects_id`.

Elle doit appliquer côté serveur un garde-fou d'accès aux projets avant l'affichage des tâches.

La logique de contournement des restrictions `READMY` de GLPI ne doit jamais transformer cette page en accès global aux tâches : seules les tâches des projets réellement visibles par l'utilisateur peuvent être retournées.

### Colonnes par défaut

La vue globale affiche au minimum :

```text
Tâche de projet
Projet
Type
Statut
Priorité
Module
%
Fin planifiée
Durée planifiée
Parent
Utilisateur(s) affecté(s)
Groupe(s) affecté(s)
Dernière modification
```

La colonne `Projet` est ajoutée par rapport au dashboard d'un projet et doit être placée juste après la tâche.

Un clic sur le projet ouvre directement son `📊 Pilotage des tâches`.

## 5. Composants prévus

La V1.1 doit rester découpée en composants dédiés plutôt que d'étendre fortement `DashboardRenderer`.

### `ProjectMenuManager`

Responsabilités :

- construire le secteur principal `Projet` ;
- récupérer les projets en cours visibles ;
- trier les projets ;
- construire leurs URLs de dashboard ;
- injecter `📋 Projets` et `👤 Mes tâches` ;
- gérer un comportement dégradé si les projets dynamiques ne peuvent pas être chargés.

### `ProjectTabsManager`

Responsabilités :

- exposer les identifiants techniques des onglets concernés ;
- fournir au JS les informations nécessaires pour masquer `ProjectTask` et déplacer `DashboardTab` ;
- ne jamais bloquer la route native de l'onglet masqué.

### `ProjectTaskCreateLink`

Responsabilités :

- vérifier la possibilité d'afficher l'action de création ;
- construire l'URL native GLPI avec `projects_id` ;
- centraliser la construction du lien afin qu'elle soit testable.

### `MyTasksRenderer`

Responsabilités :

- préparer la vue globale ;
- injecter les critères `mes tâches` ;
- limiter aux projets non terminés visibles ;
- réutiliser les composants de recherche existants ;
- fournir un contexte AJAX propre à `ptd_scope=mytasks`.

### `ActiveProjectProvider`

Responsabilités :

- centraliser la définition `projet visible + non terminé` ;
- fournir les projets autorisés au menu et au renderer global ;
- éviter que le menu et `👤 Mes tâches` divergent fonctionnellement.

## 6. Sécurité

Les règles suivantes sont obligatoires :

- un projet invisible ne doit jamais apparaître dans le menu ;
- une tâche d'un projet invisible ne doit jamais apparaître dans `👤 Mes tâches` ;
- le filtrage de projet doit être réappliqué côté serveur lors des requêtes AJAX ;
- les paramètres GET ne sont jamais considérés comme preuve d'accès ;
- la création de tâche utilise les contrôles natifs GLPI ;
- le masquage d'un onglet est purement visuel et ne remplace pas les ACL ;
- aucune élévation permanente des droits en session ;
- tous les scopes temporaires de droits ou de recherche doivent restaurer exactement leur état initial.

## 7. Comportement en cas d'erreur

- Si un projet du menu devient inaccessible entre la génération du menu et le clic, GLPI doit refuser l'accès normalement.
- Si le chargement des projets dynamiques échoue, conserver les entrées statiques utilisables du secteur `Projet`.
- Si la page `👤 Mes tâches` ne peut pas calculer les groupes ou projets visibles, échouer fermé : aucun élargissement implicite des résultats.
- Si Fields est absent ou ses options de recherche ne sont pas détectées, ignorer `Module` / `Priorité` sans casser la page.

## 8. Tests attendus

### Menu principal

- secteur `Projet` présent pour un utilisateur ayant accès aux projets ;
- secteur absent sans droit de consultation des projets ;
- projet visible + non terminé inclus ;
- projet terminé exclu ;
- projet inaccessible exclu ;
- ordre alphabétique ;
- lien projet ouvrant le bon dashboard ;
- entrée `📋 Projets` correcte ;
- entrée `👤 Mes tâches` correcte et conditionnée aux droits ;
- invalidation du cache de menu après changement de projet.

### Onglets

- `DashboardTab` apparaît juste après `Projet` ;
- `Tâches de projet` n'est plus visible dans la barre d'onglets ;
- accès direct à l'onglet natif toujours possible ;
- désactivation du plugin restaure l'interface native.

### Création de tâche

- bouton visible avec droit CREATE ;
- bouton absent sans droit CREATE ;
- URL contenant le bon `projects_id` ;
- formulaire natif GLPI ouvert ;
- aucune duplication du formulaire dans le plugin.

### `👤 Mes tâches`

- affectation directe utilisateur incluse ;
- affectation via groupe incluse ;
- tâche non affectée exclue ;
- tâche d'un projet terminé exclue ;
- tâche d'un projet inaccessible exclue ;
- colonne Projet présente ;
- tri, pagination et recherche conservent les critères de sécurité ;
- les appels AJAX avec `ptd_scope=mytasks` reconstruisent les critères côté serveur ;
- les appels AJAX ne peuvent pas contourner le périmètre ;
- Fields absent ne provoque pas d'erreur.

## 9. Hors périmètre V1.1

- formulaire rapide de création de tâche dans le dashboard ;
- modale de création de tâche ;
- troisième niveau de menu GLPI ;
- remplacement ou suppression physique des routes natives `ProjectTask` ;
- modification du core GLPI ;
- affichage des tâches de projets terminés dans `👤 Mes tâches` ;
- nouveau schéma SQL propre au plugin.

## Critères d'acceptation

La V1.1 est acceptée lorsque :

1. le secteur principal `Projet` est utilisable et liste uniquement les projets en cours visibles ;
2. un clic sur un projet ouvre directement son dashboard ;
3. le dashboard permet d'ouvrir le formulaire natif de création d'une tâche déjà rattachée au projet ;
4. `📊 Pilotage des tâches` est placé juste après `Projet` ;
5. `Tâches de projet` est masqué visuellement mais reste techniquement accessible ;
6. `👤 Mes tâches` affiche uniquement les tâches de l'utilisateur ou de ses groupes appartenant à des projets en cours et visibles ;
7. recherche, tri, pagination et AJAX ne permettent jamais de sortir de ce périmètre ;
8. aucune modification du core GLPI n'est nécessaire.
