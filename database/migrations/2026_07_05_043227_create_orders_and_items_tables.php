<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabela Pai: Comanda / Pedido de Venda
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // Multi-tenant: Isolamento por Empresa
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            // O cliente que está pagando
            $table->unsignedBigInteger('customer_id');
            $table->foreign('customer_id')->references('id')->on('users')->onDelete('cascade');

            // Vinculo opcional com o Agendamento de origem (Se a comanda nasceu da Agenda)
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->foreign('appointment_id')->references('id')->on('appointments')->onDelete('set null');

            // Totais Financeiros
            $table->decimal('total_services', 10, 2)->default(0.00); // Soma dos serviços
            $table->decimal('total_products', 10, 2)->default(0.00); // Soma dos produtos vendidos no balcão
            $table->decimal('discount_amount', 10, 2)->default(0.00); // Desconto aplicado
            $table->decimal('total_amount', 10, 2)->default(0.00);    // Valor final líquido a pagar (Serviços + Produtos - Desconto)

            // Status da Comanda
            // open: Aberta no caixa | paid: Paga/Finalizada | canceled: Cancelada
            $table->enum('status', ['open', 'paid', 'canceled'])->default('open');

            $table->timestamp('closed_at')->nullable(); // Data/Hora exata do fechamento no caixa
            $table->timestamps();
        });

        // 2. Tabela Filho: Itens da Comanda (Serviços prestados e Produtos vendidos)
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');

            // O item pode ser um Serviço OU um Produto
            // 'service' ou 'product'
            $table->enum('item_type', ['service', 'product']);
            $table->unsignedBigInteger('item_id'); // ID do Serviço ou do Produto correspondente

            // Profissional que executou o serviço ou vendeu o produto (Essencial para a Comissão)
            $table->unsignedBigInteger('professional_id')->nullable();
            $table->foreign('professional_id')->references('id')->on('users')->onDelete('set null');

            // Valores e Quantidades
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);  // Preço unitário no momento da venda
            $table->decimal('total_price', 10, 2); // Preço unitário * Quantidade

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
