<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('ultimamilla.cupo_global_default', 30);
        $this->migrator->add('ultimamilla.hora_cierre_reservas', '12:00');
        $this->migrator->add('ultimamilla.umbral_minimo_diario', 0);
        $this->migrator->add('ultimamilla.dias_operativos', [1, 2, 3, 4, 5, 6]);
        $this->migrator->add('ultimamilla.devolucion_threshold', 3);
        $this->migrator->add('ultimamilla.cumplimiento_sobre_reserva_max', 0.80);
        $this->migrator->add('ultimamilla.cumplimiento_consistente_min', 0.90);
        $this->migrator->add('ultimamilla.cumplimiento_sub_reserva_cupo_max', 0.70);
    }
};
