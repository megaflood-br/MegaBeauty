<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // Quem realizou a ação (Admin)

            $table->decimal('quantity', 10, 2); // Quantidade movimentada (aceita frações se necessário)
            $table->enum('type', ['input', 'output']); // input = Entrada, output = Saída
            $table->string('reason'); // Motivo: "Ajuste Manual", "Compra", "Consumo em Serviço"
            $table->text('description')->nullable(); // Alguma observação extra do Admin

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
