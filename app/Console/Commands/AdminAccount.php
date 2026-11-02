<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Signature('app:admin-account {username : Username used to sign in} {--name= : Display name for a new account} {--email= : Email for a new account}')]
#[Description('Create an administrator account, or reset the password of an existing one')]
class AdminAccount extends Command
{
    private const MIN_PASSWORD_LENGTH = 8;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $username = Str::lower($this->argument('username'));
        $user = User::where('username', $username)->first();

        if ($user !== null && ! $user->isAdmin()) {
            $this->error("Username {$username} is already used by a non-admin account.");

            return self::FAILURE;
        }

        $password = (string) $this->secret('Password');

        if (Str::length($password) < self::MIN_PASSWORD_LENGTH) {
            $this->error('Password must be at least '.self::MIN_PASSWORD_LENGTH.' characters.');

            return self::FAILURE;
        }

        if ($user !== null) {
            $user->update(['password' => Hash::make($password), 'is_active' => true, 'must_change_password' => false]);
            $this->info("Password reset for {$username}.");

            return self::SUCCESS;
        }

        User::create([
            'name' => $this->option('name') ?: 'Administrator',
            'username' => $username,
            'email' => $this->option('email') ?: "{$username}@smkbhaktiinsani.sch.id",
            'password' => Hash::make($password),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->info("Administrator {$username} created.");

        return self::SUCCESS;
    }
}
