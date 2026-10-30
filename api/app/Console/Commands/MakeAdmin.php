<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/** Адміністратор з ADMIN_EMAIL / ADMIN_PASSWORD: створюється один раз, пароль потім не змінюється */
class MakeAdmin extends Command
{
    protected $signature = 'site:admin {email} {--password=}';

    protected $description = 'Створити адміністратора (якщо його ще немає)';

    public function handle(): int
    {
        $email = $this->argument('email');
        if (User::where('email', $email)->exists()) {
            $this->info("$email вже є");

            return self::SUCCESS;
        }
        $password = $this->option('password') ?: env('ADMIN_PASSWORD');
        if (blank($password)) {
            $this->warn('ADMIN_PASSWORD не задано, адміністратора не створено');

            return self::SUCCESS;
        }
        User::create(['name' => 'Адміністратор', 'email' => $email, 'password' => $password, 'role' => 'admin']);
        $this->info("Створено $email");

        return self::SUCCESS;
    }
}
