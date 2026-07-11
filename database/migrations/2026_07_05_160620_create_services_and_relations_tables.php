<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Se a tabela NÃO existir, cria do zero completa
        if (!Schema::hasTable('services')) {
            Schema::create('services', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
                $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('set null');
                $table->string('name');
                $table->string('slug');
                $table->decimal('price', 10, 2);
                $table->decimal('additional_cost', 10, 2)->default(0.00);
                $table->decimal('commission_percentage', 5, 2)->default(0.00);
                $table->integer('duration_minutes')->default(30);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('image_path')->nullable();
                $table->timestamps();
            });
        } else {
            // 2. Se ela já existir (o seu caso atual), injetamos todas as colunas que estão faltando de forma segura
            Schema::table('services', function (Blueprint $table) {
                if (!Schema::hasColumn('services', 'slug')) { $table->string('slug')->after('name'); }
                if (!Schema::hasColumn('services', 'additional_cost')) { $table->decimal('additional_cost', 10, 2)->default(0.00)->after('price'); }
                if (!Schema::hasColumn('services', 'commission_percentage')) { $table->decimal('commission_percentage', 5, 2)->default(0.00)->after('additional_cost'); }
                if (!Schema::hasColumn('services', 'duration_minutes')) { $table->integer('duration_minutes')->default(30)->after('commission_percentage'); }
                if (!Schema::hasColumn('services', 'image_path')) { $table->string('image_path')->nullable()->after('is_active'); }
            });
        }

        // 3. Garante a criação da tabela pivô do estoque se ela não existir
        if (!Schema::hasTable('service_product')) {
            Schema::create('service_product', function (Blueprint $table) {
                $table->id();
                $table->foreignId('service_id')->constrained()->onDelete('cascade');
                $table->foreignId('product_id')->constrained()->onDelete('cascade');
                $table->decimal('consumed_quantity', 10, 2);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_product');
    }
};
