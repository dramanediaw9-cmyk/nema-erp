<?php

namespace App\Modules\Core\Entry\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class PublicEntryController extends Controller
{
    public function __invoke(): View
    {
        return view('entry.index', [
            'foundations' => [
                [
                    'number' => '01',
                    'title' => 'Compte administrateur',
                    'text' => 'Créer l accès sécurisé du responsable principal de l entreprise.',
                    'collects' => ['Nom du responsable', 'Email de connexion', 'Mot de passe sécurisé'],
                    'prepares' => ['Profil propriétaire', 'Accès administrateur', 'Journal de première connexion'],
                    'check' => 'Le responsable peut gérer les utilisateurs, les rôles et les réglages.',
                    'result' => 'Un propriétaire clairement identifié.',
                ],
                [
                    'number' => '02',
                    'title' => 'Entreprise',
                    'text' => 'Définir l identité, les coordonnées et la devise de travail.',
                    'collects' => ['Nom légal', 'Téléphone et adresse', 'Devise, NIF/RCCM si disponible'],
                    'prepares' => ['Espace séparé', 'Paramètres société', 'Base prête pour les documents'],
                    'check' => 'Les factures, tickets et rapports peuvent afficher l’identité de la société.',
                    'result' => 'Une société séparée et prête à être configurée.',
                ],
                [
                    'number' => '03',
                    'title' => 'Espace métier',
                    'text' => 'Choisir le secteur et préparer l agence principale.',
                    'collects' => ['Metier / secteur d’activité', 'Agence principale', 'Dépôt et caisse de départ'],
                    'prepares' => ['Modules adaptés', 'Stock initial possible', 'Caisse POS prête à ouvrir'],
                    'check' => 'L’équipe arrive directement dans un environnement orienté métier.',
                    'result' => 'Un démarrage adapté à l activité réelle.',
                ],
                [
                    'number' => '04',
                    'title' => 'Formule',
                    'text' => 'Choisir la capacité en utilisateurs et en agences.',
                    'collects' => ['Nombre d’utilisateurs', 'Nombre d’agences', 'Niveau de capacité'],
                    'prepares' => ['Essai actif', 'Limites connues', 'Évolution possible vers plus de capacité'],
                    'check' => 'L’entreprise démarre sans paiement immédiat, avec une base exploitable.',
                    'result' => 'Un essai de 14 jours sans paiement immédiat.',
                ],
            ],
            'industries' => [
                ['name' => 'Alimentation & boutique', 'detail' => 'Caisse rapide, stock, ruptures, fournisseurs et rapports du jour.'],
                ['name' => 'Restaurant & snack', 'detail' => 'Menus, boissons, encaissement rapide, achats cuisine et rapport journalier.'],
                ['name' => 'Commerce général', 'detail' => 'Ventes, achats, factures, encaissements et suivi multi-agence.'],
                ['name' => 'Distribution & grossiste', 'detail' => 'Dépôts, transferts, réapprovisionnement, tarifs gros et contrôle des marges.'],
                ['name' => 'Services & agences', 'detail' => 'Clients, devis, prestations, facturation, paiements et projets.'],
                ['name' => 'BTP & chantier', 'detail' => 'Devis chantier, achats, dépenses terrain, stock matériaux et suivi projet.'],
                ['name' => 'Mode, beauté, électronique', 'detail' => 'Variantes, références, caisse boutique, top ventes et stock sensible.'],
                ['name' => 'École, hôtel, garage, agriculture', 'detail' => 'Packs métier adaptables pour facturation, stock, dépenses et rapports.'],
            ],
            'modules' => [
                ['name' => 'Caisse POS', 'status' => 'Prêt', 'detail' => 'Tickets, sessions, paiements, clôture et rapport caisse.'],
                ['name' => 'Stock', 'status' => 'Prêt', 'detail' => 'Inventaires, mouvements, lots, ruptures et transferts.'],
                ['name' => 'Achats', 'status' => 'Renforcé', 'detail' => 'Fournisseurs, réappro, commandes, réceptions et factures.'],
                ['name' => 'Clients & ventes', 'status' => 'Prêt', 'detail' => 'Devis, factures, paiements, avoirs et historiques.'],
                ['name' => 'Paramètres société', 'status' => 'Prêt', 'detail' => 'Utilisateurs, rôles, agences, caisses, taxes et documents.'],
                ['name' => 'Rapports', 'status' => 'Prêt', 'detail' => 'Ventes du jour, marge, ruptures, caisse par caissier et audit.'],
            ],
            'assurances' => [
                ['title' => 'Données séparées', 'text' => 'Chaque entreprise garde ses utilisateurs, ses stocks, ses documents et ses réglages.'],
                ['title' => 'Documents imprimables', 'text' => 'Tickets, factures, achats, réceptions, inventaires et clôtures de caisse.'],
                ['title' => 'Droits contrôlés', 'text' => 'Caissier dans la caisse, manager dans les rapports, administrateur dans les réglages.'],
                ['title' => 'Sauvegarde suivie', 'text' => 'Contrôle quotidien de la base et des fichiers importants pour réduire le risque.'],
            ],
        ]);
    }
}
