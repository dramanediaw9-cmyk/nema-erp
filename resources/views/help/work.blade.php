@extends('layouts.app')

@section('title', 'Aide travail - Nema ERP')
@section('page-title', 'Aide travail')

@section('content')
    <div class="page-head">
        <div>
            <h2 style="margin:0;">Aide au travail quotidien</h2>
            <div class="muted">Les explications longues sont regroupees ici pour garder les ecrans metier rapides et denses.</div>
        </div>
    </div>

    <section class="grid" style="grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));">
        <article class="card">
            <h3 class="section-title">Mode Travail</h3>
            <p class="muted">Le bouton T compacte le menu, reduit l en-tete, masque les textes explicatifs et donne la priorite aux tableaux, aux listes et aux indicateurs metier.</p>
        </article>
        <article class="card">
            <h3 class="section-title">Barre modules</h3>
            <p class="muted">Les modules sont affiches en barre horizontale compacte. Les icones d epinglage ont ete retirees de cette zone pour eviter les actions inutiles pendant le travail.</p>
        </article>
        <article class="card">
            <h3 class="section-title">Donnees avant decoration</h3>
            <p class="muted">Les ecrans stock, mouvements, inventaires et transferts doivent afficher les chiffres et les lignes utiles avant les messages de presentation.</p>
        </article>
        <article class="card">
            <h3 class="section-title">Avant la vente</h3>
            <p class="muted">Nema suit une logique ERP professionnelle : compte proprietaire, base entreprise, comptabilite, puis donnees de base avant la premiere vente.</p>
        </article>
    </section>
@endsection
