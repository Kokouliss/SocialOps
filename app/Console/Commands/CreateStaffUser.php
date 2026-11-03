<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateStaffUser extends Command
{
    protected $signature = 'staff:create {name} {email}';

    protected $description = 'Create an agency staff login (there is no public registration)';

    public function handle(): int
    {
        if (User::where('email', $this->argument('email'))->exists()) {
            $this->error('A user with that email already exists.');

            return self::FAILURE;
        }

        $password = $this->secret('Password');

        if (strlen((string) $password) < 12) {
            $this->error('Password must be at least 12 characters.');

            return self::FAILURE;
        }

        User::create([
            'name' => $this->argument('name'),
            'email' => $this->argument('email'),
            'password' => Hash::make($password),
        ])->forceFill(['email_verified_at' => now()])->save();

        $this->info('Staff user created.');

        return self::SUCCESS;
    }
}
