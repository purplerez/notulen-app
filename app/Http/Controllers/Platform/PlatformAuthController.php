<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\SuperAdmin;
use App\Support\Audit;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PlatformAuthController extends Controller
{
    /**
     * Display the platform super admin login form.
     */
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::guard('platform')->check() && session('platform_2fa_verified') === true) {
            return redirect()->route('platform.dashboard');
        }

        return view('platform.auth.login');
    }

    /**
     * Handle initial credentials authentication step.
     */
    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
        ]);

        /** @var SuperAdmin|null $superAdmin */
        $superAdmin = SuperAdmin::where('email', $validated['email'])->first();

        if (! $superAdmin || ! Hash::check($validated['password'], $superAdmin->password)) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'Kredensial Super Admin tidak sesuai atau tidak terdaftar.',
            ]);
        }

        // Store pre-auth ID in session for 2FA step
        session(['platform_auth_id' => $superAdmin->id]);
        session()->forget('platform_2fa_verified');

        // If admin does not have a TOTP secret yet, generate one for setup
        if (empty($superAdmin->totp_secret)) {
            $superAdmin->update([
                'totp_secret' => Totp::generateSecret(16),
                'totp_enabled' => false,
            ]);
        }

        return redirect()->route('platform.verify_2fa');
    }

    /**
     * Display the 2FA (TOTP) verification form.
     */
    public function show2faForm(): View|RedirectResponse
    {
        $adminId = session('platform_auth_id') ?? Auth::guard('platform')->id();

        if (! $adminId) {
            return redirect()->route('platform.login');
        }

        /** @var SuperAdmin $superAdmin */
        $superAdmin = SuperAdmin::findOrFail($adminId);
        $isSetup = ! $superAdmin->hasTotpEnabled();

        // Ensure secret exists
        if (empty($superAdmin->totp_secret)) {
            $superAdmin->update([
                'totp_secret' => Totp::generateSecret(16),
                'totp_enabled' => false,
            ]);
            $superAdmin->refresh();
        }

        $secret = (string) $superAdmin->totp_secret;
        $otpUri = Totp::getOtpAuthUri($superAdmin->email, $secret);

        return view('platform.auth.verify_2fa', [
            'superAdmin' => $superAdmin,
            'isSetup' => $isSetup,
            'secret' => $secret,
            'otpUri' => $otpUri,
        ]);
    }

    /**
     * Verify the 6-digit TOTP code.
     */
    public function verify2fa(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ], [
            'code.required' => 'Kode OTP 6-digit wajib dimasukkan.',
            'code.size' => 'Kode OTP harus tepat 6 digit angka.',
        ]);

        $adminId = session('platform_auth_id') ?? Auth::guard('platform')->id();

        if (! $adminId) {
            return redirect()->route('platform.login')->withErrors([
                'email' => 'Sesi login telah berakhir. Silakan masuk kembali.',
            ]);
        }

        /** @var SuperAdmin $superAdmin */
        $superAdmin = SuperAdmin::findOrFail($adminId);

        $isValid = Totp::verify((string) $superAdmin->totp_secret, (string) $request->input('code'));

        if (! $isValid) {
            Audit::record('platform_login_2fa', null, [
                'super_admin_id' => $superAdmin->id,
                'email' => $superAdmin->email,
                'status' => 'failed',
                'ip' => $request->ip(),
            ]);

            return back()->withErrors([
                'code' => 'Kode autentikasi 2FA (TOTP) tidak valid atau telah kedaluwarsa. Pastikan jam perangkat Anda sinkron.',
            ]);
        }

        // Successfully verified
        if (! $superAdmin->hasTotpEnabled()) {
            $superAdmin->update([
                'totp_enabled' => true,
                'totp_confirmed_at' => now(),
            ]);
        }

        Auth::guard('platform')->login($superAdmin);
        session()->forget('platform_auth_id');
        session(['platform_2fa_verified' => true]);

        Audit::record('platform_login', null, [
            'super_admin_id' => $superAdmin->id,
            'email' => $superAdmin->email,
            'ip' => $request->ip(),
        ]);

        return redirect()->route('platform.dashboard')->with('success', 'Autentikasi dua langkah berhasil. Selamat datang di Platform.');
    }

    /**
     * Log out of the platform guard.
     */
    public function logout(Request $request): RedirectResponse
    {
        $superAdmin = Auth::guard('platform')->user();

        if ($superAdmin instanceof SuperAdmin) {
            Audit::record('platform_logout', null, [
                'super_admin_id' => $superAdmin->id,
                'email' => $superAdmin->email,
                'ip' => $request->ip(),
            ]);
        }

        Auth::guard('platform')->logout();
        session()->forget(['platform_auth_id', 'platform_2fa_verified']);

        return redirect()->route('platform.login')->with('success', 'Anda telah keluar dari sesi Super Admin Platform.');
    }
}
