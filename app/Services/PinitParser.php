<?php

namespace App\Services;

use App\Enums\Ciudad;
use Carbon\Carbon;

class PinitParser
{
    /**
     * Parsea una fila del archivo Pinit.
     *
     * @param  array  $fila  Array de valores de la fila (0-indexed por columna).
     * @return array{
     *     id_ruta_pinit: string,
     *     cedula: ?string,
     *     nombre_operador: string,
     *     ciudad_parseada: ?Ciudad,
     *     nombre_limpio: ?string,
     *     placa: ?string,
     *     status: string,
     *     total: int,
     *     entregados: int,
     *     porcentaje: float,
     *     excepciones: array,
     *     tiempos: array,
     *     performance: array,
     *     es_devolucion: bool,
     *     warnings_fila: array
     * }
     */
    public function parsearFila(array $fila, int $devolucionThreshold): array
    {
        $mapeo = config('reservas.pinit.mapeo_columnas');
        $sufijos = config('reservas.pinit.sufijos_marca');

        $idRuta = trim((string) ($fila[$mapeo['id_ruta']] ?? ''));
        $nombreOperador = trim((string) ($fila[$mapeo['nombre_operador']] ?? ''));

        if ($idRuta === '' || $nombreOperador === '') {
            throw new \InvalidArgumentException('Fila sin ID de ruta o sin nombre de operador');
        }

        $warnings = [];

        // 1. Cedula: col C primaria, fallback a prefijo numerico de col D
        $cedulaRaw = trim((string) ($fila[$mapeo['cedula']] ?? ''));
        $cedula = $this->extraerCedula($cedulaRaw, $nombreOperador, $warnings);

        // 2. Ciudad: del segundo token de col D
        $ciudad = $this->extraerCiudad($nombreOperador, $warnings);

        // 3. Nombre limpio: remover prefijo numerico, ciudad y sufijos de marca
        $nombreLimpio = $this->limpiarNombre($nombreOperador, $sufijos);

        // 4. Status, total, entregados
        $status = trim((string) ($fila[$mapeo['status']] ?? ''));
        $total = (int) ($fila[$mapeo['total']] ?? 0);
        $entregados = (int) ($fila[$mapeo['entregados']] ?? 0);
        $porcentaje = $total > 0 ? round(($entregados / $total) * 100, 2) : 0.0;

        // 5. Excepciones (cols S-Z = indices 18-25)
        $excepciones = [];
        for ($i = $mapeo['excepciones_inicio']; $i <= $mapeo['excepciones_fin']; $i++) {
            $valor = (int) ($fila[$i] ?? 0);
            if ($valor > 0) {
                $excepciones['col_'.$this->columnaLetra($i)] = $valor;
            }
        }

        // 6. Reintentos (cols AC-AF = indices 28-31)
        $reintentos = [];
        for ($i = $mapeo['reintentos_inicio']; $i <= $mapeo['reintentos_fin']; $i++) {
            $valor = (int) ($fila[$i] ?? 0);
            if ($valor > 0) {
                $reintentos['intento_'.($i - $mapeo['reintentos_inicio'] + 1)] = $valor;
            }
        }

        // 7. Tiempos y kilometros (cols AH-AK)
        $tiempos = [
            'kilometros_estimados' => (float) ($fila[$mapeo['kilometros_estimados']] ?? 0),
            'kilometros_reales' => (float) ($fila[$mapeo['kilometros_reales']] ?? 0),
            'tiempo_estimado' => (string) ($fila[$mapeo['tiempo_estimado']] ?? ''),
            'tiempo_real' => (string) ($fila[$mapeo['tiempo_real']] ?? ''),
        ];

        $performance = [
            'reintentos' => $reintentos,
            'total_excepciones' => (int) ($fila[$mapeo['total_excepciones']] ?? 0),
        ];

        // 8. Devolucion
        $esDevolucion = $total <= $devolucionThreshold;

        return [
            'id_ruta_pinit' => $idRuta,
            'cedula' => $cedula,
            'nombre_operador' => $nombreOperador,
            'ciudad_parseada' => $ciudad,
            'nombre_limpio' => $nombreLimpio,
            'placa' => null,
            'status' => $status,
            'total' => $total,
            'entregados' => $entregados,
            'porcentaje' => $porcentaje,
            'excepciones' => $excepciones,
            'tiempos' => $tiempos,
            'performance' => $performance,
            'es_devolucion' => $esDevolucion,
            'warnings_fila' => $warnings,
        ];
    }

    private function extraerCedula(string $cedulaRaw, string $nombreOperador, array &$warnings): ?string
    {
        // Limpiar puntos, comas, espacios (formatos tipicos de Excel: "1.036.643.435")
        $cedulaLimpia = preg_replace('/[^a-zA-Z0-9]/', '', $cedulaRaw);

        if ($cedulaLimpia !== '' && strlen($cedulaLimpia) >= 6) {
            return $cedulaLimpia;
        }

        // Fallback: extraer del prefijo de col D
        if (preg_match('/^(\d{6,15})\s+/', $nombreOperador, $matches)) {
            $warnings[] = 'Cedula tomada del prefijo del nombre (col C estaba vacia)';

            return $matches[1];
        }

        $warnings[] = 'No se pudo extraer cedula de la fila';

        return null;
    }

    private function extraerCiudad(string $nombreOperador, array &$warnings): ?Ciudad
    {
        if (preg_match('/^\d+\s+(MED|ITAGUI)\b/i', $nombreOperador, $matches)) {
            return Ciudad::from(strtoupper($matches[1]));
        }

        $warnings[] = 'No se pudo identificar ciudad (MED|ITAGUI) en el nombre';

        return null;
    }

    private function limpiarNombre(string $nombreOperador, array $sufijos): string
    {
        // Quitar prefijo numerico y ciudad
        $sinPrefijo = preg_replace('/^\d+\s+(MED|ITAGUI)\s+/i', '', $nombreOperador);

        // Quitar sufijos de marca (orden importa: mas especificos primero)
        $limpio = $sinPrefijo;
        foreach ($sufijos as $sufijo) {
            $limpio = preg_replace('/\s+'.preg_quote($sufijo, '/').'\s*$/i', '', $limpio);
        }

        // Quitar token suelto "AL" al final
        $limpio = preg_replace('/\s+AL\s*$/i', '', $limpio);

        return trim($limpio);
    }

    private function columnaLetra(int $index): string
    {
        $letra = '';
        $n = $index;
        while ($n >= 0) {
            $letra = chr(65 + ($n % 26)).$letra;
            $n = intdiv($n, 26) - 1;
        }

        return $letra;
    }

    /**
     * Valida que el archivo tenga las cabeceras esperadas en la primera fila.
     */
    public function validarCabeceras(array $primeraFila): bool
    {
        $cabecerasEsperadas = config('reservas.pinit.cabeceras_validacion');
        $textoFila = strtolower(implode(' ', array_map('strval', $primeraFila)));

        foreach ($cabecerasEsperadas as $cabecera) {
            if (! str_contains($textoFila, strtolower($cabecera))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Extrae fecha del nombre de archivo: "report-ds-...-YYYY-MM-DD-to-YYYY-MM-DD.xlsx"
     */
    public function extraerFechaDelNombre(string $nombreArchivo): ?Carbon
    {
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})(?:-to-\d{4}-\d{2}-\d{2})?\.xlsx?$/i', $nombreArchivo, $matches)) {
            try {
                return Carbon::createFromDate((int) $matches[1], (int) $matches[2], (int) $matches[3], 'America/Bogota')->startOfDay();
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }
}
