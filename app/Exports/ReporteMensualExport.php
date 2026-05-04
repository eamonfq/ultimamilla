<?php

namespace App\Exports;

use App\Models\Repartidor;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ReporteMensualExport implements WithMultipleSheets
{
    public function __construct(public Carbon $mes) {}

    public function sheets(): array
    {
        $inicio = $this->mes->copy()->startOfMonth();
        $fin = $this->mes->copy()->endOfMonth();

        $sheets = [new ReporteMensualResumenSheet($inicio, $fin)];

        $repartidoresConCruces = Repartidor::query()
            ->whereHas('cruces', fn ($q) => $q->whereBetween('fecha_operacion', [$inicio, $fin]))
            ->orderBy('nombre')
            ->get();

        foreach ($repartidoresConCruces as $repartidor) {
            $sheets[] = new ReporteMensualRepartidorSheet($repartidor, $inicio, $fin);
        }

        return $sheets;
    }
}
