@php($printCompany = $company ?? null)
<div class="company-brand">
    @if ($printCompany?->logo_path)
        <img class="company-brand-logo" src="{{ asset('storage/'.$printCompany->logo_path) }}" alt="Logo {{ $printCompany->name }}">
    @endif
    <div>
        <div><strong>{{ $printCompany?->legal_name ?: $printCompany?->name }}</strong></div>
        <div class="meta">{{ $printCompany?->address }}</div>
        <div class="meta">Tel : {{ $printCompany?->phone ?: 'N/A' }} @if($printCompany?->email)· {{ $printCompany->email }} @endif</div>
        <div class="meta">NIF : {{ $printCompany?->nif ?: 'N/A' }} · RCCM : {{ $printCompany?->rccm ?: 'N/A' }}</div>
    </div>
</div>
