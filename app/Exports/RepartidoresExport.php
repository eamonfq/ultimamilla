<?php

namespace App\Exports;

use App\Models\Repartidor;
use App\Settings\UltimamillaSettings;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RepartidoresExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(public bool $soloActivos = true) {}

    public function query()
    {
        $q = Repartidor::query()->orderBy('nombre');
        if ($this->soloActivos) {
            $q->activos();
        }

        return $q;
    }

    public function headings(): array
    {
        return ['Cedula', 'Nombre', 'Telefono', 'Ciudad', 'Placa', 'Cupo efectivo', 'Personalizado', 'Activo', 'Ultimo ingreso', 'Creado'];
    }

    public function map($repartidor): array
    {
        $settings = app(UltimamillaSettings::class);

        return [
            $repartidor->cedula,
            $repartidor->nombre,
            $repartidor->telefono ?? '',
            $repartidor->ciudad->label(),
            $repartidor->placa ?? '',
            $repartidor->cupoEfectivo($settings),
            $repartidor->cupo_personalizado !== null ? 'Si' : 'No',
            $repartidor->activo ? 'Activo' : 'Inactivo',
            $repartidor->last_login_at?->locale('es')->isoFormat('D MMM YYYY H:mm') ?? 'Nunca',
            $repartidor->created_at->locale('es')->isoFormat('D MMM YYYY'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4F46E5']]],
        ];
    }

    public function title(): string
    {
        return 'Repartidores';
    }
}
