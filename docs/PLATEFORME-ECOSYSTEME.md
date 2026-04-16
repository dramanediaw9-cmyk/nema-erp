# Plateforme & Ecosysteme - Nema ERP

Ce document decrit la premiere vague qui ouvre les 4 chantiers strategiques du produit :

- largeur fonctionnelle
- packaging commercial et deploiement industrialise
- ecosysteme extensions / API / partenaires
- profondeur initiale RH, paie, production, commerce et projets

## Ce qui est maintenant dans le produit

- page `Plateforme` dans l application pour lire le packaging et les capacites API
- endpoint API `GET /api/v1/platform/capabilities`
- endpoints API `GET/POST/PATCH /api/v1/automation/rules...`
- endpoint API `GET/PATCH /api/v1/platform/deployment-profile`
- endpoint API `GET /api/v1/platform/tenant-readiness`
- endpoint API `GET /api/v1/platform/openapi`
- endpoints API `GET/POST/PATCH /api/v1/platform/connections...`
- endpoint API `PATCH /api/v1/platform/connections/{integrationConnection}/secrets`
- endpoint API `GET /api/v1/accounting/localization`
- modules `Capital humain`, `Paie`, `Projets`, `Production` et `Commerce unifie`
- profondeur RH avec demandes de conge
- profondeur paie avec bulletins et lignes salariales
- profondeur production avec nomenclatures et couts matieres
- page `Comptabilite > Localisation OHADA`
- hub `Plateforme` avec registre de connexions partenaires, hygiene des jetons API, sante outbox/inbound et runbooks integrateurs
- hub `Automatisations` avec regles noyau, signaux transverses, cooldown et historique d execution
- profil de deploiement par societe avec offre, mode d hebergement, support, sauvegarde, monitoring et readiness score
- portefeuille readiness inter-societes pour consolider les societes d un meme tenant et prioriser les escalades
- gouvernance des secrets connecteurs avec mode d authentification, rotation, expiration et responsable
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
- ressources plateforme `platform/connections` pour piloter les connecteurs partenaires et leur sante
- ressources noyau `automation/rules` pour piloter les regles d automatisation transverses
- ressource `platform/connections/{integrationConnection}/secrets` pour piloter la rotation des secrets et les echeances critiques
- ressource `platform/deployment-profile` pour piloter l offre, le mode de deploiement et la readiness par societe
- ressource `platform/tenant-readiness` pour consolider les scores readiness, la sante portefeuille et les priorites par societe
- contrat integrateur OpenAPI via `platform/openapi` et export web `plateforme/openapi.json`
- contrat integrateur OpenAPI enrichi avec les endpoints `automation/rules`
- endpoint de capacites `platform/capabilities` pour exposer le socle aux integrateurs
- endpoint `accounting/localization` pour exposer la lecture OHADA / SYSCOHADA aux integrateurs
- outbox d integration pour brancher middleware, BI ou SI tiers
- scheduler noyau `php artisan nema:automation:run` pour executer les regles transverses
- registre des connexions partenaires avec type, mode de synchro, responsable, derniere synchro et statut de sante
- checks operations qui remontent les connecteurs critiques, les synchros trop anciennes et les jetons API a rotation proche
- checks operations qui remontent aussi les secrets de connecteurs expires, a tourner ou sans proprietaire clair
- score de readiness qui combine checks systeme, sauvegardes, monitoring, queue, mailer, stockage objet et exercices de restauration
- vue tenant qui compare les societes actives, calcule une moyenne readiness et remonte les societes a risque

## Vague suivante recommandee

1. automatisation comptable plus profonde autour de la paie, de la production et du cash
2. brancher les regles noyau a des actions externes selectives: webhook, mail et orchestration partenaire
3. package SaaS multi-tenant plus poussé avec bascule de readiness tenant vers vrais tenants clients et exports exploitables support/commercial
4. automatisation de rotation et d audit des secrets avec coffre-fort ou webhook de rotation fournisseur
