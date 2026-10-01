<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create {email} {--super-admin : Create a super administrator}';

    protected $description = 'Create an administrator with a hidden password prompt; MFA enrollment is required at first login';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Run this command interactively so the password is never passed as an argument.');

            return self::FAILURE;
        }

        $data = [
            'email' => mb_strtolower(trim((string) $this->argument('email'))),
            'name' => $this->ask('Name'),
            'password' => $this->secret('Password (at least 12 characters)'),
            'password_confirmation' => $this->secret('Confirm password'),
        ];
        $validator = Validator::make($data, [
            'email' => ['required', 'email', 'max:255', Rule::unique('users')],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols(), 'max:72'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new User;
        $user->fill(collect($data)->only(['name', 'email', 'password'])->all());
        $user->role = $this->option('super-admin') ? User::ROLE_SUPER_ADMIN : User::ROLE_ADMIN;
        $user->save();

        $this->info('Administrator created. Complete authenticator enrollment at the first login.');

        return self::SUCCESS;
    }
}
