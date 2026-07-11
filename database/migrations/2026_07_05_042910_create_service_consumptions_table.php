<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabela que define quais produtos/insumos um serviço consome automaticamente
        Schema::create('service_consumptions', function (Blueprint $table) {
            $table->id();

            // Multi-tenant: Isolamento por Empresa
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            // O serviço pai (Ex: Manicure)
            $table->unsignedBigInteger('service_id');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');

            // O produto que será consumido (Ex: Algodão)
            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');

            // Quantidade consumida por atendimento.
            // Usamos decimal caso o controle seja por kg, litros ou frações (Ex: 0.050 para 50ml de acetona)
            $table->decimal('quantity', 10, 3)->default(1.000);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_consumptions');
    }
};
