<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_sequences', function (Blueprint $table) {
            $table->string('prefix', 64)->change();
        });
    }

    public function down(): void
    {
        Schema::table('document_sequences', function (Blueprint $table) {
            $table->string('prefix', 20)->change();
        });
    }
};