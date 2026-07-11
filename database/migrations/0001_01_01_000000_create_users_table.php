<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabela de Empresas (Tenants)
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('active');

            // Dados Cadastrais e Endereço da Empresa (Admin)
            $table->string('document_cpf_cnpj')->nullable()->unique();
            $table->string('rg')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('address')->nullable();
            $table->string('number')->nullable();
            $table->string('complement')->nullable();
            $table->string('district')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->timestamps();
        });

        // Tabela de Usuários Unificada (Superadmin, Admin, Professional, Customer)
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->onDelete('cascade');

            // Identificação Básica
            $table->string('name');
            $table->string('nick')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->string('avatar')->nullable();
            $table->string('profession')->nullable();

            // Documentos Pessoais
            $table->string('user_document_cpf_cnpj')->nullable();
            $table->string('user_rg')->nullable();

            // Endereço Pessoal (Para Profissionais)
            $table->string('user_postal_code')->nullable();
            $table->string('user_address')->nullable();
            $table->string('user_number')->nullable();
            $table->string('user_complement')->nullable();
            $table->string('user_district')->nullable();
            $table->string('user_city')->nullable();
            $table->string('user_state', 2)->nullable();

            // Mídias Sociais e Preferências do Cliente
            $table->date('birth_date')->nullable();
            $table->string('instagram')->nullable();
            $table->string('facebook')->nullable();
            $table->text('hashtags')->nullable();
            $table->boolean('has_notifications')->default(true);

            // Flags de Configuração do Profissional
            $table->boolean('has_calendar')->default(true);
            $table->boolean('has_commission')->default(false);
            $table->text('observations')->nullable();

            // Nível de Acesso (Role)
            $table->enum('role', ['superadmin', 'admin', 'professional', 'customer'])->default('customer');
            $table->boolean('is_active')->default(true);

            // Permissões Dinâmicas
            $table->boolean('can_view_customer_phone')->default(true);
            $table->boolean('can_view_customer_email')->default(true);
            $table->boolean('can_see_other_professionals_agenda')->default(false);

            $table->rememberToken();
            $table->timestamps();
        });

        // Tabelas padrão do ecossistema do Laravel (Breeze/Auth exigem para rodar em modo FILE)
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('tenants');
    }
};
