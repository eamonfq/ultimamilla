<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@ultimamilla.test'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('password'),
            ]
        );

        $this->command->info('Admin creado: admin@ultimamilla.test / password');
    }
}
