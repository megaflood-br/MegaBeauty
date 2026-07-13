<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::table('pop_templates', function (Blueprint $table) {
        // Adiciona a coluna categoria, padrão como 'Geral'
        $table->string('category')->default('Geral')->after('content');
    });
}

public function down(): void
{
    Schema::table('pop_templates', function (Blueprint $table) {
        $table->dropColumn('category');
    });
}
};
