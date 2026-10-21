<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationKopController extends Controller
{
    /**
     * Display the organization kop and address management page.
     */
    public function show(Request $request): View
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! ($user instanceof User) || ! $user->organization) {
            abort(403);
        }

        $organization = $user->organization;

        Gate::authorize('view', $organization);

        return view('organization.kop', [
            'organization' => $organization,
        ]);
    }

    /**
     * Upload and update the organization official kop image.
     */
    public function update(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! ($user instanceof User) || ! $user->organization) {
            abort(403);
        }

        $organization = $user->organization;

        Gate::authorize('update', $organization);

        $request->validate([
            'kop' => ['required', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'address_text' => ['nullable', 'string', 'max:500'],
        ], [
            'kop.required' => 'Pilih berkas gambar kop surat terlebih dahulu.',
            'kop.image' => 'Berkas harus berupa gambar yang valid.',
            'kop.mimes' => 'Format gambar kop harus berupa JPG, PNG, atau WebP.',
            'kop.max' => 'Ukuran berkas gambar kop tidak boleh melebihi 2MB.',
        ]);

        $file = $request->file('kop');
        $ext = $file->getClientOriginalExtension() ?: 'png';
        $filename = 'kop_'.time().'.'.$ext;
        $dir = "orgs/{$organization->id}/kop";

        // Remove old kop image if exists
        if ($organization->kop_path && Storage::disk('local')->exists($organization->kop_path)) {
            Storage::disk('local')->delete($organization->kop_path);
        }

        $path = $file->storeAs($dir, $filename, 'local');

        $updates = ['kop_path' => $path];
        if ($request->has('address_text')) {
            $updates['address_text'] = $request->input('address_text');
        }

        $organization->update($updates);

        Audit::record('kop_uploaded', null, [
            'organization_id' => $organization->id,
            'filename' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'size_bytes' => $file->getSize(),
        ]);

        return back()->with('success', 'Gambar kop surat resmi berhasil disimpan.');
    }

    /**
     * Remove the current organization kop image.
     */
    public function destroy(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! ($user instanceof User) || ! $user->organization) {
            abort(403);
        }

        $organization = $user->organization;

        Gate::authorize('update', $organization);

        if ($organization->kop_path && Storage::disk('local')->exists($organization->kop_path)) {
            Storage::disk('local')->delete($organization->kop_path);
        }

        $organization->update(['kop_path' => null]);

        Audit::record('kop_deleted', null, [
            'organization_id' => $organization->id,
        ]);

        return back()->with('success', 'Gambar kop surat resmi telah dihapus.');
    }

    /**
     * Securely stream/preview the organization kop image via controller.
     */
    public function preview(Request $request): StreamedResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! ($user instanceof User) || ! $user->organization) {
            abort(403);
        }

        $organization = $user->organization;

        Gate::authorize('view', $organization);

        if (! $organization->kop_path || ! Storage::disk('local')->exists($organization->kop_path)) {
            abort(404, 'Kop surat tidak ditemukan.');
        }

        return Storage::disk('local')->response($organization->kop_path, null, [
            'Cache-Control' => 'private, no-store, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
