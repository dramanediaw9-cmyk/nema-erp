# Usage Rapide - Nema ERP

## Comptes de demo

- admin@nema-erp.test / password
- dg@nema-erp.test / password
- manager@nema-erp.test / password
- ops@nema-erp.test / password

## Parcours quotidien

### 1. Verifier le dashboard

- ouvrir `Dashboard`
- lire les chiffres du mois
- verifier les alertes stock
- verifier les approbations en attente
- verifier la situation de la periode comptable

### 2. Traiter les approbations

- ouvrir `Approbations`
- filtrer par `Ventes`, `Achats` ou `Depenses`
- ouvrir le document
- valider l etape suivante

### 3. Saisir une vente

- aller dans `Ventes`
- cliquer `Nouvelle facture`
- choisir le client et les articles
- enregistrer
- si la facture passe tout de suite, le stock et la comptabilite sont mis a jour
- si elle part en validation, elle attend dans `Approbations`

### 4. Saisir un achat

- aller dans `Achats`
- cliquer `Nouvel achat`
- choisir le fournisseur et les articles
- enregistrer
- apres approbation finale, le stock augmente et la dette fournisseur est creee

### 5. Saisir une depense

- aller dans `Depenses`
- cliquer `Nouvelle depense`
- choisir la categorie, le montant et le compte de tresorerie si paiement immediat
- enregistrer
- la comptabilite n est impactee qu apres approbation finale

### 6. Encaisser ou regler

- ouvrir une facture client ou fournisseur
- utiliser le bouton de paiement si le document est approuve
- verifier ensuite `Paiements`

## Pages utiles

- `Dashboard` : pilotage global
- `Approbations` : boite de validation
- `Alertes internes` : alertes systeme et blocages
- `Notifications sortantes` : file email / WhatsApp des approbations
- `Rapports` : syntheses dirigeant
- `Comptabilite > Periodes` : cloture et reouverture
