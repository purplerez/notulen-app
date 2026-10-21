<?php

namespace Tests\Feature\Organization;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrganizationKopTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $orgA;

    protected Organization $orgB;

    protected User $adminA;

    protected User $notulisA;

    protected User $pimpinanA;

    protected User $adminB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->orgA = Organization::factory()->create([
            'name' => 'SMP Negeri 1 Merdeka',
            'address_text' => 'Jl. Merdeka No. 10, Jakarta',
            'kop_path' => null,
        ]);

        $this->orgB = Organization::factory()->create([
            'name' => 'SMA Swasta Teladan',
            'address_text' => 'Jl. Pemuda No. 20, Bandung',
            'kop_path' => null,
        ]);

        $this->adminA = User::factory()->admin()->create(['organization_id' => $this->orgA->id]);
        $this->notulisA = User::factory()->notulis()->create(['organization_id' => $this->orgA->id]);
        $this->pimpinanA = User::factory()->pimpinan()->create(['organization_id' => $this->orgA->id]);

        $this->adminB = User::factory()->admin()->create(['organization_id' => $this->orgB->id]);
    }

    public function test_admin_can_view_kop_management_page(): void
    {
        $response = $this->actingAs($this->adminA)->get(route('organization.kop.show'));

        $response->assertOk();
        $response->assertSee('Kop Surat Organisasi');
        $response->assertSee('SMP Negeri 1 Merdeka');
        $response->assertSee('Belum Ada Gambar Kop Diunggah');
    }

    public function test_admin_can_upload_valid_kop_image(): void
    {
        $file = UploadedFile::fake()->image('kop_sekolah.png', 800, 200)->size(500); // 500 KB

        $response = $this->actingAs($this->adminA)->post(route('organization.kop.update'), [
            'kop' => $file,
            'address_text' => 'Jl. Merdeka No. 10 (Gedung A), Jakarta',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->orgA->refresh();

        $this->assertNotNull($this->orgA->kop_path);
        Storage::disk('local')->assertExists($this->orgA->kop_path);
        $this->assertSame('Jl. Merdeka No. 10 (Gedung A), Jakarta', $this->orgA->address_text);

        // Check audit log
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->orgA->id,
            'action' => 'kop_uploaded',
            'actor_user_id' => $this->adminA->id,
        ]);
    }

    public function test_kop_upload_rejects_non_image_or_oversized_files(): void
    {
        // 1. Rejects non-image file (.txt)
        $txtFile = UploadedFile::fake()->create('dokumen.txt', 100, 'text/plain');
        $responseTxt = $this->actingAs($this->adminA)->post(route('organization.kop.update'), [
            'kop' => $txtFile,
        ]);
        $responseTxt->assertSessionHasErrors('kop');

        // 2. Rejects oversized image (> 2048 KB)
        $largeFile = UploadedFile::fake()->image('big_kop.jpg')->size(2500);
        $responseLarge = $this->actingAs($this->adminA)->post(route('organization.kop.update'), [
            'kop' => $largeFile,
        ]);
        $responseLarge->assertSessionHasErrors('kop');
    }

    public function test_notulis_and_pimpinan_cannot_upload_or_delete_kop(): void
    {
        $file = UploadedFile::fake()->image('kop.png', 600, 150);

        // Notulis upload rejected
        $respNotulis = $this->actingAs($this->notulisA)->post(route('organization.kop.update'), [
            'kop' => $file,
        ]);
        $respNotulis->assertForbidden();

        // Pimpinan upload rejected
        $respPimpinan = $this->actingAs($this->pimpinanA)->post(route('organization.kop.update'), [
            'kop' => $file,
        ]);
        $respPimpinan->assertForbidden();

        // Notulis delete rejected
        $respDelNotulis = $this->actingAs($this->notulisA)->delete(route('organization.kop.destroy'));
        $respDelNotulis->assertForbidden();

        // Pimpinan delete rejected
        $respDelPimpinan = $this->actingAs($this->pimpinanA)->delete(route('organization.kop.destroy'));
        $respDelPimpinan->assertForbidden();
    }

    public function test_admin_can_delete_existing_kop_image(): void
    {
        // Setup initial kop file
        $path = "orgs/{$this->orgA->id}/kop/initial_kop.png";
        Storage::disk('local')->put($path, 'dummy-image-content');
        $this->orgA->update(['kop_path' => $path]);

        $this->assertTrue(Storage::disk('local')->exists($path));

        $response = $this->actingAs($this->adminA)->delete(route('organization.kop.destroy'));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->orgA->refresh();
        $this->assertNull($this->orgA->kop_path);
        Storage::disk('local')->assertMissing($path);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->orgA->id,
            'action' => 'kop_deleted',
            'actor_user_id' => $this->adminA->id,
        ]);
    }

    public function test_kop_preview_serves_image_securely_via_controller(): void
    {
        $path = "orgs/{$this->orgA->id}/kop/kop_preview.png";
        Storage::disk('local')->put($path, 'image-binary-stream-data');
        $this->orgA->update(['kop_path' => $path]);

        // 1. Guest is redirected to login
        $guestResp = $this->get(route('organization.kop.preview'));
        $guestResp->assertRedirect(route('login'));

        // 2. Org A member can preview
        $authResp = $this->actingAs($this->notulisA)->get(route('organization.kop.preview'));
        $authResp->assertOk();
        $authResp->assertHeader('Cache-Control', 'must-revalidate, no-store, private');

        // 3. Org B member only sees Org B's kop (tenant isolation: org B has no kop -> 404)
        $orgBResp = $this->actingAs($this->adminB)->get(route('organization.kop.preview'));
        $orgBResp->assertNotFound();
    }
}
