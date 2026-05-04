<?php

namespace App\Exports;

use App\Models\Cruce;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CrucesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        public ?Carbon $desde = null,
        public ?Carbon $hasta = null,
    ) {}

    public function query()
    {
        $q = Cruce::query()
            ->with('repartidor')
            ->whereNotNull('repartidor_id')
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
        return ['Fecha', 'Cedula', 'Nombre', 'Reservado', 'Asignado', 'Entregado', 'Cumplimiento %', 'Patron'];
    }

    public function map($cruce): array
    {
        return [
            $cruce->fecha_operacion->format('Y-m-d'),
            $cruce->repartidor->cedula ?? '',
            $cruce->repartidor->nombre ?? '',
            $cruce->reservado,
            $cruce->asignado,
            $cruce->entregado,
            $cruce->cumplimiento_pct !== null ? number_format((float) $cruce->cumplimiento_pct, 1) : '',
            $cruce->patron?->label() ?? '',
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
        return 'Cruces';
    }
}
