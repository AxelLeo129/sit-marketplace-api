<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Datos de la clínica y especialidades de cada médico.
     */
    public function up(): void
    {
        Schema::create('doctor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('clinic_name')->nullable();
            $table->string('clinic_phone', 20)->nullable();
            $table->string('clinic_address')->nullable();
            // Ruta del logo en Firebase Storage; se imprime en la receta.
            $table->string('clinic_logo_path')->nullable();
            // Si el médico registra síntomas y diagnóstico en cada receta.
            $table->boolean('keeps_clinical_record')->default(true);
            $table->boolean('show_alerts')->default(true);
            $table->timestamps();
        });

        // Especialidades del médico (usa las mismas categorías del catálogo).
        Schema::create('category_user', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['category_id', 'user_id']);
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_user');
        Schema::dropIfExists('doctor_profiles');
    }
};
