<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Limpeza de segurança
        Schema::dropIfExists('customer_anamneses');

        if (Schema::hasTable('services') && Schema::hasColumn('services', 'anamnesis_template_id')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropForeign(['anamnesis_template_id']);
                $table->dropColumn('anamnesis_template_id');
            });
        }

        Schema::dropIfExists('anamnesis_templates');

        // 1. Templates de Anamnese por Tenant
        Schema::create('anamnesis_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('form_schema');
            $table->timestamps();
        });

        // 2. Vinculando o template ao Serviço
        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('anamnesis_template_id')->nullable()->constrained()->nullOnDelete();
        });

        // 3. Respostas das Clientes (Link Público)
        Schema::create('customer_anamneses', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // CORREÇÃO: Apontando a chave estrangeira para a tabela 'users'
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();

            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();

            $table->json('responses')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_anamneses');

        if (Schema::hasTable('services') && Schema::hasColumn('services', 'anamnesis_template_id')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropForeign(['anamnesis_template_id']);
                $table->dropColumn('anamnesis_template_id');
            });
        }

        Schema::dropIfExists('anamnesis_templates');
    }
};
