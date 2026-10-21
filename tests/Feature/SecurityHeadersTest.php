<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_responses_contain_required_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'microphone=(self), camera=(), geolocation=()');
    }

    public function test_framework_public_storage_route_is_not_registered(): void
    {
        $hasStorageRoute = Route::has('storage.local');

        $this->assertFalse($hasStorageRoute, 'Public storage route storage.local must NOT be registered');
    }

    public function test_pwa_manifest_is_accessible_and_valid_json(): void
    {
        $manifestPath = public_path('manifest.json');
        $this->assertFileExists($manifestPath);

        $json = json_decode(file_get_contents($manifestPath), true);
        $this->assertIsArray($json);
        $this->assertEquals('Aplikasi Notulensi Rapat', $json['name']);
        $this->assertEquals('Notulensi', $json['short_name']);
        $this->assertEquals('standalone', $json['display']);
        $this->assertCount(3, $json['icons']);
    }
}
