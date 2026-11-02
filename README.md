# SportSpace

SportSpace est une application web de gestion et de consultation d'équipes et de matchs. Elle permet de consulter les équipes, le calendrier et les scores, et de gérer les données selon le rôle du compte connecté.

## Fonctionnalités

- Consultation publique des équipes, des matchs du jour et des résultats, avec filtres partageables.
- Création de compte et connexion.
- Gestion des équipes avec ajout, modification et suppression, y compris le téléversement d'un drapeau.
- Gestion des matchs, de leurs statuts (en cours, terminé, reporté ou annulé) et de leurs scores.
- Gestion des comptes et des rôles réservée aux administrateurs.
- Interface en français, feuilles de style et scripts servis localement, sitemap généré par l'application.

## Prérequis

- PHP 8.0 ou plus récent, avec `pdo_mysql`, `mbstring` et `fileinfo` activés.
- MySQL ou MariaDB.
- Apache 2.4 avec `AllowOverride All` pour appliquer les règles des fichiers `.htaccess`.
- L'extension PHP GD est recommandée pour redimensionner les grandes images téléversées.
- HTTPS est requis en production.

L'application ne nécessite actuellement ni Composer ni npm pour s'exécuter. Les polices Google Fonts sont chargées depuis Google lorsqu'une connexion Internet est disponible.

## Installation locale avec WAMP

1. Place le dossier dans le répertoire web de WAMP ou configure un virtual host Apache vers ce dossier.
2. Crée une base MySQL/MariaDB vide en `utf8mb4`, puis importe `database/schema_app.sql` et `database/schema_users.sql`, dans cet ordre. Les autres fichiers SQL sont des migrations destinées aux bases déjà existantes ; consulte leurs commentaires et `DEPLOYMENT.md` avant de les utiliser.
3. Copie `.env.example` vers `.env` et renseigne les paramètres locaux :

   ```dotenv
   APP_ENV=development
   APP_URL=http://localhost/td7/SportSpace
   APP_TIMEZONE=America/Cayenne

   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=sportspace
   DB_USER=ton_utilisateur_mysql
   DB_PASS=ton_mot_de_passe

   SESSION_COOKIE_SECURE=false
   ```

   Adapte `APP_URL` au nom et au chemin utilisés par ton virtual host. `SESSION_COOKIE_SECURE=false` convient uniquement au développement local en HTTP ; utilise HTTPS et `true` en production. Ne publie jamais `.env`.

4. Assure-toi que le serveur web peut écrire dans `photos/` afin d'enregistrer les drapeaux envoyés depuis l'interface.
5. Si le dossier reste à `C:/wamp64/www/td7/SportSpace`, ouvre `http://localhost/td7/SportSpace/`, puis crée un compte. Adapte l'URL si tu utilises un autre emplacement ou un virtual host.
6. Pour attribuer le rôle administrateur au premier compte, exécute une requête SQL ciblée avec son adresse :

   ```sql
   UPDATE users
   SET role = 'admin'
   WHERE email = 'admin@exemple.test';
   ```

   Vérifie qu'une seule ligne a été modifiée. Les inscriptions publiques reçoivent le rôle `user` par défaut.

Pour les étapes de déploiement, les migrations et la configuration Apache/PHP, consulte [DEPLOYMENT.md](./DEPLOYMENT.md).

## Rôles

| Rôle | Accès |
| --- | --- |
| `user` | Consultation et accès à son compte |
| `agent` | Consultation et gestion des équipes, matchs et scores |
| `admin` | Accès agent et gestion des comptes et des rôles |

Les pages de consultation restent publiques. L'accès aux actions de gestion est contrôlé côté serveur.

Dans [gestioncomptes.php](./gestioncomptes.php), un administrateur peut changer les rôles et supprimer les autres comptes. L'application empêche de retirer ou de supprimer le dernier administrateur, ainsi que de supprimer ou rétrograder le compte administrateur actuellement connecté.

## Pages principales

| Fichier | Fonction |
| --- | --- |
| `index.php` | Liste des équipes |
| `matchdujour.php` | Calendrier des matchs et filtre par date |
| `scorematch.php` | Résultats et filtre par équipe |
| `register.php`, `login.php`, `logout.php` | Création de compte et authentification |
| `creerequipe.php` | Gestion des équipes et de leurs images (agent/admin) |
| `ajoutermatch.php` | Création, modification et suppression des matchs (agent/admin) |
| `ajouterscore.php` | Saisie des scores (agent/admin) |
| `gestioncomptes.php` | Gestion des comptes et des rôles (admin) |

## Données et fichiers téléversés

Les définitions et migrations de la base sont conservées dans `database/`. Les images de drapeaux fournies avec le projet sont dans `photos/` ; les nouveaux téléversements y sont enregistrés sous le nom `drapeau_*` et ne sont pas suivis par Git. En production, sauvegarde et déploie les images téléversées avec la base, car les lignes des équipes les référencent.

## Fichiers à ne pas publier sur GitHub

Le fichier `.gitignore` exclut déjà les catégories suivantes :

- `.env` et les autres fichiers `.env.*`, qui peuvent contenir les identifiants de la base et des paramètres privés. `.env.example` reste publiable car il ne contient que des valeurs factices.
- Les journaux (`*.log`, `error_log`), caches, fichiers temporaires et dossiers de dépendances générés localement (`vendor/`, `node_modules/`).
- Les images téléversées (`photos/drapeau_*`), ainsi que les éventuels fichiers et dossiers de sauvegarde/export de base (`database/backups/`, `database/dumps/`, `*.sql.gz`, `*.dump`, `*.bak`, `*.backup`, `*.sqlite*`).
- Les fichiers propres à un poste de travail (`.idea/`, `.vscode/`, `.DS_Store`, `Thumbs.db`, `desktop.ini`).
- `Fiche1_3_RGPD.pdf`, document présent dans le dossier mais non requis par l'application.

Les fichiers `database/schema_*.sql` et `database/migrate_*.sql` ne sont pas des exports de données : ils décrivent la structure de la base et les migrations, et doivent rester dans le dépôt. Place tout export contenant des données dans `database/backups/` ou `database/dumps/` pour qu'il soit ignoré. Les drapeaux statiques de `photos/` sont également nécessaires. Si `.env` ou un autre fichier sensible a déjà été ajouté à Git, `.gitignore` ne le retire pas de l'historique : il faut le retirer de l'index et remplacer les secrets exposés.

## Structure du projet

```text
SportSpace/
├── assets/          # CSS et JavaScript de l'application
├── config/          # Chargement de l'environnement et connexion PDO
├── css/             # Bootstrap fourni localement
├── database/        # Schémas et migrations SQL
├── includes/        # Authentification, fonctions et éléments partagés
├── lang/            # Fichiers de langue
├── photos/          # Drapeaux statiques et images téléversées
├── *.php            # Pages de l'application
├── .env.example     # Modèle de configuration locale
└── DEPLOYMENT.md    # Instructions de mise en production
```
