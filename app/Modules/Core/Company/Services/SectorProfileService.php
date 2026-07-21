<?php

namespace App\Modules\Core\Company\Services;

use App\Modules\Core\Company\Models\Setting;

class SectorProfileService
{
    public const DEFAULT_PROFILE = 'general_trade';

    public function profiles(): array
    {
        $profiles = [
            'general_trade' => [
                'label' => 'Commerce general',
                'icon' => 'sell',
                'description' => 'Pour les commerces polyvalents, boutiques mixtes et entreprises qui vendent plusieurs familles d articles.',
                'vocabulary' => ['client' => 'Client', 'product' => 'Produit', 'sale' => 'Vente', 'stock' => 'Stock', 'cashier' => 'Caissier'],
                'recommended_modules' => ['ventes', 'achats', 'stock', 'caisse/POS', 'facturation', 'clients', 'fournisseurs', 'depenses', 'rapports', 'paiements', 'alertes', 'documents'],
                'specific_fields' => ['produits', 'categories', 'prix d achat', 'prix de vente', 'code-barres', 'stock minimum', 'inventaire'],
                'workflows' => ['Creer les produits', 'Ouvrir la caisse', 'Vendre et encaisser', 'Mettre a jour le stock', 'Controler les ventes et les marges'],
                'kpis' => ['chiffre d affaires', 'marge', 'stock faible', 'produits les plus vendus', 'dettes clients', 'depenses'],
                'alerts' => ['stock faible', 'facture impayee', 'caisse non cloturee', 'baisse des ventes'],
                'documents' => ['ticket de caisse', 'facture', 'devis', 'bon de livraison', 'rapport journalier'],
                'starter' => [
                    'categories' => ['Produits courants', 'Produits gros', 'Services'],
                    'units' => ['Unite', 'Pack', 'Carton'],
                    'payments' => ['Especes', 'Orange Money', 'Wave', 'Virement'],
                    'invoice_templates' => ['Facture standard', 'Ticket comptoir'],
                    'examples' => ['Article standard', 'Pack promotionnel', 'Service simple'],
                ],
            ],
            'restaurant_cafe' => [
                'label' => 'Restaurant',
                'icon' => 'pos',
                'description' => 'Pour restaurants, cafes, snacks, livraison de repas et points de restauration rapide.',
                'vocabulary' => ['client' => 'Client', 'product' => 'Menu / plat', 'sale' => 'Commande', 'stock' => 'Cuisine', 'cashier' => 'Serveur / caissier'],
                'recommended_modules' => ['caisse/POS', 'ventes', 'stock', 'achats', 'clients', 'fournisseurs', 'depenses', 'employes', 'rapports', 'paiements', 'alertes', 'documents'],
                'specific_fields' => ['tables', 'menus', 'plats', 'commandes cuisine', 'livraison', 'ingredients', 'serveur'],
                'workflows' => ['Creer menus et boissons', 'Prendre la commande', 'Envoyer en cuisine', 'Encaisser', 'Cloturer le service'],
                'kpis' => ['recette du service', 'top plats', 'marge par plat', 'depenses cuisine', 'frequentation', 'performance serveur'],
                'alerts' => ['ingredient faible', 'boisson en rupture', 'caisse non cloturee', 'baisse des ventes'],
                'documents' => ['ticket client', 'bon cuisine', 'rapport service', 'facture evenement'],
                'starter' => [
                    'categories' => ['Menus', 'Boissons', 'Ingredients', 'Livraison'],
                    'units' => ['Unite', 'Portion', 'Kg', 'Litre'],
                    'payments' => ['Especes', 'Orange Money', 'Wave'],
                    'invoice_templates' => ['Ticket restaurant', 'Facture groupe'],
                    'examples' => ['Riz sauce', 'Poulet grille', 'Boisson gazeuse', 'Menu complet'],
                ],
            ],
            'school_training' => [
                'label' => 'Ecole et formation',
                'icon' => 'team',
                'description' => 'Pour ecoles privees, centres de formation, cours du soir et instituts.',
                'vocabulary' => ['client' => 'Eleve / parent', 'product' => 'Frais scolaire', 'sale' => 'Facture scolaire', 'stock' => 'Supports', 'cashier' => 'Agent caisse'],
                'recommended_modules' => ['facturation', 'clients', 'paiements', 'depenses', 'employes', 'rapports', 'alertes', 'documents'],
                'specific_fields' => ['eleves', 'classes', 'inscriptions', 'frais scolaires', 'mensualites', 'parents', 'annee scolaire'],
                'workflows' => ['Inscrire un eleve', 'Creer les frais', 'Encaisser une mensualite', 'Suivre les impayes', 'Lire le rapport de periode'],
                'kpis' => ['inscriptions', 'frais encaisses', 'restes dus', 'depenses', 'frequentation', 'performance de recouvrement'],
                'alerts' => ['mensualite en retard', 'dette parent elevee', 'paiement non rapproche', 'baisse des inscriptions'],
                'documents' => ['recu de paiement', 'facture scolaire', 'etat des impayes', 'rapport de classe'],
                'starter' => [
                    'categories' => ['Inscription', 'Mensualite', 'Formation', 'Supports'],
                    'units' => ['Mois', 'Trimestre', 'Annee', 'Session'],
                    'payments' => ['Especes', 'Orange Money', 'Virement'],
                    'invoice_templates' => ['Facture scolaire', 'Recu simple'],
                    'examples' => ['Frais inscription', 'Mensualite primaire', 'Formation bureautique'],
                ],
            ],
            'auto_parts_garage' => [
                'label' => 'Garage',
                'icon' => 'settings',
                'description' => 'Pour garages, ateliers mecaniques, vendeurs de pieces auto et services de reparation.',
                'vocabulary' => ['client' => 'Client vehicule', 'product' => 'Piece / service', 'sale' => 'Reparation', 'stock' => 'Stock pieces', 'cashier' => 'Reception atelier'],
                'recommended_modules' => ['ventes', 'achats', 'stock', 'facturation', 'clients', 'fournisseurs', 'depenses', 'employes', 'rapports', 'paiements', 'alertes', 'documents'],
                'specific_fields' => ['vehicules', 'reparations', 'pieces', 'main-d oeuvre', 'diagnostic', 'kilometrage', 'technicien'],
                'workflows' => ['Enregistrer le vehicule', 'Creer un devis reparation', 'Sortir les pieces', 'Facturer main-d oeuvre', 'Encaisser et livrer'],
                'kpis' => ['reparations du jour', 'marge pieces', 'main-d oeuvre facturee', 'stock critique', 'dettes clients'],
                'alerts' => ['piece en rupture', 'devis non valide', 'facture impayee', 'reparation en retard'],
                'documents' => ['devis reparation', 'ordre de travail', 'facture garage', 'bon de sortie piece'],
                'starter' => [
                    'categories' => ['Pieces auto', 'Lubrifiants', 'Main-d oeuvre', 'Services atelier'],
                    'units' => ['Unite', 'Kit', 'Bidon', 'Heure'],
                    'payments' => ['Especes', 'Orange Money', 'Virement'],
                    'invoice_templates' => ['Facture garage', 'Devis reparation'],
                    'examples' => ['Vidange', 'Filtre huile', 'Main-d oeuvre mecanique'],
                ],
            ],
            'pharmacy_parapharmacy' => [
                'label' => 'Pharmacie',
                'icon' => 'alert',
                'description' => 'Pour pharmacies, parapharmacies et commerces de sante avec suivi strict du stock.',
                'vocabulary' => ['client' => 'Patient / client', 'product' => 'Produit trace', 'sale' => 'Vente comptoir', 'stock' => 'Lot', 'cashier' => 'Agent comptoir'],
                'recommended_modules' => ['ventes', 'achats', 'stock', 'caisse/POS', 'facturation', 'clients', 'fournisseurs', 'rapports', 'paiements', 'alertes', 'documents'],
                'specific_fields' => ['lots', 'dates d expiration', 'ordonnances', 'stock critique', 'familles sensibles', 'fournisseur agree'],
                'workflows' => ['Creer produits et lots', 'Vendre au comptoir', 'Controler expiration', 'Reapprovisionner', 'Suivre alertes sensibles'],
                'kpis' => ['ventes comptoir', 'lots proches expiration', 'stock critique', 'marge', 'produits les plus vendus'],
                'alerts' => ['produit expire', 'lot proche expiration', 'stock critique', 'facture fournisseur en attente'],
                'documents' => ['ticket comptoir', 'etat des lots', 'inventaire sensible', 'commande fournisseur'],
                'starter' => [
                    'categories' => ['Medicaments', 'Parapharmacie', 'Hygiene', 'Produits sensibles'],
                    'units' => ['Boite', 'Plaquette', 'Flacon', 'Unite'],
                    'payments' => ['Especes', 'Orange Money', 'Wave'],
                    'invoice_templates' => ['Ticket pharmacie', 'Facture comptoir'],
                    'examples' => ['Paracetamol', 'Sirop', 'Gel antiseptique'],
                ],
            ],
            'wholesale_distribution' => [
                'label' => 'Depot et stock',
                'icon' => 'stock',
                'description' => 'Pour depots, magasins de stockage, distribution locale et suivi multi-entrepots.',
                'vocabulary' => ['client' => 'Client / agence', 'product' => 'Article stock', 'sale' => 'Sortie stock', 'stock' => 'Depot', 'cashier' => 'Magasinier'],
                'recommended_modules' => ['stock', 'achats', 'ventes', 'fournisseurs', 'clients', 'documents', 'rapports', 'alertes'],
                'specific_fields' => ['entrepots', 'emplacements', 'transferts', 'inventaires', 'quantites minimum', 'mouvements stock'],
                'workflows' => ['Receptionner', 'Ranger en depot', 'Transferer', 'Faire inventaire', 'Analyser les ecarts'],
                'kpis' => ['stock disponible', 'ruptures', 'mouvements', 'ecarts inventaire', 'articles dormants'],
                'alerts' => ['absence de mouvement', 'stock faible', 'ecart inventaire', 'transfert non valide'],
                'documents' => ['bon de reception', 'bon de transfert', 'fiche inventaire', 'rapport de stock'],
                'starter' => [
                    'categories' => ['Articles stockables', 'Conditionnements', 'Services logistiques'],
                    'units' => ['Unite', 'Carton', 'Sac', 'Palette'],
                    'payments' => ['Especes', 'Virement'],
                    'invoice_templates' => ['Bon depot', 'Bon transfert'],
                    'examples' => ['Carton produit', 'Sac marchandise', 'Frais livraison'],
                ],
            ],
            'beauty_salon' => [
                'label' => 'Salon de coiffure',
                'icon' => 'customer',
                'description' => 'Pour salons de coiffure, beaute, soins et petits services avec produits associes.',
                'vocabulary' => ['client' => 'Client salon', 'product' => 'Service / produit', 'sale' => 'Prestation', 'stock' => 'Produits salon', 'cashier' => 'Reception'],
                'recommended_modules' => ['caisse/POS', 'ventes', 'clients', 'stock', 'depenses', 'employes', 'rapports', 'paiements'],
                'specific_fields' => ['prestations', 'coiffeurs', 'rendez-vous', 'produits utilises', 'forfaits', 'clients fideles'],
                'workflows' => ['Creer les prestations', 'Servir le client', 'Ajouter les produits', 'Encaisser', 'Suivre la performance employe'],
                'kpis' => ['recette jour', 'services les plus demandes', 'produits vendus', 'performance employe', 'clients fideles'],
                'alerts' => ['produit salon faible', 'depense anormale', 'baisse de frequentation', 'paiement en attente'],
                'documents' => ['ticket salon', 'recu paiement', 'rapport employe', 'rapport journalier'],
                'starter' => [
                    'categories' => ['Coiffure', 'Soins', 'Produits salon', 'Forfaits'],
                    'units' => ['Service', 'Unite', 'Forfait'],
                    'payments' => ['Especes', 'Orange Money', 'Wave'],
                    'invoice_templates' => ['Ticket salon', 'Recu prestation'],
                    'examples' => ['Coupe homme', 'Tresse', 'Soin cheveux', 'Shampoing'],
                ],
            ],
            'workshop_manufacturing' => [
                'label' => 'Atelier',
                'icon' => 'settings',
                'description' => 'Pour ateliers de fabrication, couture, menuiserie, reparation ou production artisanale.',
                'vocabulary' => ['client' => 'Client commande', 'product' => 'Produit / travail', 'sale' => 'Commande atelier', 'stock' => 'Matieres', 'cashier' => 'Responsable atelier'],
                'recommended_modules' => ['ventes', 'achats', 'stock', 'facturation', 'clients', 'fournisseurs', 'depenses', 'employes', 'rapports', 'documents'],
                'specific_fields' => ['commandes atelier', 'matieres premieres', 'main-d oeuvre', 'delai livraison', 'etat travaux', 'mesures'],
                'workflows' => ['Prendre commande', 'Evaluer matieres et main-d oeuvre', 'Produire', 'Livrer', 'Facturer'],
                'kpis' => ['commandes en cours', 'cout matiere', 'marge travail', 'retards livraison', 'performance atelier'],
                'alerts' => ['commande en retard', 'matiere faible', 'depense anormale', 'facture impayee'],
                'documents' => ['devis atelier', 'ordre de travail', 'facture', 'bon de livraison'],
                'starter' => [
                    'categories' => ['Travaux atelier', 'Matieres premieres', 'Main-d oeuvre', 'Reparations'],
                    'units' => ['Unite', 'Metre', 'Heure', 'Forfait'],
                    'payments' => ['Especes', 'Orange Money', 'Virement'],
                    'invoice_templates' => ['Devis atelier', 'Facture travail'],
                    'examples' => ['Reparation', 'Fabrication sur commande', 'Main-d oeuvre'],
                ],
            ],
            'services_agency' => [
                'label' => 'Prestation de service',
                'icon' => 'document',
                'description' => 'Pour agences, consultants, maintenance, prestations professionnelles et services aux entreprises.',
                'vocabulary' => ['client' => 'Client / compte', 'product' => 'Prestation', 'sale' => 'Mission', 'stock' => 'Ressource', 'cashier' => 'Agent facturation'],
                'recommended_modules' => ['ventes', 'facturation', 'clients', 'depenses', 'employes', 'rapports', 'paiements', 'documents'],
                'specific_fields' => ['prestations', 'missions', 'contrats', 'devis', 'forfaits', 'heures', 'livrables'],
                'workflows' => ['Creer le devis', 'Valider la mission', 'Suivre la prestation', 'Facturer', 'Encaisser'],
                'kpis' => ['devis gagnes', 'factures impayees', 'marge prestation', 'CA par client', 'depenses'],
                'alerts' => ['devis sans reponse', 'facture impayee', 'retard de paiement', 'mission non facturee'],
                'documents' => ['devis', 'facture prestation', 'recu paiement', 'rapport client'],
                'starter' => [
                    'categories' => ['Prestations', 'Forfaits', 'Frais refacturables'],
                    'units' => ['Forfait', 'Heure', 'Jour', 'Mission'],
                    'payments' => ['Especes', 'Virement', 'Orange Money'],
                    'invoice_templates' => ['Facture prestation', 'Devis service'],
                    'examples' => ['Maintenance', 'Conseil', 'Installation', 'Abonnement'],
                ],
            ],
            'agriculture_livestock' => [
                'label' => 'Elevage et aviculture',
                'icon' => 'stock',
                'description' => 'Pour elevage, aviculture, alimentation betail, intrants et vente de production.',
                'vocabulary' => ['client' => 'Acheteur', 'product' => 'Betail / produit', 'sale' => 'Vente production', 'stock' => 'Stock ferme', 'cashier' => 'Responsable ferme'],
                'recommended_modules' => ['ventes', 'achats', 'stock', 'depenses', 'clients', 'fournisseurs', 'rapports', 'paiements', 'alertes'],
                'specific_fields' => ['lots animaux', 'aliments', 'vaccins', 'mortalite', 'production', 'cycles', 'charges ferme'],
                'workflows' => ['Entrer les achats ferme', 'Suivre les lots', 'Enregistrer ventes', 'Controler depenses', 'Lire marge cycle'],
                'kpis' => ['production', 'mortalite', 'cout alimentation', 'stock aliments', 'marge cycle'],
                'alerts' => ['aliment faible', 'depense anormale', 'baisse production', 'absence mouvement stock'],
                'documents' => ['facture vente', 'bon sortie aliment', 'rapport cycle', 'etat depenses'],
                'starter' => [
                    'categories' => ['Animaux', 'Aliments', 'Soins', 'Production'],
                    'units' => ['Tete', 'Kg', 'Sac', 'Plateau'],
                    'payments' => ['Especes', 'Orange Money', 'Virement'],
                    'invoice_templates' => ['Facture ferme', 'Recu vente'],
                    'examples' => ['Poulet chair', 'Aliment volaille', 'Oeufs', 'Vaccin'],
                ],
            ],
            'import_export' => [
                'label' => 'Import-export',
                'icon' => 'truck',
                'description' => 'Pour importateurs, exportateurs, transit, lots internationaux et commerce multi-fournisseurs.',
                'vocabulary' => ['client' => 'Client import/export', 'product' => 'Lot marchandise', 'sale' => 'Commande internationale', 'stock' => 'Transit / depot', 'cashier' => 'Agent operations'],
                'recommended_modules' => ['achats', 'ventes', 'stock', 'fournisseurs', 'clients', 'facturation', 'depenses', 'documents', 'rapports', 'paiements', 'alertes'],
                'specific_fields' => ['fournisseur pays', 'lots', 'transit', 'frais douane', 'transport', 'devise', 'marge par lot'],
                'workflows' => ['Creer achat import', 'Ajouter frais', 'Receptionner stock', 'Vendre lot', 'Analyser marge lot'],
                'kpis' => ['marge par lot', 'frais import', 'stock en transit', 'dettes fournisseurs', 'paiements en attente'],
                'alerts' => ['lot en retard', 'frais anormal', 'paiement en attente', 'stock sans mouvement'],
                'documents' => ['facture import', 'bon reception', 'facture client', 'etat frais lot'],
                'starter' => [
                    'categories' => ['Lots import', 'Frais transit', 'Transport', 'Services douane'],
                    'units' => ['Carton', 'Palette', 'Conteneur', 'Kg'],
                    'payments' => ['Virement', 'Especes', 'Orange Money'],
                    'invoice_templates' => ['Facture import-export', 'Etat lot'],
                    'examples' => ['Lot marchandise', 'Frais transit', 'Transport port'],
                ],
            ],
            'fashion_boutique' => [
                'label' => 'Boutique de vetements',
                'icon' => 'sell',
                'description' => 'Pour pret-a-porter, chaussures, accessoires, tailles, couleurs et collections.',
                'vocabulary' => ['client' => 'Client boutique', 'product' => 'Modele / variante', 'sale' => 'Vente boutique', 'stock' => 'Stock taille/couleur', 'cashier' => 'Vendeur'],
                'recommended_modules' => ['caisse/POS', 'ventes', 'stock', 'achats', 'clients', 'fournisseurs', 'rapports', 'paiements', 'alertes'],
                'specific_fields' => ['tailles', 'couleurs', 'collections', 'modeles', 'prix promo', 'clients VIP'],
                'workflows' => ['Creer modeles et variantes', 'Vendre en caisse', 'Suivre tailles restantes', 'Faire inventaire', 'Analyser invendus'],
                'kpis' => ['top modeles', 'stock tailles', 'invendus', 'ventes promo', 'marge'],
                'alerts' => ['taille en rupture', 'article dormant', 'baisse ventes', 'stock faible'],
                'documents' => ['ticket boutique', 'facture client', 'etat stock tailles', 'rapport promo'],
                'starter' => [
                    'categories' => ['Vetements', 'Chaussures', 'Accessoires', 'Promotions'],
                    'units' => ['Unite', 'Lot', 'Carton'],
                    'payments' => ['Especes', 'Orange Money', 'Wave'],
                    'invoice_templates' => ['Ticket boutique', 'Facture client'],
                    'examples' => ['Chemise', 'Chaussure', 'Sac', 'Tenue complete'],
                ],
            ],
            'hardware_store' => [
                'label' => 'Quincaillerie',
                'icon' => 'settings',
                'description' => 'Pour quincailleries, materiaux, outillage, plomberie, electricite et negoce technique.',
                'vocabulary' => ['client' => 'Client / artisan', 'product' => 'Reference technique', 'sale' => 'Vente / devis', 'stock' => 'Depot technique', 'cashier' => 'Vendeur'],
                'recommended_modules' => ['ventes', 'achats', 'stock', 'caisse/POS', 'facturation', 'clients', 'fournisseurs', 'rapports', 'paiements', 'alertes', 'documents'],
                'specific_fields' => ['references', 'dimensions', 'conditionnements', 'fournisseurs', 'tarif chantier', 'stock depot'],
                'workflows' => ['Chercher reference', 'Faire devis', 'Vendre ou reserver', 'Reapprovisionner', 'Controler stock depot'],
                'kpis' => ['devis convertis', 'references critiques', 'stock dormant', 'marge famille', 'achats urgents'],
                'alerts' => ['reference manquante', 'stock dormant', 'devis en attente', 'commande fournisseur en retard'],
                'documents' => ['devis technique', 'facture', 'bon livraison', 'inventaire depot'],
                'starter' => [
                    'categories' => ['Outillage', 'Electricite', 'Plomberie', 'Fixations'],
                    'units' => ['Unite', 'Boite', 'Rouleau', 'Carton'],
                    'payments' => ['Especes', 'Orange Money', 'Virement'],
                    'invoice_templates' => ['Facture quincaillerie', 'Devis chantier'],
                    'examples' => ['Cable', 'Robinet', 'Visserie', 'Marteau'],
                ],
            ],
            'food_store' => [
                'label' => 'Supermarche',
                'icon' => 'pos',
                'description' => 'Pour supermarches, alimentations, superettes et commerces a forte rotation.',
                'vocabulary' => ['client' => 'Client comptoir', 'product' => 'Produit rayon', 'sale' => 'Ticket caisse', 'stock' => 'Rayon / depot', 'cashier' => 'Caissier'],
                'recommended_modules' => ['caisse/POS', 'ventes', 'stock', 'achats', 'facturation', 'clients', 'fournisseurs', 'depenses', 'employes', 'rapports', 'paiements', 'alertes', 'documents'],
                'specific_fields' => ['produits', 'categories', 'codes-barres', 'rayons', 'ruptures', 'inventaires', 'dates d expiration'],
                'workflows' => ['Importer produits', 'Ouvrir caisse', 'Scanner et vendre', 'Reapprovisionner rayon', 'Cloturer caisse'],
                'kpis' => ['ventes du jour', 'top produits', 'ruptures', 'marge', 'ecart caisse', 'performance caissier'],
                'alerts' => ['stock faible', 'produit expire', 'caisse non cloturee', 'absence mouvement stock'],
                'documents' => ['ticket thermique', 'facture comptoir', 'cloture caisse', 'inventaire rapide'],
                'starter' => [
                    'categories' => ['Boissons', 'Epicerie', 'Produits frais', 'Hygiene'],
                    'units' => ['Unite', 'Pack', 'Carton', 'Kg'],
                    'payments' => ['Especes', 'Orange Money', 'Wave'],
                    'invoice_templates' => ['Ticket caisse', 'Facture comptoir'],
                    'examples' => ['Riz', 'Huile', 'Boisson', 'Savon'],
                ],
            ],
            'delivery_company' => [
                'label' => 'Entreprise de livraison',
                'icon' => 'truck',
                'description' => 'Pour livraison urbaine, coursiers, transport local, colis et suivi des encaissements.',
                'vocabulary' => ['client' => 'Expediteur / destinataire', 'product' => 'Course / colis', 'sale' => 'Livraison', 'stock' => 'Colis', 'cashier' => 'Agent livraison'],
                'recommended_modules' => ['ventes', 'facturation', 'clients', 'depenses', 'employes', 'rapports', 'paiements', 'alertes', 'documents'],
                'specific_fields' => ['courses', 'colis', 'adresse depart', 'adresse arrivee', 'livreur', 'statut livraison', 'frais'],
                'workflows' => ['Creer course', 'Affecter livreur', 'Suivre livraison', 'Encaisser', 'Lire rapport livreur'],
                'kpis' => ['livraisons du jour', 'courses en retard', 'recette livreur', 'depenses carburant', 'paiements en attente'],
                'alerts' => ['livraison en retard', 'paiement non confirme', 'depense carburant anormale', 'baisse activite'],
                'documents' => ['bon livraison', 'recu livraison', 'rapport livreur', 'facture client'],
                'starter' => [
                    'categories' => ['Livraison locale', 'Course express', 'Transport colis', 'Frais supplementaires'],
                    'units' => ['Course', 'Colis', 'Km', 'Forfait'],
                    'payments' => ['Especes', 'Orange Money', 'Wave', 'Virement'],
                    'invoice_templates' => ['Recu livraison', 'Facture transport'],
                    'examples' => ['Course Bamako', 'Livraison express', 'Frais attente'],
                ],
            ],
        ];

        foreach ($profiles as $key => $profile) {
            $profile['key'] = $key;
            $profiles[$key] = $this->completeProfile($profile);
        }

        return $profiles;
    }

    public function keys(): array
    {
        return array_keys($this->profiles());
    }

    public function defaultProfile(): array
    {
        return $this->profiles()[self::DEFAULT_PROFILE];
    }

    public function isExplicitlyConfigured(?int $companyId): bool
    {
        if (! $companyId) {
            return false;
        }

        return Setting::query()
            ->where('company_id', $companyId)
            ->where('key', 'sector_profile')
            ->exists();
    }

    public function profileForCompany(?int $companyId): array
    {
        if (! $companyId) {
            return $this->defaultProfile();
        }

        $setting = Setting::query()
            ->where('company_id', $companyId)
            ->where('key', 'sector_profile')
            ->first();

        $profileKey = $this->canonicalKey((string) ($setting?->value['profile'] ?? self::DEFAULT_PROFILE));

        return $this->profiles()[$profileKey] ?? $this->defaultProfile();
    }

    public function canonicalKey(?string $profileKey): string
    {
        return match ((string) $profileKey) {
            'cosmetics_beauty' => 'beauty_salon',
            default => (string) ($profileKey ?: self::DEFAULT_PROFILE),
        };
    }

    public function businessVocabularyForCompany(?int $companyId): array
    {
        return $this->businessVocabularyForProfile($this->profileForCompany($companyId));
    }

    public function businessVocabularyForProfile(array $profile): array
    {
        $vocabulary = $profile['vocabulary'] ?? [];
        $profileKey = $profile['key'] ?? self::DEFAULT_PROFILE;
        $client = $vocabulary['client'] ?? 'Client';
        $product = $vocabulary['product'] ?? 'Produit';
        $sale = $vocabulary['sale'] ?? 'Vente';
        $stock = $vocabulary['stock'] ?? 'Stock';
        $cashier = $vocabulary['cashier'] ?? 'Caissier';

        return array_merge([
            'profile_key' => $profileKey,
            'profile_label' => $profile['label'] ?? 'Commerce general',
            'client' => $client,
            'clients' => $this->pluralBusinessLabel($profileKey, 'client', $client),
            'product' => $product,
            'products' => $this->pluralBusinessLabel($profileKey, 'product', $product),
            'sale' => $sale,
            'sales' => $this->pluralBusinessLabel($profileKey, 'sale', $sale),
            'stock' => $stock,
            'cashier' => $cashier,
            'cashiers' => $this->pluralBusinessLabel($profileKey, 'cashier', $cashier),
            'supplier' => $this->singularBusinessLabel($profileKey, 'supplier', 'Fournisseur'),
            'suppliers' => $this->pluralBusinessLabel($profileKey, 'supplier', 'Fournisseur'),
            'purchase' => $this->singularBusinessLabel($profileKey, 'purchase', 'Achat'),
            'purchases' => $this->pluralBusinessLabel($profileKey, 'purchase', 'Achat'),
            'replenishment' => $this->singularBusinessLabel($profileKey, 'replenishment', 'Reapprovisionnement'),
            'replenishments' => $this->pluralBusinessLabel($profileKey, 'replenishment', 'Reapprovisionnement'),
        ], $vocabulary);
    }

    public function updateProfile(int $companyId, ?int $tenantId, string $profileKey): void
    {
        $profileKey = $this->canonicalKey($profileKey);

        Setting::query()->updateOrCreate(
            ['company_id' => $companyId, 'key' => 'sector_profile'],
            [
                'tenant_id' => $tenantId,
                'value' => ['profile' => $profileKey],
            ]
        );
    }

    private function singularBusinessLabel(string $profileKey, string $wordKey, string $fallback): string
    {
        $labels = [
            'restaurant_cafe' => [
                'supplier' => 'Fournisseur cuisine',
                'purchase' => 'Achat cuisine',
                'replenishment' => 'Reappro cuisine',
            ],
            'school_training' => [
                'supplier' => 'Partenaire / fournisseur',
                'purchase' => 'Depense ecole',
                'replenishment' => 'Besoin ecole',
            ],
            'auto_parts_garage' => [
                'supplier' => 'Fournisseur pieces',
                'purchase' => 'Achat piece',
                'replenishment' => 'Reappro piece',
            ],
            'pharmacy_parapharmacy' => [
                'supplier' => 'Fournisseur agree',
                'purchase' => 'Achat pharmacie',
                'replenishment' => 'Reappro pharmacie',
            ],
            'wholesale_distribution' => [
                'supplier' => 'Fournisseur depot',
                'purchase' => 'Achat depot',
                'replenishment' => 'Reappro depot',
            ],
            'beauty_salon' => [
                'supplier' => 'Fournisseur salon',
                'purchase' => 'Achat salon',
                'replenishment' => 'Reappro salon',
            ],
            'workshop_manufacturing' => [
                'supplier' => 'Fournisseur matieres',
                'purchase' => 'Achat matiere',
                'replenishment' => 'Reappro atelier',
            ],
            'services_agency' => [
                'supplier' => 'Prestataire / fournisseur',
                'purchase' => 'Achat prestation',
                'replenishment' => 'Besoin mission',
            ],
            'agriculture_livestock' => [
                'supplier' => 'Fournisseur ferme',
                'purchase' => 'Achat ferme',
                'replenishment' => 'Reappro ferme',
            ],
            'import_export' => [
                'supplier' => 'Fournisseur import',
                'purchase' => 'Achat import',
                'replenishment' => 'Reappro import',
            ],
            'fashion_boutique' => [
                'supplier' => 'Fournisseur mode',
                'purchase' => 'Achat collection',
                'replenishment' => 'Reappro boutique',
            ],
            'hardware_store' => [
                'supplier' => 'Fournisseur technique',
                'purchase' => 'Achat technique',
                'replenishment' => 'Reappro quincaillerie',
            ],
            'food_store' => [
                'supplier' => 'Fournisseur rayon',
                'purchase' => 'Achat boutique',
                'replenishment' => 'Reappro rayon',
            ],
            'delivery_company' => [
                'supplier' => 'Prestataire livraison',
                'purchase' => 'Depense livraison',
                'replenishment' => 'Besoin livraison',
            ],
        ];

        return $labels[$profileKey][$wordKey] ?? $fallback;
    }

    private function pluralBusinessLabel(string $profileKey, string $wordKey, string $fallback): string
    {
        $labels = [
            'restaurant_cafe' => [
                'client' => 'Clients restaurant',
                'product' => 'Menus / plats',
                'sale' => 'Commandes',
                'cashier' => 'Serveurs / caissiers',
                'supplier' => 'Fournisseurs cuisine',
                'purchase' => 'Achats cuisine',
                'replenishment' => 'Reappro cuisine',
            ],
            'school_training' => [
                'client' => 'Eleves / parents',
                'product' => 'Frais scolaires',
                'sale' => 'Factures scolaires',
                'cashier' => 'Agents caisse',
                'supplier' => 'Partenaires / fournisseurs',
                'purchase' => 'Depenses ecole',
                'replenishment' => 'Besoins ecole',
            ],
            'auto_parts_garage' => [
                'client' => 'Clients vehicules',
                'product' => 'Pieces / services',
                'sale' => 'Reparations',
                'cashier' => 'Reception atelier',
                'supplier' => 'Fournisseurs pieces',
                'purchase' => 'Achats pieces',
                'replenishment' => 'Reappro pieces',
            ],
            'pharmacy_parapharmacy' => [
                'client' => 'Patients / clients',
                'product' => 'Produits sante',
                'sale' => 'Ventes comptoir',
                'cashier' => 'Agents comptoir',
                'supplier' => 'Fournisseurs agrees',
                'purchase' => 'Achats pharmacie',
                'replenishment' => 'Reappro pharmacie',
            ],
            'wholesale_distribution' => [
                'client' => 'Clients / agences',
                'product' => 'Articles stock',
                'sale' => 'Sorties stock',
                'cashier' => 'Magasiniers',
                'supplier' => 'Fournisseurs depot',
                'purchase' => 'Achats depot',
                'replenishment' => 'Reappro depot',
            ],
            'beauty_salon' => [
                'client' => 'Clients salon',
                'product' => 'Services / produits',
                'sale' => 'Prestations',
                'cashier' => 'Reception salon',
                'supplier' => 'Fournisseurs salon',
                'purchase' => 'Achats salon',
                'replenishment' => 'Reappro salon',
            ],
            'workshop_manufacturing' => [
                'client' => 'Clients commande',
                'product' => 'Produits / travaux',
                'sale' => 'Commandes atelier',
                'cashier' => 'Responsables atelier',
                'supplier' => 'Fournisseurs matieres',
                'purchase' => 'Achats matieres',
                'replenishment' => 'Reappro atelier',
            ],
            'services_agency' => [
                'client' => 'Clients / comptes',
                'product' => 'Prestations',
                'sale' => 'Missions',
                'cashier' => 'Agents facturation',
                'supplier' => 'Prestataires / fournisseurs',
                'purchase' => 'Achats prestation',
                'replenishment' => 'Besoins mission',
            ],
            'agriculture_livestock' => [
                'client' => 'Acheteurs',
                'product' => 'Betail / produits',
                'sale' => 'Ventes production',
                'cashier' => 'Responsables ferme',
                'supplier' => 'Fournisseurs ferme',
                'purchase' => 'Achats ferme',
                'replenishment' => 'Reappro ferme',
            ],
            'import_export' => [
                'client' => 'Clients import/export',
                'product' => 'Lots marchandise',
                'sale' => 'Commandes internationales',
                'cashier' => 'Agents operations',
                'supplier' => 'Fournisseurs import',
                'purchase' => 'Achats import',
                'replenishment' => 'Reappro import',
            ],
            'fashion_boutique' => [
                'client' => 'Clients boutique',
                'product' => 'Modeles / variantes',
                'sale' => 'Ventes boutique',
                'cashier' => 'Vendeurs',
                'supplier' => 'Fournisseurs mode',
                'purchase' => 'Achats collection',
                'replenishment' => 'Reappro boutique',
            ],
            'hardware_store' => [
                'client' => 'Clients / artisans',
                'product' => 'References techniques',
                'sale' => 'Ventes / devis',
                'cashier' => 'Vendeurs',
                'supplier' => 'Fournisseurs techniques',
                'purchase' => 'Achats techniques',
                'replenishment' => 'Reappro quincaillerie',
            ],
            'food_store' => [
                'client' => 'Clients comptoir',
                'product' => 'Produits rayon',
                'sale' => 'Tickets caisse',
                'cashier' => 'Caissiers',
                'supplier' => 'Fournisseurs rayon',
                'purchase' => 'Achats boutique',
                'replenishment' => 'Reappro rayon',
            ],
            'delivery_company' => [
                'client' => 'Expediteurs / destinataires',
                'product' => 'Courses / colis',
                'sale' => 'Livraisons',
                'cashier' => 'Agents livraison',
                'supplier' => 'Prestataires livraison',
                'purchase' => 'Depenses livraison',
                'replenishment' => 'Besoins livraison',
            ],
        ];

        return $labels[$profileKey][$wordKey] ?? $fallback.'s';
    }

    private function completeProfile(array $profile): array
    {
        $profile['key'] ??= '';

        return array_replace_recursive([
            'badge' => 'Metier',
            'recommended_modules' => [],
            'specific_fields' => [],
            'workflows' => [],
            'kpis' => [],
            'alerts' => [],
            'documents' => [],
            'starter' => [
                'categories' => [],
                'units' => [],
                'payments' => [],
                'invoice_templates' => [],
                'examples' => [],
                'essential_settings' => ['Societe', 'Agence', 'Caisse', 'Droits utilisateurs'],
            ],
            'guide' => [
                'configure_first' => [],
                'daily_work' => [],
                'watch' => [],
                'avoid' => [],
                'useful_reports' => [],
                'advice' => [],
            ],
            'recommended_modules_full' => $this->moduleCards($profile['recommended_modules'] ?? []),
        ], $profile, [
            'guide' => $this->guideFor($profile),
            'recommended_modules_full' => $this->moduleCards($profile['recommended_modules'] ?? []),
        ]);
    }

    private function guideFor(array $profile): array
    {
        return [
            'configure_first' => [
                'Verifier les informations de la societe et les documents imprimes.',
                'Creer les utilisateurs avec leurs droits.',
                'Preparer les categories, produits ou services de depart.',
                'Configurer les moyens de paiement utilises au Mali.',
            ],
            'daily_work' => $profile['workflows'] ?? [],
            'watch' => $profile['kpis'] ?? [],
            'avoid' => [
                'Vendre sans stock ou sans prix correct.',
                'Laisser une caisse ouverte plusieurs jours.',
                'Melanger les depenses personnelles et celles de l entreprise.',
                'Attendre la fin du mois pour verifier les impayes.',
            ],
            'useful_reports' => ['Ventes du jour', 'Stock faible', 'Marge', 'Paiements en attente', 'Depenses'],
            'advice' => [
                'Commencer simple : produits, clients, caisse et stock.',
                'Former chaque utilisateur uniquement sur les ecrans dont il a besoin.',
                'Lire le rapport journalier avant de fermer la journee.',
            ],
        ];
    }

    private function moduleCards(array $moduleNames): array
    {
        $catalog = [
            'ventes' => ['permission' => 'sales.view', 'label' => 'Ventes', 'route_name' => 'sales.index', 'description' => 'Suivre les ventes, factures et restes dus.'],
            'achats' => ['permission' => 'purchases.view', 'label' => 'Achats', 'route_name' => 'purchases.index', 'description' => 'Commander, receptionner et controler les fournisseurs.'],
            'stock' => ['permission' => 'stock.view', 'label' => 'Stock', 'route_name' => 'stock.index', 'description' => 'Voir les quantites, ruptures, mouvements et inventaires.'],
            'caisse/POS' => ['permission' => 'pos.view', 'label' => 'Caisse / POS', 'route_name' => 'pos.index', 'description' => 'Encaisser rapidement et imprimer les tickets.'],
            'facturation' => ['permission' => 'sales.view', 'label' => 'Facturation', 'route_name' => 'sales.index', 'description' => 'Creer factures, recus et documents clients.'],
            'clients' => ['permission' => 'customers.view', 'label' => 'Clients', 'route_name' => 'customers.index', 'description' => 'Centraliser les clients, dettes et historiques.'],
            'fournisseurs' => ['permission' => 'suppliers.view', 'label' => 'Fournisseurs', 'route_name' => 'suppliers.index', 'description' => 'Gerer les partenaires et achats.'],
            'depenses' => ['permission' => 'expenses.view', 'label' => 'Depenses', 'route_name' => 'expenses.index', 'description' => 'Suivre les charges et sorties d argent.'],
            'employes' => ['permission' => 'hr.view', 'label' => 'Employes', 'route_name' => 'hr.index', 'description' => 'Suivre equipe, roles et performance.'],
            'rapports' => ['permission' => 'reports.view', 'label' => 'Rapports', 'route_name' => 'reports.index', 'description' => 'Lire les chiffres utiles pour decider.'],
            'paiements' => ['permission' => 'payments.view', 'label' => 'Paiements', 'route_name' => 'payments.index', 'description' => 'Controler encaissements et decaissements.'],
            'alertes' => ['permission' => 'notifications.view', 'label' => 'Alertes', 'route_name' => 'notifications.index', 'description' => 'Traiter les risques et rappels importants.'],
            'documents' => ['permission' => 'dashboard.view', 'label' => 'Documents', 'route_name' => 'search.index', 'description' => 'Retrouver les documents importants.'],
        ];

        return collect($moduleNames)
            ->map(fn (string $module): ?array => $catalog[$module] ?? null)
            ->filter()
            ->values()
            ->all();
    }
}
