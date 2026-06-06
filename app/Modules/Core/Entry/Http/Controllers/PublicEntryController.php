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
                    'result' => 'Un propriétaire clairement identifié.',
                ],
                [
                    'number' => '02',
                    'title' => 'Entreprise',
                    'text' => 'Définir l identité, les coordonnées et la devise de travail.',
                    'result' => 'Une société séparée et prête à être configurée.',
                ],
                [
                    'number' => '03',
                    'title' => 'Espace métier',
                    'text' => 'Choisir le secteur et préparer l agence principale.',
                    'result' => 'Un démarrage adapté à l activité réelle.',
                ],
                [
                    'number' => '04',
                    'title' => 'Formule',
                    'text' => 'Choisir la capacité en utilisateurs et en agences.',
                    'result' => 'Un essai de 14 jours sans paiement immédiat.',
                ],
            ],
        ]);
    }
}
