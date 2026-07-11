<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabela de Serviços
        Schema::create('services', function (Blueprint $table) {
            $table->id(); // Cria como BIGINT UNSIGNED
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('duration_minutes');
            $table->decimal('price', 10, 2);
            $table->timestamps();
        });

        // 2. Tabela de Expediente (Work Schedules)
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            // Declarando explicitamente o tipo unsignedBigInteger antes da chave estrangeira
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->integer('day_of_week');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->time('break_start_time')->nullable();
            $table->time('break_end_time')->nullable();
            $table->boolean('is_working')->default(true);
            $table->timestamps();
        });

        // 3. Tabela Intermédia (Professional Services)
        Schema::create('professional_services', function (Blueprint $table) {
            $table->id();

            // Forçando os tipos exatos para bater com bigint(20) unsigned das tabelas pais
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unsignedBigInteger('service_id');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');

            $table->enum('commission_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('commission_value', 10, 2)->default(0.00);

            $table->timestamps();
        });

        // 4. Tabela de Histórico de Pagamentos
        Schema::create('professional_payments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->decimal('amount', 10, 2);
            $table->date('payment_date');
            $table->string('payment_method')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_payments');
        Schema::dropIfExists('professional_services');
        Schema::dropIfExists('work_schedules');
        Schema::dropIfExists('services');
    }
};
