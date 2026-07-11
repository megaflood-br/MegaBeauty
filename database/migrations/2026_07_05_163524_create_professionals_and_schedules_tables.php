<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabela Principal de Profissionais
        if (!Schema::hasTable('professionals')) {
            Schema::create('professionals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
                $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // Vinculo com o login

                // Dados Básicos
                $table->string('name');
                $table->string('nickname')->nullable();
                $table->string('profession')->nullable(); // Ex: Cabeleireira, Manicure
                $table->string('phone');
                $table->string('email')->nullable();
                $table->date('birthday')->nullable();

                // Documentação
                $table->string('cpf')->nullable();
                $table->string('rg')->nullable();
                $table->string('cnpj')->nullable();

                // Endereço Completo
                $table->string('cep')->nullable();
                $table->string('street')->nullable();
                $table->string('number')->nullable();
                $table->string('complement')->nullable();
                $table->string('neighborhood')->nullable();
                $table->string('city')->nullable();
                $table->string('state', 2)->nullable();

                // Configurações de Comissão (Baseado nos seus Prints de Referência)
                $table->string('commission_type')->default('percentage'); // percentage ou fixed
                $table->decimal('default_commission', 5, 2)->default(0.00);

                // Regras financeiras das comissões (Print 3)
                $table->string('tax_deduction_rule')->default('proportional'); // proportional, tenant_100, professional_100
                $table->string('discount_deduction_rule')->default('proportional'); // proportional, tenant_100, professional_100
                $table->boolean('deduct_additional_cost')->default(true); // Desconta custo adicional dos serviços?
                $table->string('deduct_consumed_products')->default('service'); // comission, service ou não descontar
                $table->string('consumed_product_price_type')->default('professional'); // cost, sale, professional

                // Outros
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. Tabela de Horários de Expediente / Agenda
        if (!Schema::hasTable('professional_schedules')) {
            Schema::create('professional_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('professional_id')->constrained()->onDelete('cascade');
                $table->integer('day_of_week'); // 0 = Domingo, 1 = Segunda, etc.
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->time('lunch_start')->nullable();
                $table->time('lunch_end')->nullable();
                $table->boolean('is_working')->default(true); // Se atende nesse dia
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_schedules');
        Schema::dropIfExists('professionals');
    }
};
