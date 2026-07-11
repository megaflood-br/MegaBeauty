<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Como vamos reconstruir a lógica da agenda, dropamos a antiga primeiro
        Schema::dropIfExists('appointments');

        // 1. Tabela de Status Customizados pelo Admin (Ex: Confirmado, Aguardando, Em Atendimento...)
        Schema::create('appointment_statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable(); // null para padrões do sistema, preenchido para personalizados do salão
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->string('name'); // Nome do status (ex: "Confirmado")
            $table->string('color')->default('#34d399'); // Hexadecimal da cor para renderizar na agenda
            $table->boolean('is_default')->default(false); // Flag para identificar o status padrão inicial
            $table->timestamps();
        });

        // 2. Tabela Pai: O Agendamento Geral (Agrupa os dados do cabeçalho do modal)
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('customer_id');
            $table->foreign('customer_id')->references('id')->on('users')->onDelete('cascade');

            $table->unsignedBigInteger('appointment_status_id'); // Relacionamento com os status dinâmicos
            $table->foreign('appointment_status_id')->references('id')->on('appointment_statuses');

            $table->date('date'); // Data do agendamento
            $table->string('color_override')->nullable(); // Se o usuário quiser mudar a cor específica desse agendamento no modal

            // Flags do Modal
            $table->boolean('send_reminder')->default(true); // Enviar lembrete toggle
            $table->boolean('is_overbooked')->default(false); // Encaixar agendamento toggle

            // Lógica de Repetição/Recorrência
            // options: 'none', 'daily', 'weekly', 'monthly'
            $table->enum('repeat_mode', ['none', 'daily', 'weekly', 'monthly'])->default('none');
            $table->integer('repeat_until_turns')->nullable(); // Quantas vezes vai repetir se houver limite

            $table->text('notes')->nullable(); // Observações gerais do rodapé do modal
            $table->timestamps();
        });

        // 3. Tabela Filho: Itens do Agendamento (Suporta múltiplos serviços/profissionais na mesma marcação)
        Schema::create('appointment_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id');
            $table->foreign('appointment_id')->references('id')->on('appointments')->onDelete('cascade');

            $table->unsignedBigInteger('service_id');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');

            $table->unsignedBigInteger('professional_id');
            $table->foreign('professional_id')->references('id')->on('users')->onDelete('cascade');

            $table->time('start_time'); // Horário de início do item (ex: 00:10h do print)
            $table->integer('duration_minutes_override')->nullable(); // Caso queira mudar a duração padrão do serviço só nessa vez

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_items');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('appointment_statuses');
    }
};
