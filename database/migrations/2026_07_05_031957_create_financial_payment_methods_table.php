<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabela de Formas de Pagamento (Customizável por Tenant/Empresa)
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();

            // Multi-tenant: Cada salão/clínica pode ativar/configurar suas formas de pagamento
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->string('name'); // Ex: "Cartão de Crédito - Visa", "Pix", "Dinheiro"

            // Tipo lógico para o sistema saber como processar
            // options: 'money', 'pix', 'credit_card', 'debit_card', 'internal_wallet', 'cashback'
            $table->enum('type', ['money', 'pix', 'credit_card', 'debit_card', 'internal_wallet', 'cashback'])->default('money');

            // Configurações de taxas (Essencial para calcular o valor líquido real recebido pelo salão)
            $table->decimal('fee_percentage', 5, 2)->default(0.00); // Ex: 2.99% de taxa da maquininha
            $table->decimal('fixed_fee', 10, 2)->default(0.00);     // Ex: R$ 0,40 por transação (comum em Pix/Boleto)

            // Prazo de recebimento em dias (Útil para projeção de fluxo de caixa automático no dashboard)
            $table->integer('payout_days_interval')->default(0); // Ex: 0 para Pix/Dinheiro, 30 para Crédito

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
