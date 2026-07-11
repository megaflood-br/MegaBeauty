<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // 'pf' para Pessoa Física (CPF) e 'pj' para Pessoa Jurídica (CNPJ)
            $table->enum('company_type', ['pf', 'pj'])->default('pj')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('company_type');
        });
    }
};
