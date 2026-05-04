<?php

namespace Database\Seeders;

use App\Enums\Ciudad;
use App\Models\Repartidor;
use Illuminate\Database\Seeder;

class RepartidoresSeeder extends Seeder
{
    public function run(): void
    {
        $repartidores = [
            ['cedula' => '3951329', 'nombre' => 'Luis Prada', 'ciudad' => Ciudad::MED, 'pin' => '1234'],
            ['cedula' => '1036643435', 'nombre' => 'Duván Pantoja', 'ciudad' => Ciudad::MED, 'pin' => '5678'],
            ['cedula' => '1007055329', 'nombre' => 'Maycol Henao', 'ciudad' => Ciudad::ITAGUI, 'pin' => '9012'],
        ];

        foreach ($repartidores as $data) {
            Repartidor::updateOrCreate(
                ['cedula' => $data['cedula']],
                [
                    'nombre' => $data['nombre'],
                    'ciudad' => $data['ciudad'],
                    'pin' => $data['pin'],
                    'activo' => true,
                ]
            );
        }

        $this->command->table(
            ['Cédula', 'Nombre', 'PIN'],
            [
                ['3951329', 'Luis Prada', '1234'],
                ['1036643435', 'Duván Pantoja', '5678'],
                ['1007055329', 'Maycol Henao', '9012'],
            ]
        );
    }
}
