<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea usuarios y tokens de recuperación de contraseña.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            // Número de colegiado: los médicos también pueden iniciar sesión con él.
            $table->string('collegiate_number', 30)->nullable()->unique();
            $table->string('phone', 20)->nullable();
            $table->date('birth_date')->nullable();
            // Ruta del objeto en Firebase Storage, no la URL pública.
            $table->string('avatar_path')->nullable();
            // Valores de App\Enums\Role.
            $table->string('role', 30)->index();
            // Visitador médico responsable de este médico.
            $table->foreignId('sales_rep_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Sin tabla "sessions": la API autentica con tokens de Sanctum, no con sesiones.
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
