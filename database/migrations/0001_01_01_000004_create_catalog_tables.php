<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea el catálogo de medicamentos: categorías, laboratorios,
     * presentaciones y productos.
     */
    public function up(): void
    {
        // Áreas terapéuticas. Clasifican productos y especialidades de médicos.
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        Schema::create('labs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        // Forma farmacéutica, p. ej. "Caja" de 30 "tabletas".
        Schema::create('presentations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('unit', 50);
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('presentation_id')->constrained()->restrictOnDelete();
            $table->foreignId('lab_id')->constrained()->restrictOnDelete();
            // Precio de venta con IVA incluido.
            $table->decimal('price', 10, 2);
            $table->decimal('cost', 10, 2);
            $table->unsignedInteger('stock')->default(0);
            $table->string('warehouse', 100)->nullable();
            $table->boolean('is_active')->default(true);
            // Medicamento controlado que requiere receta.
            $table->boolean('is_regulated')->default(false);
            $table->text('observations')->nullable();
            // Ruta del objeto en Firebase Storage.
            $table->string('image_path')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('category_product', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['category_id', 'product_id']);
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('products');
        Schema::dropIfExists('presentations');
        Schema::dropIfExists('labs');
        Schema::dropIfExists('categories');
    }
};
