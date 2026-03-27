<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $companies = DB::table('companies')->select('id')->get();

        foreach ($companies as $company) {
            foreach ([
                ['document_type' => 'purchase_order', 'prefix' => 'BCF-{BRANCH}-{YEAR}-'],
                ['document_type' => 'goods_receipt', 'prefix' => 'BRF-{BRANCH}-{YEAR}-'],
                ['document_type' => 'stock_transfer', 'prefix' => 'TRF-{BRANCH}-{YEAR}-'],
            ] as $sequence) {
                DB::table('document_sequences')->updateOrInsert(
                    ['company_id' => $company->id, 'document_type' => $sequence['document_type']],
                    [
                        'prefix' => $sequence['prefix'],
                        'next_number' => 1,
                        'padding' => 5,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        DB::table('document_sequences')->whereIn('document_type', ['purchase_order', 'goods_receipt', 'stock_transfer'])->delete();
    }
};
