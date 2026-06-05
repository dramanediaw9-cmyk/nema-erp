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
                    'title' => 'Compte proprietaire',
                    'text' => 'Identifier le responsable, son email, son role et les acces de depart.',
                    'result' => 'Un administrateur clair pour piloter l espace Nema.',
                ],
                [
                    'number' => '02',
                    'title' => 'Base entreprise',
                    'text' => 'Creer la societe, la devise, le pays, l adresse et la structure de depart.',
                    'result' => 'Une base propre, separee et prete pour les operations.',
                ],
                [
                    'number' => '03',
                    'title' => 'Comptabilite',
                    'text' => 'Preparer taxes, comptes, caisse, banque, mobile money et regles de facturation.',
                    'result' => 'Des ventes fiables, sans corriger la compta apres coup.',
                ],
                [
                    'number' => '04',
                    'title' => 'Donnees de base',
                    'text' => 'Charger clients, fournisseurs, produits, prix et stock initial avant la premiere vente.',
                    'result' => 'Un ERP pret a vendre avec les vraies donnees de l entreprise.',
                ],
            ],
        ]);
    }
}
