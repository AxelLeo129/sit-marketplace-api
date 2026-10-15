<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recetas emitidas por los médicos y sus medicamentos.
     */
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('users')->restrictOnDelete();
            $table->string('patient_name');
            $table->string('patient_phone', 20)->index();
            $table->string('patient_email')->nullable();
            $table->text('symptoms')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('observations')->nullable();
            $table->date('issued_on');
            $table->date('next_appointment_on')->nullable();
            // El paciente autoriza que el call-center lo llame para enviarle la receta.
            $table->boolean('allows_call_center')->default(true);
            // Valores de App\Enums\PrescriptionStatus.
            $table->string('status', 20)->default('pending')->index();
            // Fecha y hora acordada para volver a llamar (estado "reagendada").
            $table->dateTime('callback_at')->nullable();
            $table->timestamps();

            $table->index(['doctor_id', 'issued_on']);
        });

        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // Indicaciones de uso, p. ej. "1 tableta cada 8 horas por 5 días".
            $table->string('dosage_instructions');
            $table->timestamps();
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
    }
};
