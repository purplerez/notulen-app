<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PlatformPasswordController extends Controller
{
    /**
     * Show the form for editing the platform super admin's password.
     */
    public function edit(Request $request): View
    {
        return view('platform.password-edit', [
            'superAdmin' => auth('platform')->user(),
        ]);
    }

    /**
     * Update the platform super admin's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:platform'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ], [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'current_password.current_password' => 'Password saat ini tidak sesuai.',
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password baru minimal harus 8 karakter.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
        ]);

        $superAdmin = auth('platform')->user();
        $superAdmin->update([
            'password' => $validated['password'],
        ]);

        return redirect()->route('platform.dashboard')->with('success', 'Password Super Admin berhasil diperbarui.');
    }
}
