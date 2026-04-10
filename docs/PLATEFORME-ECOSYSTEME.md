# Plateforme & Ecosysteme - Nema ERP

Ce document decrit la premiere vague qui ouvre les 4 chantiers strategiques du produit :

- largeur fonctionnelle
- packaging commercial et deploiement industrialise
- ecosysteme extensions / API / partenaires
- profondeur initiale RH, paie, production, commerce et projets

## Ce qui est maintenant dans le produit

- page `Plateforme` dans l application pour lire le packaging et les capacites API
- endpoint API `GET /api/v1/platform/capabilities`
- endpoint API `GET /api/v1/accounting/localization`
- modules `Capital humain`, `Paie`, `Projets`, `Production` et `Commerce unifie`
- profondeur RH avec demandes de conge
- profondeur paie avec bulletins et lignes salariales
- profondeur production avec nomenclatures et couts matieres
- page `Comptabilite > Localisation OHADA`
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
- ressources metier d expansion `hr/departments`, `hr/employees`, `hr/leave-requests`, `payroll/runs`, `payroll/slips`, `projects`, `manufacturing/boms`, `production-orders`, `commerce/channels`
- endpoint de capacites `platform/capabilities` pour exposer le socle aux integrateurs
- endpoint `accounting/localization` pour exposer la lecture OHADA / SYSCOHADA aux integrateurs
- outbox d integration pour brancher middleware, BI ou SI tiers

## Vague suivante recommandee

1. profondeur projets avec jalons, budget detaille et affectations
2. profondeur commerce unifie avec orchestration catalogues/canaux
3. automatisation comptable plus profonde autour de la paie et de la production
4. package SaaS / supervision / sauvegardes multi-client encore plus outille
