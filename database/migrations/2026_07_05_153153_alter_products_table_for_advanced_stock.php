<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Se as colunas básicas não existirem, cria
            if (!Schema::hasColumn('products', 'brand')) { $table->string('brand')->nullable()->after('category_id'); }
            if (!Schema::hasColumn('products', 'sku_code')) { $table->string('sku_code')->nullable()->after('brand'); }

            // Blindagem contra duplicidade
            if (!Schema::hasColumn('products', 'professional_price')) { $table->decimal('professional_price', 10, 2)->default(0.00)->after('sale_price'); }
            if (!Schema::hasColumn('products', 'default_commission_type')) { $table->enum('default_commission_type', ['percentage', 'fixed'])->default('percentage')->after('professional_price'); }
            if (!Schema::hasColumn('products', 'default_commission_value')) { $table->decimal('default_commission_value', 10, 2)->default(0.00)->after('default_commission_type'); }

            // Regras avançadas de estoque fracionado - Ajustado para verificar stock_quantity
            if (!Schema::hasColumn('products', 'output_unit_type')) {
                $table->string('output_unit_type')->default('unit');
            }
            if (!Schema::hasColumn('products', 'output_unit_equivalent')) {
                $table->decimal('output_unit_equivalent', 10, 2)->default(1.00);
            }
            if (!Schema::hasColumn('products', 'minimum_stock')) {
                $table->integer('minimum_stock')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $columns = [
                'brand', 'sku_code', 'professional_price', 'default_commission_value',
                'default_commission_type', 'output_unit_type', 'output_unit_equivalent', 'minimum_stock'
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
