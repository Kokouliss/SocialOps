<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

/** Staff logins. The first one is made on /setup (only while there are no users), the rest on the Staff page. */
class StaffController extends Controller
{
    public function index()
    {
        return view('staff.index', ['users' => User::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $this->create($request);

        return redirect()->route('staff.index')->with('status', 'login-added');
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
        ]));
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
