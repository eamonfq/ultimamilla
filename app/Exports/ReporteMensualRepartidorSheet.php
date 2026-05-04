<?php

namespace App\Exports;

use App\Models\Cruce;
use App\Models\Repartidor;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReporteMensualRepartidorSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        public Repartidor $repartidor,
        public Carbon $inicio,
        public Carbon $fin,
    ) {}

    public function collection()
    {
        return Cruce::query()
            ->where('repartidor_id', $this->repartidor->id)
            ->whereBetween('fecha_operacion', [$this->inicio, $this->fin])
            ->orderBy('fecha_operacion')
            ->get()
            ->map(fn ($c) => [
                $c->fecha_operacion->format('Y-m-d'),
                $c->fecha_operacion->locale('es')->isoFormat('dddd'),
                $c->reservado,
                $c->asignado,
                $c->entregado,
                $c->cumplimiento_pct ?? '',
                $c->patron?->label() ?? '',
            ]);
    }

    public function headings(): array
    {
        return ['Fecha', 'Dia', 'Reservado', 'Asignado', 'Entregado', 'Cumplimiento %', 'Patron'];
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
        $titulo = preg_replace('/[\\\\\/\?\*\[\]:]/', '', $this->repartidor->nombre);

        return mb_substr($titulo, 0, 31);
    }
}
