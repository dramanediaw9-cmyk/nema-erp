@php
    $breakdown = is_array($breakdown ?? null) ? $breakdown : [];
    $placeholder = $placeholder ?? null;
    $breakdownUnits = collect(array_keys($cashDenominations))->sum(fn ($denomination) => (int) ($breakdown[$denomination] ?? 0));
    $hasBreakdown = $breakdownUnits > 0;
    $breakdownTotal = collect(array_keys($cashDenominations))->sum(fn ($denomination) => ((int) ($breakdown[$denomination] ?? 0)) * (int) $denomination);
@endphp

@if ($hasBreakdown || $placeholder !== null)
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Coupure</th>
                <th class="right">Quantite</th>
                <th class="right">Montant</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($cashDenominations as $denomination => $label)
                <tr>
                    <td>{{ $label }} ({{ number_format((int) $denomination, 0, ',', ' ') }} XOF)</td>
                    <td class="right">
                        {{ $hasBreakdown ? number_format((int) ($breakdown[$denomination] ?? 0), 0, ',', ' ') : $placeholder }}
                    </td>
                    <td class="right">
                        {{ $hasBreakdown ? number_format(((int) ($breakdown[$denomination] ?? 0)) * (int) $denomination, 0, ',', ' ') . ' XOF' : $placeholder }}
                    </td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
            <tr>
                <th colspan="2">Total especes</th>
                <th class="right">{{ $hasBreakdown ? number_format($breakdownTotal, 0, ',', ' ') . ' XOF' : $placeholder }}</th>
            </tr>
            </tfoot>
        </table>
    </div>
@endif