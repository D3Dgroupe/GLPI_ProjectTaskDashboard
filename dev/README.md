# GLPI 11 jetable pour développer le plugin

GLPI **11.0.12** (image officielle `glpi/glpi`, comme la production) + MariaDB 10.11, en Docker, sur `127.0.0.1:8088` uniquement.
Le dépôt est monté en lecture-écriture dans `plugins/projecttaskdashboard` : le code modifié est vu immédiatement.
Identifiants jetables seulement ; ne jamais y mettre de donnée de production.

## Démarrer / arrêter

```bash
bash dev/up.sh      # démarre, installe GLPI, installe+active le plugin, sème les données, écrit dev/.env.test
bash dev/down.sh    # détruit conteneurs ET volumes (base, fichiers) et dev/.env.test
```

`up.sh` est idempotent : sur un environnement déjà semé il ne fait que le démarrer ; `down.sh` puis `up.sh` recrée le même
environnement (mêmes IDs, nouveaux mots de passe/secret OAuth). Environ 1 min.
Variables : `GLPI_PORT` (défaut `8088`), `PLUGIN_SRC` (checkout du plugin à monter, défaut = ce dépôt).

Interface web : <http://127.0.0.1:8088> — compte `glpi` / `glpi` (super-admin).

### Monter une autre version du plugin (ex. 0.6.3 pour tester la mise à jour)

```bash
git worktree add /tmp/ptd-main main
PLUGIN_SRC=/tmp/ptd-main bash dev/up.sh          # installe la 0.6.3 (seed.php est monté à part : dev/ absent de main sans importance) (seed.php est monté à part, dev/ absent de main : sans importance)
# puis passer à la version de travail, sans perdre la base :
PLUGIN_SRC=$PWD docker compose -f dev/docker-compose.yml up -d --force-recreate glpi
docker compose -f dev/docker-compose.yml exec -w /var/www/glpi glpi bin/console plugin:install projecttaskdashboard -u glpi -n
```

(`plugin:install` sur un plugin déjà installé dont la version a changé exécute la mise à jour ; `--force` réexécute l'installation.)

## Réinstaller le plugin après une modification

Le code PHP/JS/CSS est servi directement. Pour rejouer `plugin_projecttaskdashboard_install()` (migration de schéma) :

```bash
docker compose -f dev/docker-compose.yml exec -w /var/www/glpi glpi \
  bin/console plugin:install projecttaskdashboard -u glpi --force -n
docker compose -f dev/docker-compose.yml exec -w /var/www/glpi glpi bin/console plugin:activate projecttaskdashboard
```

Désinstallation : `... bin/console plugin:uninstall projecttaskdashboard -n`.

## Journaux PHP / SQL

```bash
docker compose -f dev/docker-compose.yml exec glpi tail -f /var/glpi/logs/php-errors.log
docker compose -f dev/docker-compose.yml exec glpi tail -f /var/glpi/logs/sql-errors.log
docker compose -f dev/docker-compose.yml logs -f glpi        # Apache + cron
```

## Données et fichier `dev/.env.test`

Créées par `dev/seed.php` (classes GLPI, pas de SQL) et `bin/console user:create` :

- OAuth : client `PTD tests`, grant `password`, scope `api`.
- Utilisateurs : `ptd_tech` (profil « PTD technique » : lecture + modification des projets/tâches, entité racine récursive) et
  `ptd_ro` (profil « PTD lecture seule » : aucune modification). Mots de passe aléatoires dans `.env.test`.
- Entités : racine + « Autre » ; racine : projet parent, sous-projets A et B, 4 tâches ; « Autre » : 1 projet + 1 tâche.
- `dev/.env.test` (ignoré par Git) contient l'URL de l'API v2 (`.../api.php/v2.3`), le jeton OAuth (`/api.php/token`), client id/secret,
  logins/mots de passe, tous les IDs. L'API expose `doc.json` sans authentification.

Jeton (grant password) :

```bash
set -a; . dev/.env.test; set +a
curl -s -d grant_type=password -d client_id=$GLPI_OAUTH_CLIENT_ID -d client_secret=$GLPI_OAUTH_CLIENT_SECRET \
  -d username=$GLPI_TECH_LOGIN -d password=$GLPI_TECH_PASSWORD -d scope=api $GLPI_OAUTH_TOKEN_URL
```

Note : `GET /Setup/Plugin` exige le compte admin `glpi`, pas `ptd_tech`.
