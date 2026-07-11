<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Carteira do Cliente (Controle de Créditos, Débitos e Cashback)
        Schema::create('customer_wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // ID do cliente
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->decimal('balance_credits', 10, 2)->default(0.00); // Créditos que ele possui
            $table->decimal('balance_debits', 10, 2)->default(0.00);  // O que ele está devendo no estabelecimento
            $table->decimal('balance_cashback', 10, 2)->default(0.00); // Saldo de cashback disponível
            $table->decimal('cancellation_fee_rate', 10, 2)->default(0.00); // Taxa de cancelamento personalizada se houver

            $table->timestamps();
        });

        // 2. Fichas de Anamnese (Histórico médico/estético)
        Schema::create('customer_anamneses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('user_id'); // ID do cliente
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->string('title'); // Ex: "Avaliação Facial", "Ficha Corporal"
            $table->json('responses'); // Armazena as perguntas e respostas em formato JSON (flexível para qualquer questionário)
            $table->date('evaluation_date'); // Data da avaliação

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_anamneses');
        Schema::dropIfExists('customer_wallets');
    }
};
