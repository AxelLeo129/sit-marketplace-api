<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bloqueo de cuentas por intentos fallidos y registro de eventos de autenticación.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Contraseñas incorrectas seguidas desde el último login correcto.
            $table->unsignedTinyInteger('failed_login_attempts')->default(0)->after('is_active');
            // Bloqueos consecutivos: cada uno dura el doble que el anterior.
            $table->unsignedTinyInteger('lockout_count')->default(0)->after('failed_login_attempts');
            $table->timestamp('locked_until')->nullable()->after('lockout_count');
        });

        // Solo se agregan filas: nunca se editan, para que sirva como auditoría.
        Schema::create('auth_events', function (Blueprint $table) {
            $table->id();
            // Valores de App\Enums\AuthEventType.
            $table->string('type', 40);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Lo que se escribió para entrar (correo o colegiado), útil cuando el usuario no existe.
            $table->string('login', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('detail', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['type', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('auth_events');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['failed_login_attempts', 'lockout_count', 'locked_until']);
        });
    }
};
