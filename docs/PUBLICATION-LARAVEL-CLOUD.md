# Publication Laravel Cloud

Ce guide prepare **Nema ERP** pour une mise en ligne rapide sur **Laravel Cloud**.

## Objectif

Obtenir une premiere publication publique avec :

- application Laravel accessible sur un domaine public
- base MySQL de production
- stockage objet pour images produit et pieces jointes
- scheduler actif
- logs de base et surveillance minimale
- assets Vite compiles

## Ce qui est deja pret dans le projet

- support `s3` / objet via `league/flysystem-aws-s3-v3`
- scripts Composer utiles :
  - `composer run assets:build`
  - `composer run build:cloud`
  - `composer run deploy:cloud`
- scheduler metier dans [routes/console.php](../routes/console.php)
- manifest et assets front compiles
- variables de prod dans [.env.laravel-cloud.example](../.env.laravel-cloud.example)
- disques configurables :
  - `PRODUCT_MEDIA_DISK`
  - `DOCUMENT_ATTACHMENT_DISK`

## Ressources Laravel Cloud a prevoir

1. Une application Laravel Cloud reliee au depot Git.
2. Une base de donnees MySQL.
3. Un KV store / Redis pour `cache`, `session` et, si voulu, `queue`.
4. Un object storage compatible S3.
5. Un domaine public avec HTTPS.

## Ce que le code ne fait pas tout seul

Le projet est maintenant prepare pour la publication, mais il reste des elements externes a fournir :

- un depot Git distant propre
- un compte Laravel Cloud
- un domaine public
- des credentials MySQL / Redis / S3 / SMTP de production
- la decision de ne **pas** injecter les seeders de demonstration en base client

## Variables d environnement recommandees

Base de depart : [.env.laravel-cloud.example](../.env.laravel-cloud.example)

Points importants :

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://erp.example.com

LOG_CHANNEL=stack
LOG_STACK=stderr,daily
LOG_LEVEL=info

SESSION_DRIVER=redis
SESSION_STORE=redis
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

CACHE_STORE=redis
QUEUE_CONNECTION=redis

FILESYSTEM_DISK=s3
PRODUCT_MEDIA_DISK=s3
DOCUMENT_ATTACHMENT_DISK=s3
```

## Build et deploiement recommandes

### Build command

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
composer run build:cloud
```

### Deploy command

```bash
php artisan migrate --force
```

Option compacte si tu preferes les scripts du projet :

```bash
composer run deploy:cloud
```

Les caches `config`, `routes` et `views` sont volontairement generes pendant le **build** et non pendant le **deploy**.

## Scheduler

Le scheduler doit etre actif en production. Les commandes critiques deja planifiees sont :

- `nema:notifications:dispatch-outbound --limit=50`
- `nema:notifications:sync-internal`
- `nema:integrations:dispatch-outbox --limit=50`
- `nema:ops:health-check --store`
- `nema:ops:monitor-app`
- `nema:ops:outbox-prune --days=30`
- `nema:ops:backup-run --keep=7`
- `nema:ops:backup-verify`

## Queue worker

Le coeur actuel de l application repose surtout sur le scheduler. Un worker de queue simple reste recommande pour les evolutions et les traitements Laravel standards :

```bash
php artisan queue:work --tries=3 --timeout=120 --max-time=3600
```

## Stockage des fichiers

En production cloud :

- **ne pas compter sur le disque local** pour les uploads metier
- ne pas compter sur `php artisan storage:link` pour la persistance
- utiliser `s3` pour :
  - images produit
  - pieces jointes documentaires

Le projet sait maintenant stocker ces fichiers sur un disque configurable et memorise le disque des images produit.

## Seeders et donnees de demo

Pour une vraie production :

- lancer les **migrations**
- ne pas lancer les seeders de demonstration sur la base client
- creer seulement les comptes, agences, caisses et parametres necessaires

## Check rapide avant ouverture au public

- `APP_DEBUG=false`
- `APP_URL` exact
- base prod isolee
- Redis/KV branche
- stockage objet branche
- domaine + HTTPS actifs
- scheduler actif
- au moins un backup verifie
- test manuel :
  - connexion
  - ouverture caisse
  - vente
  - paiement
  - rapport du jour
  - upload image produit
  - upload piece jointe

## Commandes utiles apres mise en ligne

```bash
php artisan test
php artisan schedule:list
php artisan nema:ops:health-check --store
php artisan nema:ops:monitor-app
php artisan nema:ops:backup-run --keep=7
php artisan nema:ops:backup-verify
```
