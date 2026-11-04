# Changelog

## 0.7.0

### Ajouté

- Lien GitHub : table `glpi_plugin_projecttaskdashboard_githublinks` (un lien par tâche de projet / projet, identifiant GitHub unique),
  créée à l'installation/mise à jour, supprimée à la désinstallation ; le lien suit la purge de l'objet (conservé en corbeille).
- API High-Level v2 : `GET /Plugin/Projecttaskdashboard/Status`, `GET|PUT /Plugin/Projecttaskdashboard/GithubLink` (écriture idempotente,
  droits de l'objet, pas d'oracle d'existence, refus des doublons, du re-pointage et des objets en corbeille).
- Affichage en lecture seule : colonne « GitHub » dans les listes de tâches (texte brut `#numéro` dans les exports CSV/PDF/ODS/XLSX),
  ligne « Issue GitHub » sur la tâche, ligne « Dépôt GitHub » sur le projet.
- Vidage du cache des routes API v2 à l'installation, l'activation, la désactivation et la désinstallation du plugin.
- GLPI 11.0.12 jetable (`dev/`, Docker) avec données de test, option `WITH_FIELDS=1` (plugin Fields 1.24.6) et tests de bout en bout de l'API.

### Corrigé

- Création d'une tâche depuis un ticket : le champ Fields « Module » (dropdown ITILCategory) n'était jamais enregistré. La clé de saisie
  était préfixée deux fois (`itilcategories_id_itilcategories_id_modulefield`) alors que Fields stocke déjà le nom préfixé ; la catégorie
  du ticket est désormais bien recopiée. Les tâches créées auparavant gardent un Module vide (à compléter à la main si besoin).

### Mise à jour depuis 0.6.3

Voir le README (« Mise à jour 0.6.3 → 0.7.0 ») : mise à jour du code puis `glpi:plugin:install` ; une table est créée, rien n'est modifié.

## 0.6.3

Dernière version avant le lien GitHub (voir l'historique Git).
