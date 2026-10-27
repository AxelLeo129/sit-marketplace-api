<?php

namespace Tests\Feature;

use App\Enums\AuthEventType;
use App\Enums\Role;
use App\Models\AuthEvent;
use App\Models\DoctorProfile;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $login, string $password = 'password'): TestResponse
    {
        // Cada intento con su propia IP: así se prueba el bloqueo de la cuenta, no el límite por IP.
        static $ip = 1;

        return $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.$ip++])
            ->postJson('/api/login', ['login' => $login, 'password' => $password]);
    }

    private function failTimes(User $user, int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->login($user->email, 'incorrecta');
        }
    }

    public function test_locks_the_account_after_five_failures_even_from_different_ips(): void
    {
        $user = User::factory()->create();

        $this->failTimes($user, 4);
        $this->login($user->email, 'incorrecta')->assertStatus(429)->assertHeader('Retry-After', 900);

        // Bloqueada: ni con la contraseña correcta.
        $this->login($user->email)->assertStatus(429);
        $this->assertDatabaseHas('auth_events', ['user_id' => $user->id, 'type' => AuthEventType::AccountLocked->value]);

        // Al pasar el bloqueo vuelve a entrar y se limpian los contadores.
        $this->travel(16)->minutes();
        $this->login($user->email)->assertOk();
        $this->assertSame(0, $user->refresh()->lockout_count);
    }

    public function test_each_new_lockout_lasts_twice_as_long(): void
    {
        $user = User::factory()->create();

        $this->failTimes($user, 5);
        $this->travel(16)->minutes();
        $this->failTimes($user, 4);

        $this->login($user->email, 'incorrecta')->assertStatus(429)->assertHeader('Retry-After', 1800);
    }

    public function test_password_reset_removes_the_lock(): void
    {
        $user = User::factory()->create();
        $this->failTimes($user, 5);

        $token = Password::createToken($user);
        $this->postJson('/api/reset-password', [
            'token' => $token, 'email' => $user->email,
            'password' => 'nueva-clave-123', 'password_confirmation' => 'nueva-clave-123',
        ])->assertOk();

        $this->assertNull($user->refresh()->locked_until);
        $this->login($user->email, 'nueva-clave-123')->assertOk();
    }

    public function test_login_is_also_limited_per_account_and_ip(): void
    {
        RateLimiter::clear('login');
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['login' => 'otro@demo.test', 'password' => 'x']);
        }

        $this->postJson('/api/login', ['login' => 'otro@demo.test', 'password' => 'x'])->assertStatus(429);
        // Otra cuenta desde la misma IP todavía puede intentar.
        $this->postJson('/api/login', ['login' => $user->email, 'password' => 'password'])->assertOk();
    }

    public function test_records_authentication_events(): void
    {
        $user = User::factory()->create();

        $this->login('no-existe@demo.test', 'x');
        $token = $this->login($user->email)->json('token');
        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertDatabaseHas('auth_events', ['type' => 'login_failed', 'user_id' => null, 'login' => 'no-existe@demo.test', 'detail' => 'unknown_user']);
        $this->assertDatabaseHas('auth_events', ['type' => 'login_succeeded', 'user_id' => $user->id]);
        $this->assertDatabaseHas('auth_events', ['type' => 'logout', 'user_id' => $user->id]);
    }

    public function test_only_admin_reads_the_access_log(): void
    {
        $user = User::factory()->create();
        $this->login($user->email, 'incorrecta');

        $this->actingAs(User::factory()->role(Role::Admin)->create())
            ->getJson('/api/security/auth-events?type=login_failed')
            ->assertOk()
            ->assertJsonPath('data.0.type_label', 'Intento fallido')
            ->assertJsonPath('data.0.is_warning', true)
            ->assertJsonPath('data.0.user.id', $user->id);

        $this->actingAs(User::factory()->role(Role::CallCenter)->create())
            ->getJson('/api/security/auth-events')
            ->assertForbidden();
    }

    public function test_old_events_are_pruned(): void
    {
        // created_at no es asignable en bloque: lo pone la base al insertar.
        AuthEvent::create(['type' => AuthEventType::LoginSucceeded])->forceFill(['created_at' => now()->subDays(400)])->save();
        AuthEvent::create(['type' => AuthEventType::LoginSucceeded]);

        $this->artisan('model:prune', ['--model' => [AuthEvent::class]]);

        $this->assertDatabaseCount('auth_events', 1);
    }

    public function test_api_responses_carry_security_headers(): void
    {
        $this->getJson('/api/me')
            ->assertUnauthorized()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'")
            ->assertHeaderMissing('Strict-Transport-Security');

        $this->getJson('https://localhost/api/me')->assertHeader('Strict-Transport-Security');
    }

    public function test_clinical_data_is_encrypted_at_rest(): void
    {
        $doctor = DoctorProfile::factory()->create()->user;
        $prescription = Prescription::factory()->for($doctor, 'doctor')->create(['diagnosis' => 'Faringitis']);

        $stored = DB::table('prescriptions')->where('id', $prescription->id)->value('diagnosis');
        $this->assertNotSame('Faringitis', $stored);
        $this->assertStringNotContainsString('Faringitis', $stored);

        $this->actingAs($doctor)
            ->getJson("/api/prescriptions/{$prescription->id}")
            ->assertJsonPath('data.diagnosis', 'Faringitis');
    }

    public function test_rejects_breached_passwords(): void
    {
        config(['security.check_breached_passwords' => true]);
        $hash = strtoupper(sha1('password123'));
        Http::fake(['api.pwnedpasswords.com/*' => Http::response(substr($hash, 5).':1000')]);

        $this->actingAs($user = User::factory()->create())
            ->putJson('/api/me/password', [
                'current_password' => 'password',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertJsonValidationErrors('password');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_uploaded_images_are_reencoded_as_webp(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();

        $path = $this->actingAs(User::factory()->role(Role::Admin)->create())
            ->postJson("/api/products/{$product->id}/image", [
                'image' => UploadedFile::fake()->image('foto.jpg', 2400, 1200),
            ])
            ->assertOk()
            ->json('data.image_url');

        $stored = $product->refresh()->image_path;
        $this->assertStringEndsWith('.webp', $stored);
        $this->assertStringEndsWith('.webp', $path);

        // Se redujo al máximo configurado conservando la proporción.
        [$width, $height, $type] = getimagesizefromstring(Storage::disk('public')->get($stored));
        $this->assertSame([1600, 800, IMAGETYPE_WEBP], [$width, $height, $type]);
    }

    public function test_bcrypt_passwords_are_upgraded_to_argon2id_on_login(): void
    {
        $user = User::factory()->create(); // Creada con bcrypt (el driver de las pruebas).

        config(['hashing.driver' => 'argon2id', 'hashing.argon.verify' => false]);

        $this->login($user->email)->assertOk();
        $this->assertStringStartsWith('$argon2id$', $user->refresh()->getAuthPassword());
    }
}
