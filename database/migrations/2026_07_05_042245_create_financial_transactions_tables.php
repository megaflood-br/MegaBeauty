<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabela de Categorias Financeiras (Plano de Contas)
        Schema::create('financial_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->string('name'); // Ex: "Água e Esgoto", "Telefonia", "Receita de Serviços", "Aluguel"
            $table->enum('type', ['revenue', 'expense']); // Define se a categoria é de Entrada ou Saída
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Tabela Principal de Transações (Entradas e Saídas Unificadas)
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            // Categoria Financeira da Transação
            $table->unsignedBigInteger('financial_category_id');
            $table->foreign('financial_category_id')->references('id')->on('financial_categories');

            // Forma de pagamento utilizada
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->onDelete('set null');

            // Titular da Transação: Pode apontar para a tabela users (Cliente, Profissional, ou um Fornecedor cadastrado como user)
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            // Controle de Valores (Conforme o print do Belasis)
            $table->decimal('gross_amount', 10, 2); // Valor bruto (ex: R$ 40,00)
            $table->decimal('fee_amount', 10, 2)->default(0.00); // Valor retido de taxa (ex: R$ 0,58)
            $table->decimal('net_amount', 10, 2); // Valor líquido real (ex: R$ 39,42)

            // Origem do Lançamento
            $table->string('source_type')->nullable(); // Ex: 'appointment', 'order', 'manual'
            $table->string('source_reference')->nullable(); // Ex: "C20991" (Número da comanda/agendamento do print)

            // Datas de Controle
            $table->date('due_date'); // Data de vencimento / previsão
            $table->date('payment_date')->nullable(); // Data em que o pagamento foi realizado de fato

            // Status do Lançamento
            // pending: Aberto/Aguardando | paid: Pago/Recebido | canceled: Cancelado
            $table->enum('status', ['pending', 'paid', 'canceled'])->default('pending');

            $table->text('notes')->nullable(); // Descrição / Histórico (ex: "Referente à comanda #20991...")
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('financial_categories');
    }
};
