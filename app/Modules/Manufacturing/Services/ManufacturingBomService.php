<?php

namespace App\Modules\Manufacturing\Services;

use App\Modules\Manufacturing\Models\ManufacturingBom;
use Illuminate\Validation\ValidationException;

class ManufacturingBomService
{
    public function createBillOfMaterial(int $companyId, ?int $actorId, array $data, array $lines): ManufacturingBom
    {
        $bom = ManufacturingBom::query()->create([
            'company_id' => $companyId,
            'branch_id' => $data['branch_id'] ?? null,
            'code' => ($data['code'] ?? null) ?: $this->generateCode($companyId),
            'item_name' => $data['item_name'],
            'output_quantity' => $data['output_quantity'],
            'status' => $data['status'] ?? 'active',
            'notes' => $data['notes'] ?? null,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        foreach (array_values($lines) as $index => $line) {
            $bom->lines()->create([
                'component_code' => $line['component_code'] ?? null,
                'component_name' => $line['component_name'],
                'quantity' => $line['quantity'],
                'unit' => $line['unit'] ?? 'u',
                'wastage_rate' => $line['wastage_rate'] ?? 0,
                'notes' => $line['notes'] ?? null,
                'sequence' => $index + 1,
            ]);
        }

        return $bom->load(['branch', 'lines']);
    }

    public function parseComponentMatrix(string $matrix): array
    {
        $lines = collect(preg_split('/\r\n|\r|\n/', trim($matrix)) ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'components' => 'Ajoutez au moins une ligne composant au format nom|quantite|unite|taux rebut.',
            ]);
        }

        return $lines->map(function (string $line, int $index): array {
            $parts = array_map(static fn (string $part): string => trim($part), explode('|', $line));

            if (count($parts) < 3) {
                throw ValidationException::withMessages([
                    'components' => 'Chaque ligne composant doit contenir au minimum nom|quantite|unite.',
                ]);
            }

            return [
                'component_name' => $parts[0],
                'quantity' => (float) $parts[1],
                'unit' => $parts[2],
                'wastage_rate' => isset($parts[3]) && $parts[3] !== '' ? (float) $parts[3] : 0,
                'notes' => $parts[4] ?? null,
                'sequence' => $index + 1,
            ];
        })->all();
    }

    public function generateCode(int $companyId): string
    {
        $sequence = ManufacturingBom::query()->where('company_id', $companyId)->count() + 1;

        return 'BOM-'.now()->format('Y').'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
