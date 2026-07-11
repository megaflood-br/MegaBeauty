<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'professional_id')) {
                $table->unsignedBigInteger('professional_id')->nullable()->after('payment_method_id');
                // Se preferir adicionar a FK:
                // $table->foreign('professional_id')->references('id')->on('professionals')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('professional_id');
        });
    }
};
