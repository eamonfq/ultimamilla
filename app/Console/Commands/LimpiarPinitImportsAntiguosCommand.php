<?php

namespace App\Console\Commands;

use App\Models\PinitImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class LimpiarPinitImportsAntiguosCommand extends Command
{
    protected $signature = 'ultimamilla:limpiar-imports {--dias=90 : Eliminar archivos fisicos de imports mas viejos que N dias}';

    protected $description = 'Elimina archivos fisicos de Pinit imports antiguos (mantiene los registros DB)';

    public function handle(): int
    {
        $dias = (int) $this->option('dias');
        $limite = now('America/Bogota')->subDays($dias);

        $imports = PinitImport::where('created_at', '<', $limite)
            ->where('archivo_path', '!=', '')
            ->get();

        $eliminados = 0;
        foreach ($imports as $import) {
            if (Storage::disk('pinit_imports')->exists($import->archivo_path)) {
                Storage::disk('pinit_imports')->delete($import->archivo_path);
                $eliminados++;
            }
            $import->update(['archivo_path' => '']);
        }

        $this->info("Eliminados {$eliminados} archivos fisicos de imports >= {$dias} dias.");

        return self::SUCCESS;
    }
}
