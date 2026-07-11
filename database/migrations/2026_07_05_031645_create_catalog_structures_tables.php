<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Como vamos reconstruir a tabela de serviços de forma completa, dropamos a antiga e as que dependem dela temporariamente
        Schema::dropIfExists('appointment_items');
        Schema::dropIfExists('professional_services');
        Schema::dropIfExists('services');

        // 1. Tabela de Categorias (Atende tanto Serviços quanto Produtos. Ex: "Cabelo", "Estética", "Home Care")
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->string('name');
            $table->enum('type', ['service', 'product']); // Diferencia se a categoria é de serviço ou produto
            $table->timestamps();
        });

        // 2. Tabela de Serviços Reestruturada e Completa
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('category_id')->nullable();
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');

            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('duration_minutes'); // Duração padrão do serviço
            $table->decimal('price', 10, 2); // Preço padrão de venda

            // Configuração de comissão padrão do serviço (caso o profissional não tenha uma customizada)
            $table->enum('default_commission_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('default_commission_value', 10, 2)->default(0.00);

            $table->boolean('allow_online_booking')->default(true); // Se o cliente pode agendar esse serviço pelo site/app
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Tabela de Produtos (Focada em venda interna e controle de estoque do SaaS)
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('category_id')->nullable();
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');

            $table->string('name');
            $table->string('barcode')->nullable(); // Código de barras para facilitar a venda no balcão
            $table->text('description')->nullable();

            // Valores Financeiros
            $table->decimal('cost_price', 10, 2)->default(0.00); // Preço de custo (para relatório de lucro)
            $table->decimal('sale_price', 10, 2); // Preço de venda ao consumidor

            // Controle de Estoque
            $table->integer('stock_quantity')->default(0); // Estoque atual
            $table->integer('min_stock_alert')->default(0); // Alerta quando o estoque estiver baixo

            // Comissão sobre venda de produto (comum em salões para motivar a recepção/profissionais)
            $table->decimal('commission_percentage', 5, 2)->default(0.00);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 4. Recriando as tabelas que dependiam de Services (Garantindo a integridade do banco)
        Schema::create('professional_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('service_id');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
            $table->enum('commission_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('commission_value', 10, 2)->default(0.00);
            $table->timestamps();
        });

        Schema::create('appointment_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id');
            $table->foreign('appointment_id')->references('id')->on('appointments')->onDelete('cascade');
            $table->unsignedBigInteger('service_id');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
            $table->unsignedBigInteger('professional_id');
            $table->foreign('professional_id')->references('id')->on('users')->onDelete('cascade');
            $table->time('start_time');
            $table->integer('duration_minutes_override')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_items');
        Schema::dropIfExists('professional_services');
        Schema::dropIfExists('products');
        Schema::dropIfExists('services');
        Schema::dropIfExists('categories');
    }
};
