# Synchronisation des produits Odoo

## Fonctionnement

Le centre `Imports > Synchronisation Odoo` connecte Nema ERP a Odoo par JSON-RPC ou XML-RPC. Les secrets sont chiffres avec la cle Laravel et ne sont jamais renvoyes dans l'interface.

La synchronisation traite `product.template`, puis `product.product`, par lots ordonnes sur l'ID Odoo. Chaque execution conserve sa phase et son curseur : une execution interrompue peut donc reprendre sans recommencer.

Les donnees gerees comprennent : nom, SKU, code-barres, categorie, prix de vente, cout, taxes, unites, images, descriptions, statut, variantes, attributs, valeurs, fournisseurs et quantite disponible.

La deduplication utilise successivement la correspondance ID Odoo, le SKU puis le code-barres. Un hash de la source evite les mises a jour inutiles. L'import incremental utilise `write_date` entre la derniere synchronisation reussie et l'heure de debut de l'execution.

## Mise en service

1. Executer les migrations : `php artisan migrate --force`.
2. Verifier que le cron Hostinger lance `php artisan schedule:run` chaque minute.
3. Ouvrir `/imports/odoo`, enregistrer l'URL, la base, l'utilisateur et le mot de passe ou la cle API.
4. Cliquer sur `Tester`.
5. Lancer d'abord une synchronisation complete, puis utiliser les synchronisations incrementales.

Le planificateur traite automatiquement la file `imports` toutes les minutes. Un worker permanent peut aussi etre utilise :

`php artisan queue:work --queue=imports --tries=3 --timeout=180`

## Ligne de commande

- File d'attente : `php artisan nema:odoo:sync-products 1 --mode=incremental`
- Traitement immediat : `php artisan nema:odoo:sync-products 1 --mode=full --now`

## Reprise et diagnostic

Le bouton `Reprendre` repart du dernier ID valide. Les erreurs globales et les erreurs par produit sont conservees dans `odoo_product_import_errors`. Le tableau de progression indique le nombre cree, mis a jour, ignore ou en erreur.

Pour de tres gros catalogues, utiliser la file `database` ou `redis`, conserver des lots de 100 a 500 et laisser le worker actif jusqu'a la fin.
