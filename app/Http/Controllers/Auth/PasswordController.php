<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    /**
     * Show the form for editing the authenticated user's password.
     */
    public function edit(Request $request): View
    {
        return view('auth.password-edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the authenticated user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ], [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'current_password.current_password' => 'Password saat ini tidak sesuai.',
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password baru minimal harus 8 karakter.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
        ]);

        $user = $request->user();
        $user->update([
            'password' => $validated['password'],
        ]);

        Audit::record('user_password_changed', meta: [
            'user_id' => $user->id,
            'role' => $user->role,
            'email' => $user->email,
        ]);

        return redirect()->route('dashboard')->with('success', 'Password Anda berhasil diperbarui.');
    }
}
