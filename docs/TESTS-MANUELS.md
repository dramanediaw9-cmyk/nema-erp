# Tests Manuels - Nema ERP

## Preparation

- se connecter en `manager@nema-erp.test`
- verifier qu une agence active est definie
- ouvrir `Dashboard`

## Scenario 0 - Confort de saisie

1. ouvrir `Ventes > Nouvelle facture`
2. verifier que le resume de la facture se met a jour quand les lignes changent
3. tester les raccourcis d echeance `+7 jours`, `+15 jours`, `+30 jours`
4. ouvrir `Achats > Nouvel achat` et verifier le meme comportement
5. ouvrir `Depenses > Nouvelle depense` et verifier le resume de paiement

## Scenario 1 - Vente simple

1. creer une facture client avec un produit stockable
2. verifier que le document est soit approuve, soit en attente selon le montant
3. si approuve : verifier le decrement du stock
4. verifier l ecriture comptable dans `Comptabilite > Journaux`
5. enregistrer un paiement si la facture est approuvee
6. verifier la mise a jour du solde

## Scenario 2 - Achat fournisseur

1. creer un achat fournisseur
2. verifier le workflow
3. apres approbation finale, verifier l augmentation du stock
4. verifier la dette fournisseur
5. enregistrer un reglement
6. verifier l ecriture de tresorerie

## Scenario 3 - Depense

1. creer une depense avec paiement immediat
2. verifier l etat du workflow
3. apres approbation, verifier l ecriture comptable
4. verifier le dashboard et les alertes

## Scenario 4 - Periode comptable

1. aller dans `Comptabilite > Periodes`
2. cloturer la periode du mois courant
3. tenter une vente datee sur cette periode
4. verifier le blocage
5. reouvrir la periode
6. verifier qu une nouvelle operation est possible

## Scenario 5 - Imports

1. ouvrir `Imports CSV`
2. importer un fichier `clients`
3. importer un fichier `produits`
4. importer un fichier `stock initial`
5. verifier les donnees dans les listes concernées

## Scenario 6 - Approvals multi-niveaux

1. creer une vente de plus de 100000 XOF avec `manager@nema-erp.test`
2. verifier qu elle attend la direction
3. ouvrir `Approbations` avec `dg@nema-erp.test`
4. valider l etape suivante
5. verifier le passage en `Approuvee`

## Validation finale

- dashboard coherent
- approbations visibles
- alertes visibles
- documents imprimables
- rapports accessibles
- aucune erreur 500 visible dans le navigateur




