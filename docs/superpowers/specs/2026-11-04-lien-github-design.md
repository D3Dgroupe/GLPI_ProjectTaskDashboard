# Conception — Lien GitHub dédié (plugin Project Task Dashboard + connecteur GithubSync)

Date : 2026-11-04 · Dépôts : `D3Dgroupe/GLPI_ProjectTaskDashboard` (plugin GLPI, branche d'intégration `develop` créée ce jour)
et `D3Dgroupe/GithubSync` (connecteur Python). Contexte : `GithubSync/docs/lot2-recette.md`, `GithubSync/docs/poc-findings.md`.

## 1. Intention

Le connecteur GithubSync crée sous DEVELOPPEMENT (ID 2) un sous-projet par dépôt GitHub et une tâche native par issue ouverte.
Aujourd'hui le lien GLPI ↔ GitHub n'existe que sous forme de texte dans la description (un marqueur HTML `github_issue_id=N`
en tête, le lien de l'issue, les dates). Deux problèmes :
- **Pour les collègues** : rien ne distingue d'un coup d'œil une tâche venant de GitHub, et il faut ouvrir la description pour
  trouver le lien.
- **Pour le connecteur** : l'identité repose sur ce texte. La recette réelle a montré que l'éditeur de GLPI retire l'attribut du
  marqueur à l'enregistrement, et un collègue peut effacer ou réécrire la description. Un vrai champ, que personne n'a de raison
  de modifier, est une identité plus sûre.

Objectif : un **lien GitHub dédié**, stocké et affiché par le plugin, **écrit uniquement par le connecteur via l'API v2**.

## 2. Ce que voient les utilisateurs (décidé avec l'utilisateur)

- **Onglet *Pilotage des tâches*** d'un projet : une colonne « GitHub » affichant, pour chaque tâche synchronisée, le numéro
  de l'issue (ex. `#181`) en lien cliquable vers l'issue ; cellule vide pour les tâches manuelles (distinction immédiate).
- **Vue *Mes tâches*** du plugin : la même colonne.
- **Fiche d'une tâche synchronisée** : une ligne « Issue GitHub » avec le lien vers l'issue.
- **Fiche d'un sous-projet synchronisé** (ex. `DDSI`) : une ligne « Dépôt GitHub » avec le lien vers la page d'accueil du dépôt.
- **Listes natives GLPI** (Projets → Tâches, recherche) : la colonne est une option de recherche du plugin, que chacun peut
  ajouter ; elle est triable et filtrable (ex. « uniquement les tâches GitHub »). (Choix de l'assistant, accepté.)
- Le lien est **en lecture seule dans l'interface** : aucun formulaire ne permet de le modifier. (Choix de l'assistant, accepté.)

## 3. Non-objectifs / anti-objectifs

Non-objectifs : afficher l'état ou les commentaires de l'issue ; synchronisation GLPI → GitHub ; saisie manuelle du lien ;
l'option « Inclure les sous-projets » (issue #39, projet distinct) ; tout changement du comportement existant du plugin.
Anti-objectifs (échec même si « ça marche ») :
- un lien modifiable par un utilisateur sans droit de modification sur l'objet GLPI ;
- deux objets GLPI liés au même objet GitHub (doublon d'identité) ;
- une régression des vues existantes (Pilotage, Mes tâches, création depuis ticket, réglages emoji/menu) ;
- une désinstallation du plugin qui touche aux tâches ou projets GLPI ;
- un connecteur qui cesse de fonctionner si le plugin n'est pas à jour (il doit retomber sur le marqueur).

## 4. Plugin — modèle de données

Nouvelle table propre au plugin (`glpi_plugin_projecttaskdashboard_githublinks`, nom exact au choix du constructeur selon les
conventions GLPI 11), créée à l'installation / mise à jour et supprimée à la désinstallation :
- objet GLPI lié : type (`ProjectTask` ou `Project` uniquement) + ID ; **unique** par (type, ID) ;
- objet GitHub : genre (`issue` pour une tâche, `repository` pour un projet), **ID numérique GitHub** (64 bits), nom complet du
  dépôt (`owner/name`), numéro de l'issue (issues seulement), URL ; **unique** par (genre, ID GitHub) ;
- dates de création / modification.
Le README (qui dit « aucune table métier ») est corrigé : la table ne contient que des liens, les supprimer ne touche à aucune
tâche ni projet ; le connecteur sait les réécrire. Version du plugin incrémentée (0.6.3 → 0.7.0) pour que GLPI propose la mise à jour.

## 5. Plugin — API v2 (hook `api_controllers`, disponible depuis GLPI 11.0.0)

Un contrôleur d'API v2 propre au plugin (chemin exact au choix du constructeur, sous l'espace de routes des plugins, ex.
`/Plugin/Projecttaskdashboard/GithubLink`), authentifié comme le reste de l'API v2 (OAuth du compte technique) :
- **lecture** : liens filtrés par objet GLPI (type + ID), par liste d'IDs, ou par ID GitHub ; et liste des liens des tâches
  d'un projet donné (usage principal du connecteur : un appel par dépôt) ;
- **écriture** : créer ou mettre à jour (idempotent) le lien d'un objet GLPI ;
- **pas de suppression par l'API** dans cette version (le connecteur ne supprime jamais).
Contrôles : lecture si l'appelant a le droit de **voir** l'objet GLPI ; écriture si l'appelant a le droit de **modifier** l'objet
(projet ou tâche, droits et entités natifs de GLPI) ; type d'objet limité à `ProjectTask`/`Project` et cohérent avec le genre ;
l'objet GLPI doit exister et ne pas être à la corbeille ; URL validée (`https://github.com/…`) ; violation d'unicité (ID GitHub
déjà lié à un AUTRE objet) → refus explicite (code d'erreur distinct), jamais d'écrasement silencieux. Le schéma de la route
apparaît dans la documentation de l'API (`doc.json`).

## 6. Plugin — affichage

- Colonne « GitHub » dans *Pilotage des tâches* et *Mes tâches* (mêmes mécanismes de recherche native que les colonnes
  existantes : option de recherche du plugin sur `ProjectTask`, jointure sur la table des liens, rendu en lien `#numéro`).
- Lignes en lecture seule dans les fiches tâche et projet via le hook de formulaire déjà utilisé par le plugin (ne pas
  casser le rendu actuel des réglages emoji / menu rapide sur la fiche projet).
- Les liens s'ouvrent dans un nouvel onglet (`rel="noopener noreferrer"`).

## 7. Connecteur GithubSync — adaptation

- À chaque création ou adoption d'un sous-projet ou d'une tâche, le connecteur **écrit le lien** via la route du plugin.
- **Identité** : le lien du plugin devient la source principale pour retrouver les objets (une lecture par dépôt), le marqueur
  dans la description reste écrit et sert de **secours** ; en cas de désaccord entre les deux (lien ↔ marqueur), **alerte et
  aucune écriture** sur l'objet concerné.
- **Détection du plugin** : si la route n'existe pas (plugin absent ou ancienne version), le connecteur fonctionne comme
  aujourd'hui (marqueur seul) et le signale une fois (alerte informative), sans échec.
- **Migration** : au premier passage avec le plugin à jour, le connecteur écrit les liens manquants des 13 sous-projets et
  85 tâches existants (identifiés par leur marqueur), sans recréer ni dupliquer quoi que ce soit ; ces écritures comptent dans
  le disjoncteur.
- Les écritures de liens passent par les mêmes garde-fous (parent configuré, marqueur vérifié, budget, dry-run).

## 7 bis. Décisions complémentaires de l'assistant (suite à la relecture)

- **Identité et garde avant écriture** : quand le plugin est disponible, le lien du plugin **remplace** le marqueur comme preuve
  d'identité avant toute écriture sur une tâche ou un projet (c'est ce qui rend le connecteur insensible à l'éditeur GLPI).
  (a) lien présent et conforme, marqueur absent ou abîmé → **le lien fait foi**, l'écriture est autorisée et la mise à jour du
  contenu réécrit le marqueur ; (b) lien présent mais pointant vers un **autre** ID GitHub que celui attendu, ou lien et marqueur
  désignant deux ID différents → **alerte, aucune écriture** ; (c) pas de lien (plugin absent, ou objet pas encore migré) → garde
  actuelle par le marqueur.
- **Priorité des sources** : la base locale reste la correspondance de travail ; le lien est l'autorité de vérification et de
  reprise. Base vide → reprise **par le lien** (une lecture par dépôt), puis par le marqueur en secours. Ligne de base qui
  désigne la tâche X alors que le lien de l'issue désigne Y → alerte de dérive, aucune écriture, jamais de réaffectation
  automatique. Lien présent mais tâche supprimée ou introuvable (404) → ligne « orphaned », jamais recréée (règle actuelle).
- **Cycle de vie des liens dans le plugin** : mise à la corbeille d'une tâche ou d'un projet → le lien est **conservé** (une
  restauration le retrouve) ; **purge** → le lien est **supprimé** (hook de purge à ajouter pour `ProjectTask` et `Project`, en
  plus de celui existant sur `Project`). Le connecteur ne recrée jamais une tâche supprimée, donc un lien conservé pour un objet
  à la corbeille ne bloque aucune reprise ; un refus d'unicité est traité par le connecteur comme une dérive (alerte, aucune écriture).
- **Disjoncteur** : une écriture de lien compte pour 1 écriture. La migration (≈ 98 liens) tient largement sous le plafond de
  600 ; si elle était interrompue, elle reprend au cycle suivant sans doublon. Un lien **déjà correct** n'est pas réécrit
  (comparaison avec la lecture groupée par dépôt), donc un cycle stable reste à zéro écriture.
- **Détection du plugin** : le plugin expose une route de statut légère (version, sans donnée) ; le connecteur l'interroge au
  début de chaque cycle. 404 sur cette route → plugin absent ou trop ancien → mode « marqueur seul », alerte informative émise
  une fois par processus et consignée dans le fichier d'état. Toute autre erreur (réseau, 401/403, 5xx) est une **erreur**,
  jamais un passage silencieux en mode dégradé.
- **Droits et accès réels** : l'accès du compte technique (OAuth, scope `api`) aux routes du plugin et ses droits de
  modification sur DEVELOPPEMENT (entité 6) sont **vérifiés sur la production au début de la recette**, par une lecture sur la
  route de statut puis une écriture de lien sous le projet de test 9 ; le GLPI jetable sert à tester la logique des droits, pas
  à prouver la configuration de production.
- **GLPI jetable** : Docker 26 est disponible sur la machine de développement ; image GLPI 11 à la version 11.0.x de la production
  (à relever sur la production en début de plan ; à défaut, la dernière 11.0.x) et MariaDB.
- **Renommage d'un dépôt** : le lien du projet (nom complet, URL) est mis à jour dans la même écriture que le nom.
- **Garde du parent** : toute écriture de lien par le connecteur passe par la garde existante (objet sous le parent configuré).

## 8. Tests et recette

- Il n'existe pas de GLPI de test : un **GLPI 11 jetable en Docker** est monté localement (MariaDB + GLPI 11 à la même version
  majeure que la production, plugin monté depuis le dépôt) pour développer et tester la table, la route d'API (de bout en bout
  en HTTP, avec un client OAuth de test), les droits et l'affichage. Les tests unitaires du plugin suivent les conventions du dépôt.
- Le connecteur est testé avec ses faux clients plus un test de bout en bout contre le GLPI jetable.
- Recette réelle ensuite : installation de la nouvelle version du plugin sur la production par l'utilisateur, puis passage du
  connecteur **d'abord sous le projet de test 9**, ensuite sous DEVELOPPEMENT, avec contrôle visuel des colonnes et des fiches.

## 9. Décisions

De l'utilisateur : option « plugin + route d'API dédiée » retenue (plutôt que le plugin Fields ou les champs natifs) ; colonne
dans *Pilotage* et *Mes tâches* ; liens issue et dépôt cliquables dans les fiches ; branche `develop` créée pour le plugin
(PR vers `develop`, puis `develop` → `main`).
De l'assistant (à valider en relisant) : lien en lecture seule dans l'interface ; option de recherche disponible dans les listes
natives ; pas de suppression par l'API ; lien prioritaire et marqueur en secours ; désaccord lien/marqueur = alerte sans écriture ;
GLPI jetable en Docker pour les tests ; version 0.7.0.

## 10. Laissé au constructeur

Nom exact de la table et des colonnes, chemin exact et format JSON de la route (dans les conventions de l'API v2 GLPI 11),
organisation du contrôleur, mécanique de migration de schéma du plugin, rendu exact de la colonne et des lignes de fiche,
outillage du GLPI jetable (images, script de démarrage), découpage des tests.
