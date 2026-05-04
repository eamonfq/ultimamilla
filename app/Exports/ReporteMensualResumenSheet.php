<?php

namespace App\Exports;

use App\Enums\PatronCruce;
use App\Models\Cruce;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReporteMensualResumenSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(public Carbon $inicio, public Carbon $fin) {}

    public function collection()
    {
        return Cruce::query()
            ->with('repartidor')
            ->whereBetween('fecha_operacion', [$this->inicio, $this->fin])
            ->whereNotNull('repartidor_id')
            ->get()
            ->groupBy('repartidor_id')
            ->map(function ($cruces) {
                $rep = $cruces->first()->repartidor;
                $consistentes = $cruces->where('patron', PatronCruce::Consistente)->count();
                $sub = $cruces->where('patron', PatronCruce::SubReserva)->count();
                $sobre = $cruces->where('patron', PatronCruce::SobreReserva)->count();

                return [
                    $rep->cedula,
                    $rep->nombre,
                    $rep->ciudad->label(),
                    $cruces->count(),
                    $cruces->sum('reservado'),
                    $cruces->sum('asignado'),
                    $cruces->sum('entregado'),
                    round($cruces->whereNotNull('cumplimiento_pct')->avg('cumplimiento_pct') ?? 0, 1),
                    $consistentes,
                    $sub,
                    $sobre,
                ];
            })
            ->values();
    }

    public function headings(): array
    {
        return [
            'Cedula', 'Nombre', 'Ciudad', 'Dias operados',
            'Reservado total', 'Asignado total', 'Entregado total',
            'Cumplimiento %', 'Dias consistente', 'Dias sub-reserva', 'Dias sobre-reserva',
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
        return 'Resumen '.$this->inicio->locale('es')->isoFormat('MMM YYYY');
    }
}
