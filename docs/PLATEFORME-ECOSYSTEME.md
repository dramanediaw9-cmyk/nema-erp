# Plateforme & Ecosysteme - Nema ERP

Ce document decrit la premiere vague qui ouvre les 4 chantiers strategiques du produit :

- largeur fonctionnelle
- packaging commercial et deploiement industrialise
- ecosysteme extensions / API / partenaires
- profondeur initiale RH, paie, production, commerce et projets

## Ce qui est maintenant dans le produit

- page `Plateforme` dans l application pour lire le packaging et les capacites API
- endpoint API `GET /api/v1/platform/capabilities`
- modules `Capital humain`, `Paie`, `Projets`, `Production` et `Commerce unifie`
- scripts locaux `start-nema-erp.ps1` et `stop-nema-erp.ps1`
- smoke tests navigateur et CI GitHub sur les parcours critiques

## Packaging et exploitation

- verification applicative : `php artisan test`
- smoke navigateur : `npm run e2e:smoke`
- exploitation : `php artisan nema:ops:monitor-app`
- lancement local : `powershell -ExecutionPolicy Bypass -File .\scripts\start-nema-erp.ps1`
- arret local : `powershell -ExecutionPolicy Bypass -File .\scripts\stop-nema-erp.ps1`

## API et ecosysteme

- authentification API par `Bearer token` ou `X-Api-Key`
- ressources `workspace`, `products`, `partners`, `sales-invoices`, `payments`, `integration-events`
- endpoint de capacites `platform/capabilities` pour exposer le socle aux integrateurs
- outbox d integration pour brancher middleware, BI ou SI tiers

## Vague suivante recommandee

1. localisation comptable OHADA / SYSCOHADA plus explicite
2. endpoints API supplementaires pour RH, projets et production
3. parametrage metier plus profond sur paie et production
4. package SaaS / supervision / sauvegardes multi-client encore plus outille
