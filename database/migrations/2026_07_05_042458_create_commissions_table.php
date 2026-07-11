<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();

            // Multi-tenant: Isolamento por Empresa
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            // Quem vai receber a comissão (Usuário com role professional)
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // De onde veio essa comissão? (Vinculado à transação financeira que gerou a entrada)
            $table->unsignedBigInteger('transaction_id');
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('cascade');

            // Detalhes do item que gerou a comissão (Útil para relatórios detalhados)
            // Pode vir de um Serviço do Agendamento ou da Venda de um Produto
            $table->string('source_type'); // Ex: 'service' ou 'product'
            $table->unsignedBigInteger('source_id'); // ID do Serviço ou do Produto vendido

            // Valores para auditoria e histórico seguro
            $table->decimal('base_amount', 10, 2); // Valor base do item (ex: Preço do serviço R$ 100,00)
            $table->decimal('commission_percentage', 5, 2)->default(0.00); // % aplicado na hora (ex: 30.00%)
            $table->decimal('calculated_amount', 10, 2); // Valor líquido da comissão ganha (ex: R$ 30,00)

            // Controle de Fechamento / Pagamento ao Profissional
            // standard_payment_id: null quando ainda está pendente (o profissional acumulou o valor mas não recebeu do salão)
            // Ele será preenchido com o ID da tabela 'professional_payments' assim que o Admin fizer o fechamento e pagar o funcionário.
            $table->unsignedBigInteger('professional_payment_id')->nullable();
            $table->foreign('professional_payment_id')->references('id')->on('professional_payments')->onDelete('set null');

            // Status da comissão para controle rápido
            // pending: Acumulado na carteira interna do profissional | paid: Já foi pago no fechamento
            $table->enum('status', ['pending', 'paid'])->default('pending');

            $table->date('accrued_date'); // Data em que o profissional ganhou a comissão (geralmente a data da transação)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
