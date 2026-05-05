<?php

namespace App\Http\Controllers;

use App\Enums\StatusImport;
use App\Jobs\ProcesarPinitImport;
use App\Models\PinitImport;
use App\Services\PinitParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class PinitImportController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'archivo' => ['required', 'file', 'max:25600'],
        ]);

        $file = $request->file('archivo');
        $nombreOriginal = $file->getClientOriginalName();
        $archivoPath = 'uploads/'.$nombreOriginal;

        // Guardar archivo en disco pinit_imports
        Storage::disk('pinit_imports')->put($archivoPath, file_get_contents($file->getRealPath()));

        $parser = app(PinitParser::class);

        // Extraer fecha del nombre
        $fechaArchivo = $parser->extraerFechaDelNombre($nombreOriginal);

        if (! $fechaArchivo) {
            Storage::disk('pinit_imports')->delete($archivoPath);

            return back()->with('error', 'El archivo debe tener formato YYYY-MM-DD en el nombre, como "report-ds-...-2026-05-02-to-2026-05-02.xlsx".');
        }

        // Validar cabeceras
        $rutaCompleta = Storage::disk('pinit_imports')->path($archivoPath);
        try {
            $primerasFilas = Excel::toArray([], $rutaCompleta)[0] ?? [];
            if (empty($primerasFilas) || ! $parser->validarCabeceras($primerasFilas[0])) {
                throw new \RuntimeException('El archivo no parece ser un export valido de Pinit.');
            }
        } catch (\Throwable $e) {
            Storage::disk('pinit_imports')->delete($archivoPath);

            return back()->with('error', $e->getMessage());
        }

        // Verificar si hay un import previo done para esa fecha → marcar superseded
        $previo = PinitImport::query()
            ->whereDate('fecha_archivo', $fechaArchivo)
            ->where('status', StatusImport::Done)
            ->latest()
            ->first();

        if ($previo) {
            $previo->update(['status' => StatusImport::Superseded]);
            activity()
                ->performedOn($previo)
                ->causedBy(auth()->user())
                ->withProperties(['fecha_archivo' => $fechaArchivo->toDateString()])
                ->log('pinit_import.replaced');
        }

        // Verificar que NO haya pending/processing previo para esa fecha
        $enCurso = PinitImport::query()
            ->whereDate('fecha_archivo', $fechaArchivo)
            ->whereIn('status', [StatusImport::Pending, StatusImport::Processing])
            ->exists();

        if ($enCurso) {
            Storage::disk('pinit_imports')->delete($archivoPath);

            return back()->with('error', 'Ya hay un import procesandose para esta fecha. Espera a que termine.');
        }

        // Crear el import en pending
        $import = PinitImport::create([
            'archivo_path' => $archivoPath,
            'fecha_archivo' => $fechaArchivo,
            'total_filas' => 0,
            'status' => StatusImport::Pending,
            'importado_por' => auth()->id(),
        ]);

        // Disparar job
        ProcesarPinitImport::dispatch($import->id);

        return back()->with('success', "Archivo recibido. Procesando para fecha {$fechaArchivo->locale('es')->isoFormat('D MMM YYYY')}.");
    }
}
