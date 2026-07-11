<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professionals', function (Blueprint $table) {
            // Verifica se a coluna is_active existe, caso contrário gerencia as novas flags
            if (!Schema::hasColumn('professionals', 'generate_schedule')) {
                $table->boolean('generate_schedule')->default(true)->after('profession'); // Define se aparece no calendário
            }
            if (!Schema::hasColumn('professionals', 'earns_commission')) {
                $table->boolean('earns_commission')->default(true)->after('commission_type'); // Define se calcula comissão
            }
            // Foto caso não tenha sido criada no passo anterior
            if (!Schema::hasColumn('professionals', 'photo')) {
                $table->string('photo')->nullable()->after('user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('professionals', function (Blueprint $table) {
            $table->dropColumn(['generate_schedule', 'earns_commission', 'photo']);
        });
    }
};
