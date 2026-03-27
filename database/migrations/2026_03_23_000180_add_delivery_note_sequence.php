<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $companies = DB::table('companies')->pluck('id');

        foreach ($companies as $companyId) {
            DB::table('document_sequences')->updateOrInsert(
                [
                    'company_id' => $companyId,
                    'document_type' => 'delivery_note',
                ],
                [
                    'prefix' => 'BL-{BRANCH}-{YEAR}-',
                    'next_number' => 1,
                    'padding' => 5,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('document_sequences')->where('document_type', 'delivery_note')->delete();
    }
};
