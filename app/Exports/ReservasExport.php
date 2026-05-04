<?php

namespace App\Exports;

use App\Models\Reserva;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReservasExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        public ?Carbon $desde = null,
        public ?Carbon $hasta = null,
    ) {}

    public function query()
    {
        $q = Reserva::query()
            ->with(['repartidor', 'asignacion'])
            ->orderBy('fecha_operacion', 'desc')
            ->orderBy('repartidor_id');

        if ($this->desde) {
            $q->where('fecha_operacion', '>=', $this->desde);
        }
        if ($this->hasta) {
            $q->where('fecha_operacion', '<=', $this->hasta);
        }

        return $q;
    }

    public function headings(): array
    {
        return ['Fecha', 'Cedula', 'Repartidor', 'Ciudad', 'Reservado', 'Rutas', 'Asignado', 'Entregado al rep.', 'Estado', 'Cerrada'];
    }

    public function map($reserva): array
    {
        $asig = $reserva->asignacion;

        return [
            $reserva->fecha_operacion->format('Y-m-d'),
            $reserva->repartidor->cedula,
            $reserva->repartidor->nombre,
            $reserva->repartidor->ciudad->label(),
            $reserva->paquetes,
            $reserva->rutas,
            $asig?->paquetes_asignados ?? '',
            $asig?->entregado_a_repartidor_at?->format('H:i') ?? '',
            $reserva->estado->label(),
            $reserva->locked_at !== null ? 'Si' : 'No',
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
        return 'Reservas';
    }
}
