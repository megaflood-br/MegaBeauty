<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Desativa checagem de chaves para evitar incompatibilidade de tipos antigos de tabelas
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // 1. Tabela Principal de Comandas
        if (!Schema::hasTable('commands')) {
            Schema::create('commands', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->onDelete('cascade');

                // Usando unsignedBigInteger isolado para permitir o vínculo mesmo se o ID da tabela original variar de tipo
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedBigInteger('professional_id')->nullable();

                $table->string('code')->nullable();
                $table->enum('status', ['open', 'finished', 'canceled'])->default('open');

                $table->decimal('total_services', 10, 2)->default(0.00);
                $table->decimal('total_products', 10, 2)->default(0.00);
                $table->decimal('discount', 10, 2)->default(0.00);
                $table->decimal('total_amount', 10, 2)->default(0.00);

                $table->string('payment_method')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();

                // Cria os índices e relacionamentos de forma flexível
                $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
                $table->foreign('professional_id')->references('id')->on('professionals')->onDelete('set null');
            });
        }

        // 2. Serviços da Comanda
        if (!Schema::hasTable('command_services')) {
            Schema::create('command_services', function (Blueprint $table) {
                $table->id();
                $table->foreignId('command_id')->constrained()->onDelete('cascade');
                $table->foreignId('service_id')->constrained()->onDelete('cascade');
                $table->unsignedBigInteger('professional_id')->nullable();
                $table->decimal('price', 10, 2);
                $table->decimal('commission_value', 10, 2)->default(0.00);
                $table->timestamps();

                $table->foreign('professional_id')->references('id')->on('professionals')->onDelete('set null');
            });
        }

        // 3. Produtos Vendidos na Comanda
        if (!Schema::hasTable('command_products')) {
            Schema::create('command_products', function (Blueprint $table) {
                $table->id();
                $table->foreignId('command_id')->constrained()->onDelete('cascade');
                $table->foreignId('product_id')->constrained()->onDelete('cascade');
                $table->integer('quantity')->default(1);
                $table->decimal('price', 10, 2);
                $table->timestamps();
            });
        }

        // Reativa a checagem global do MySQL
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::dropIfExists('command_products');
        Schema::dropIfExists('command_services');
        Schema::dropIfExists('commands');
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
};
