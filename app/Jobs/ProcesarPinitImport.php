<?php

namespace App\Jobs;

use App\Enums\StatusImport;
use App\Models\PinitImport;
use App\Models\PinitRuta;
use App\Models\Repartidor;
use App\Services\CruceCalculator;
use App\Services\PinitParser;
use App\Settings\UltimamillaSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ProcesarPinitImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public int $importId) {}

    public function viaQueue(): string
    {
        return 'imports';
    }

    public function handle(PinitParser $parser, CruceCalculator $calculator, UltimamillaSettings $settings): void
    {
        $import = PinitImport::findOrFail($this->importId);

        if ($import->status !== StatusImport::Pending) {
            Log::warning("Import {$import->id} no esta en pending (esta en {$import->status->value}). Skipping.");

            return;
        }

        $import->update(['status' => StatusImport::Processing]);

        try {
            $rutasParaInsertar = [];
            $errores = [];
            $warnings = [
                'cedulas_no_encontradas' => [],
                'rutas_sin_cedula' => 0,
                'rutas_sin_ciudad' => 0,
                'detalle' => [],
            ];

            $contadorFilas = 0;

            // Cargar mapa de cedula → repartidor_id una sola vez
            $cedulasConocidas = Repartidor::pluck('id', 'cedula')->all();

            // Leer el archivo
            $rutaArchivo = Storage::disk('pinit_imports')->path($import->archivo_path);
            $rows = Excel::toArray([], $rutaArchivo)[0] ?? [];

            if (count($rows) < 2) {
                throw new \RuntimeException('El archivo no contiene filas de datos');
            }

            // Validar cabeceras
            if (! $parser->validarCabeceras($rows[0])) {
                throw new \RuntimeException('El archivo no parece ser un export valido de Pinit (cabeceras no coinciden)');
            }

            // Procesar filas de datos (saltar fila 0 que es cabecera)
            for ($i = 1; $i < count($rows); $i++) {
                $contadorFilas++;
                $fila = $rows[$i];

                // Saltear filas vacias
                if (empty(array_filter($fila, fn ($v) => $v !== null && $v !== ''))) {
                    continue;
                }

                try {
                    $datos = $parser->parsearFila($fila, $settings->devolucion_threshold);
                } catch (\InvalidArgumentException $e) {
                    $errores[] = 'Fila '.($i + 1).': '.$e->getMessage();

                    continue;
                }

                // Resolver repartidor_id
                $repartidorId = null;
                if ($datos['cedula'] !== null) {
                    $repartidorId = $cedulasConocidas[$datos['cedula']] ?? null;
                    if ($repartidorId === null) {
                        if (! in_array($datos['cedula'], $warnings['cedulas_no_encontradas'], true)) {
                            $warnings['cedulas_no_encontradas'][] = $datos['cedula'];
                        }
                    }
                } else {
                    $warnings['rutas_sin_cedula']++;
                }

                if ($datos['ciudad_parseada'] === null) {
                    $warnings['rutas_sin_ciudad']++;
                }

                if (! empty($datos['warnings_fila'])) {
                    $warnings['detalle'][] = [
                        'fila' => $i + 1,
                        'mensajes' => $datos['warnings_fila'],
                    ];
                }

                $rutasParaInsertar[] = [
                    'pinit_import_id' => $import->id,
                    'id_ruta_pinit' => $datos['id_ruta_pinit'],
                    'cedula' => $datos['cedula'] ?? '',
                    'nombre_operador' => $datos['nombre_operador'],
                    'ciudad_parseada' => $datos['ciudad_parseada']?->value,
                    'placa' => $datos['placa'],
                    'repartidor_id' => $repartidorId,
                    'status' => $datos['status'],
                    'total' => $datos['total'],
                    'entregados' => $datos['entregados'],
                    'porcentaje' => $datos['porcentaje'],
                    'excepciones' => json_encode($datos['excepciones']),
                    'tiempos' => json_encode($datos['tiempos']),
                    'performance' => json_encode($datos['performance']),
                    'es_devolucion' => $datos['es_devolucion'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Insertar en chunks
                if (count($rutasParaInsertar) >= 500) {
                    PinitRuta::insert($rutasParaInsertar);
                    $rutasParaInsertar = [];
                }
            }

            // Insertar lo que quede
            if (! empty($rutasParaInsertar)) {
                PinitRuta::insert($rutasParaInsertar);
            }

            // Calcular cruces
            $cantidadCruces = $calculator->calcularParaImport($import);

            // Marcar como done con resumen
            $import->update([
                'status' => StatusImport::Done,
                'total_filas' => $contadorFilas,
                'errores' => $errores,
                'warnings' => $warnings,
            ]);

            Log::info("Import {$import->id} procesado: {$contadorFilas} filas, {$cantidadCruces} cruces.");

        } catch (\Throwable $e) {
            $import->update([
                'status' => StatusImport::Failed,
                'errores' => [
                    'mensaje' => $e->getMessage(),
                    'archivo' => $e->getFile(),
                    'linea' => $e->getLine(),
                ],
            ]);

            // Limpiar rutas parciales
            $import->rutas()->delete();

            Log::error("Import {$import->id} fallo: ".$e->getMessage(), ['exception' => $e]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $import = PinitImport::find($this->importId);
        if ($import && $import->status !== StatusImport::Failed) {
            $import->update([
                'status' => StatusImport::Failed,
                'errores' => ['mensaje' => $exception->getMessage()],
            ]);
        }
    }
}
