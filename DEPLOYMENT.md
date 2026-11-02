# Déploiement de SportSpace

## Prérequis

- PHP 8.0 ou plus récent avec `pdo_mysql`, `mbstring` et `fileinfo` activés.
- L'extension PHP GD est recommandée pour réduire automatiquement les images de drapeau haute résolution. Les envois sont limités à 8 Mo et les images à 40 mégapixels.
- MySQL ou MariaDB et Apache 2.4 avec `AllowOverride All` pour que les fichiers `.htaccess` soient appliqués.
- Un certificat HTTPS valide en production.

## Configuration

1. Déployer les fichiers dans le document root du site (ou configurer le virtual host pour pointer vers ce dossier).
2. Copier `.env.example` vers `.env`, configurer `APP_URL` avec l'URL publique HTTPS exacte de l'application et renseigner les paramètres réels de la base. Ne jamais publier `.env` ni conserver les valeurs d'exemple en production. Les variables d'environnement du serveur peuvent aussi être utilisées à la place du fichier.
3. Utiliser un compte SQL dédié à l'application avec `SELECT`, `INSERT`, `UPDATE` et `DELETE` sur la base SportSpace. Réserver les privilèges `CREATE`, `ALTER` et `DROP` à un compte séparé pour l'installation et les migrations.
4. Laisser `APP_ENV=production` et `SESSION_COOKIE_SECURE=true` en production. Configurer PHP avec `display_errors=Off` et `log_errors=On`.
5. Donner au serveur web un accès en écriture au seul dossier `photos/` pour les images téléversées ; les autres fichiers du projet doivent rester en lecture seule.

## Base de données et comptes

- Pour une nouvelle base, exécuter `database/schema_app.sql`, puis `database/schema_users.sql`.
- Pour une ancienne base qui n'a pas encore les colonnes de statut/report, sauvegarder d'abord la base et exécuter une seule fois `database/upgrade_statut_matchs.sql` avant de déployer le code actuel.
- Pour l'ancienne base SportSpace dont `equipe` et `matchs` sont encore en MyISAM, sauvegarder la base, vérifier les requêtes de contrôle en tête de `database/migrate_matchs_integrity.sql`, puis exécuter cette migration une seule fois. Elle convertit les tables en InnoDB, aligne les clés, autorise l'absence de score et ajoute les relations/index nécessaires. Elle suppose que les tables ne possèdent pas déjà les clés étrangères de `schema_app.sql`.
- Pour une base existante créée avec l'ancien `schema_users.sql`, sauvegarder d'abord la base puis exécuter **une seule fois** `database/migrate_remove_account_lock.sql`. Le code de connexion ne lit ni n'écrit ces champs ; cette migration retire aussi les anciennes données de verrouillage.
- Les inscriptions publiques créent toujours un compte `user`. Après avoir vérifié l'adresse de l'administrateur initial, lui accorder le rôle administrateur avec une requête SQL ciblée :

  ```sql
  UPDATE users
  SET role = 'admin'
  WHERE email = 'admin@votre-domaine.example';
  ```

  Vérifier qu'une seule ligne a été modifiée. Ne rendez jamais l'inscription publique administrateur automatiquement.

## Contrôles avant ouverture

- Vérifier que le site est servi en HTTPS, que les pages privées redirigent vers la connexion et que seules les personnes autorisées ont le rôle `admin`.
- Les pages de consultation (équipes, calendrier et scores) sont publiques ; les pages de gestion restent réservées aux administrateurs. Les filtres de consultation sont des URL GET partageables.
- Vérifier que les pages d'erreur ne révèlent pas les détails SQL et consulter les journaux serveur en cas de problème.
- Vérifier les sauvegardes régulières de la base et des fichiers `photos/`.
- La connexion ne verrouille pas les comptes. Pour limiter les tentatives automatisées en production, configurer une protection au niveau du serveur, du reverse proxy ou d'un WAF, sans verrouillage de compte.
- `sitemap.php` expose les pages publiques et les calendriers présents dans la base. Après publication, soumettre son URL dans Google Search Console et référencer cette URL dans le `robots.txt` placé à la racine du domaine. Le sitemap facilite la découverte des URL mais ne garantit ni indexation ni positionnement.

## Notes Apache

Sous Apache avec `php_module`, le `.htaccess` configure la limite d'envoi PHP à 8 Mo (`post_max_size` à 10 Mo). Avec PHP-FPM ou un hébergement qui ignore `php_value`, configurer ces deux limites dans la configuration PHP du serveur.

Les `.htaccess` de ce dépôt désactivent l'indexation des dossiers, bloquent l'accès web à `.env`, aux scripts de configuration, aux inclusions et aux fichiers SQL, empêchent l'exécution directe de scripts dans `photos/`, et définissent une politique CSP compatible avec les ressources locales et Google Fonts. Activez `mod_headers` pour appliquer aussi les en-têtes aux fichiers statiques ; les réponses PHP/XML les émettent également. Si l'hébergeur désactive `AllowOverride`, appliquer les mêmes règles dans le virtual host Apache avant la mise en ligne.
