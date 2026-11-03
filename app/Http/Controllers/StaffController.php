<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Staff logins. The first one is made on /setup (only while there are no users), the rest on the Staff page. */
class StaffController extends Controller
{
    public function index()
    {
        return view('staff.index', ['users' => User::orderBy('name')->get(), 'clients' => Client::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $this->create($request);

        return redirect()->route('staff.index')->with('status', 'login-added');
    }

    /** Limit a login to some clients, or lift the limit (no client ticked). */
    public function update(Request $request, User $user)
    {
        abort_if($user->is($request->user()), 422, 'You cannot limit your own login.');
        $user->update(['client_ids' => $this->clientIds($request)]);

        return redirect()->route('staff.index')->with('status', 'login-updated');
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($user->is($request->user()), 422, 'You cannot remove your own login.');
        $user->delete();

        return redirect()->route('staff.index')->with('status', 'login-removed');
    }

    public function setup()
    {
        abort_unless(User::count() === 0, 404);

        return view('staff.setup');
    }

    public function finishSetup(Request $request)
    {
        abort_unless(User::count() === 0, 404);
        auth()->login($this->create($request));

        return redirect()->route('dashboard');
    }

    private function create(Request $request): User
    {
        $user = User::create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:12'],
        ]) + ['client_ids' => $this->clientIds($request)]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    /** The ticked client ids, or null for every client. */
    private function clientIds(Request $request): ?array
    {
        $ids = $request->validate(['client_ids' => ['nullable', 'array'], 'client_ids.*' => ['integer', Rule::exists('clients', 'id')]])['client_ids'] ?? [];

        return $ids ? array_map('intval', array_values($ids)) : null;
    }
}
