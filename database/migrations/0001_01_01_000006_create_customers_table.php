<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clientes (pacientes que compran) y sus direcciones.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // NIT para la factura; "CF" para consumidor final.
            $table->string('tax_id', 20)->default('CF');
            // El call-center busca al cliente por teléfono.
            $table->string('phone', 20)->index();
            $table->string('email')->nullable();
            $table->string('gender', 20)->nullable();
            $table->date('birth_date')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            // Valores de App\Enums\AddressType.
            $table->string('type', 20);
            $table->string('country', 100)->default('Guatemala');
            $table->string('department', 100);
            $table->string('municipality', 100);
            $table->string('street');
            $table->string('apartment')->nullable();
            // Colonia, residencial o zona.
            $table->string('neighborhood')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('phone', 20)->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'type']);
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
    }
};
