<?php

namespace App\Http\Controllers\Platform;

use App\Actions\CreateOrganizationAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformOrganizationController extends Controller
{
    /**
     * Show the form for creating a new organization.
     */
    public function create(): View
    {
        return view('platform.organizations.create');
    }

    /**
     * Store a newly created organization in storage.
     */
    public function store(Request $request, CreateOrganizationAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:100', 'unique:organizations,slug'],
            'leader_label' => ['nullable', 'string', 'max:50'],
            'address_text' => ['nullable', 'string', 'max:500'],
            'plan' => ['required', 'string', 'in:internal,free,paid'],
            'quota_minutes' => ['required', 'integer', 'min:0', 'max:1000000'],

            // Admin initial account
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['nullable', 'string', 'min:6'],

            // Optional Leader initial account
            'leader_name' => ['nullable', 'string', 'max:255'],
            'leader_email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'leader_password' => ['nullable', 'string', 'min:6'],
        ], [
            'name.required' => 'Nama lembaga wajib diisi.',
            'admin_name.required' => 'Nama administrator lembaga wajib diisi.',
            'admin_email.required' => 'Email administrator wajib diisi.',
            'admin_email.email' => 'Format email administrator tidak valid.',
            'admin_email.unique' => 'Email administrator ini sudah digunakan di sistem.',
            'leader_email.unique' => 'Email pimpinan ini sudah digunakan di sistem.',
            'admin_password.min' => 'Kata sandi minimal 6 karakter.',
            'leader_password.min' => 'Kata sandi minimal 6 karakter.',
        ]);

        $result = $action->execute($validated);

        return redirect()->route('platform.dashboard')->with('created_credentials', [
            'organization_id' => $result['organization']->id,
            'organization_name' => $result['organization']->name,
            'organization_slug' => $result['organization']->slug,
            'plan' => $result['organization']->plan,
            'quota_minutes' => $result['organization']->quota_minutes,
            'admin_name' => $result['admin']->name,
            'admin_email' => $result['admin']->email,
            'admin_password' => $result['raw_admin_password'],
            'leader_name' => $result['leader']?->name,
            'leader_email' => $result['leader']?->email,
            'leader_password' => $result['raw_leader_password'],
            'login_url' => url('/login'),
        ]);
    }
}
