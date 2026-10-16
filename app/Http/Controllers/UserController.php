<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Display a listing of the users within the tenant organization.
     */
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()->latest()->get();

        return view('users.index', [
            'users' => $users,
        ]);
    }

    /**
     * Show the form for creating a new notulis user account.
     */
    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('users.create');
    }

    /**
     * Store a newly created notulis user in the database.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Role is strictly forced to 'notulis'. Admin/pimpinan accounts can only be created via CLI command.
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'notulis',
            'is_active' => true,
        ]);

        Audit::record('user_create', meta: [
            'created_user_id' => $user->id,
            'role' => 'notulis',
        ]);

        return redirect()->route('users.index')->with('success', 'Akun notulis berhasil dibuat.');
    }
}
