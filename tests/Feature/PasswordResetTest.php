<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_a_link_to_the_frontend(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, config('app.frontend_url').'/reset-password?token=');
        });
    }

    public function test_same_response_for_unknown_emails(): void
    {
        Notification::fake();

        $this->postJson('/api/forgot-password', ['email' => 'nadie@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.');

        Notification::assertNothingSent();
    }

    public function test_resets_the_password_and_closes_sessions(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $user->createToken('viejo');

        $this->postJson('/api/forgot-password', ['email' => $user->email]);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nueva-clave-1',
            'password_confirmation' => 'nueva-clave-1',
        ])->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/login', ['login' => $user->email, 'password' => 'nueva-clave-1'])->assertOk();
    }

    public function test_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/reset-password', [
            'token' => 'inventado',
            'email' => $user->email,
            'password' => 'nueva-clave-1',
            'password_confirmation' => 'nueva-clave-1',
        ])->assertUnprocessable();
    }
}
