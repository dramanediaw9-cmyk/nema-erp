@php
    $currentView = ($view ?? 'list') === 'kanban' ? 'kanban' : 'list';
    $label = $label ?? 'Choix de vue';
@endphp

<div class="erp-view-switcher" role="tablist" aria-label="{{ $label }}">
    <a href="{{ $listUrl }}" class="erp-view-switcher__link {{ $currentView === 'list' ? 'is-active' : '' }}" role="tab" aria-selected="{{ $currentView === 'list' ? 'true' : 'false' }}">
        Liste
    </a>
    <a href="{{ $kanbanUrl }}" class="erp-view-switcher__link {{ $currentView === 'kanban' ? 'is-active' : '' }}" role="tab" aria-selected="{{ $currentView === 'kanban' ? 'true' : 'false' }}">
        Kanban
    </a>
</div>
