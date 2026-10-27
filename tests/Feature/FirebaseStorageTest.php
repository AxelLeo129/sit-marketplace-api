<?php

namespace Tests\Feature;

use App\Filesystem\FirebaseStorageAdapter;
use App\Services\ImageStorage;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * El disco "firebase" se arma con la configuración y genera URLs de descarga de Firebase.
 * No llama a Google: solo comprueba la configuración y el formato de las URLs.
 */
class FirebaseStorageTest extends TestCase
{
    private string $credentials;

    protected function setUp(): void
    {
        parent::setUp();

        // Cuenta de servicio ficticia: basta para construir el cliente sin conectarse.
        $this->credentials = tempnam(sys_get_temp_dir(), 'firebase');
        file_put_contents($this->credentials, json_encode([
            'type' => 'service_account',
            'project_id' => 'sit-demo',
            'private_key_id' => 'test',
            'private_key' => 'test',
            'client_email' => 'api@sit-demo.iam.gserviceaccount.com',
            'client_id' => '1',
        ]));

        config([
            'filesystems.disks.firebase.project_id' => 'sit-demo',
            'filesystems.disks.firebase.bucket' => 'sit-demo.firebasestorage.app',
            'filesystems.disks.firebase.credentials' => $this->credentials,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->credentials);

        parent::tearDown();
    }

    public function test_builds_the_firebase_disk(): void
    {
        $this->assertInstanceOf(FirebaseStorageAdapter::class, Storage::disk('firebase'));
    }

    public function test_urls_use_the_firebase_download_format(): void
    {
        $this->assertSame(
            'https://firebasestorage.googleapis.com/v0/b/sit-demo.firebasestorage.app/o/products%2Fabc%201.webp?alt=media',
            Storage::disk('firebase')->url('products/abc 1.webp'),
        );
    }

    public function test_image_urls_come_from_the_configured_media_disk(): void
    {
        config(['filesystems.media_disk' => 'firebase']);

        $this->assertStringStartsWith(
            'https://firebasestorage.googleapis.com/v0/b/sit-demo.firebasestorage.app/o/avatars%2F',
            app(ImageStorage::class)->url('avatars/me.png'),
        );
    }
}
