<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateSupport extends Command
{
    protected $signature = 'helpdesk:create-support';

    protected $description = 'Crear una cuenta interna de soporte';

    public function handle(): int
    {
        $data = ['name' => $this->ask('Nombre completo'), 'email' => $this->ask('Correo electrónico'), 'password' => $this->secret('Contraseña (10 caracteres, letras y números)')];
        $validator = Validator::make($data, ['name' => 'required|string|max:100', 'email' => 'required|email|unique:users|max:180', 'password' => ['required', Password::min(10)->letters()->numbers()]]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        User::create([...$data, 'role' => 'support', 'is_active' => true]);
        $this->info('Cuenta de soporte creada.');

        return self::SUCCESS;
    }
}
