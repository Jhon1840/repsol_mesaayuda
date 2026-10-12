<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Hardware', 'Software', 'Red / Internet', 'Acceso / Contraseña', 'Correo', 'Sistema interno', 'Impresoras', 'Otro'] as $name) {
            Category::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
