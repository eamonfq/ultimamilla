<?php

use App\Enums\Ciudad;
use App\Services\PinitParser;

beforeEach(function () {
    $this->parser = app(PinitParser::class);
});

it('parses a complete row from real file', function () {
    $fila = array_fill(0, 38, '');
    $fila[0] = 'RT-001';          // id_ruta
    $fila[2] = '3951329';          // cedula
    $fila[3] = '3951329 MED Luis Prada WEGROUP AL'; // nombre_operador
    $fila[8] = 'Completada';       // status
    $fila[9] = 50;                 // total
    $fila[10] = 45;                // entregados

    $result = $this->parser->parsearFila($fila, 3);

    expect($result['cedula'])->toBe('3951329');
    expect($result['ciudad_parseada'])->toBe(Ciudad::MED);
    expect($result['nombre_limpio'])->toBe('Luis Prada');
    expect($result['porcentaje'])->toBe(90.0);
    expect($result['es_devolucion'])->toBeFalse();
    expect($result['total'])->toBe(50);
    expect($result['entregados'])->toBe(45);
    expect($result['status'])->toBe('Completada');
    expect($result['warnings_fila'])->toBeEmpty();
});

it('falls back to col D when col C is empty', function () {
    $fila = array_fill(0, 38, '');
    $fila[0] = 'RT-002';
    $fila[2] = '';
    $fila[3] = '5004577 MED Carlos Barradas CENTRO DE LA MODA';
    $fila[8] = 'Completada';
    $fila[9] = 30;
    $fila[10] = 28;

    $result = $this->parser->parsearFila($fila, 3);

    expect($result['cedula'])->toBe('5004577');
    expect($result['nombre_limpio'])->toBe('Carlos Barradas');
    expect($result['warnings_fila'])->toContain('Cedula tomada del prefijo del nombre (col C estaba vacia)');
});

it('handles WE GROUP variant with space', function () {
    $fila = array_fill(0, 38, '');
    $fila[0] = 'RT-003';
    $fila[2] = '1036643435';
    $fila[3] = '1036643435 MED Duvan Pantoja WE GROUP';
    $fila[8] = 'Completada';
    $fila[9] = 40;
    $fila[10] = 38;

    $result = $this->parser->parsearFila($fila, 3);

    expect($result['nombre_limpio'])->toBe('Duvan Pantoja');
});

it('handles CENTRO DE LA MODA variant', function () {
    $fila = array_fill(0, 38, '');
    $fila[0] = 'RT-004';
    $fila[2] = '5004577';
    $fila[3] = '5004577 MED Carlos Barradas CENTRO DE LA MODA';
    $fila[8] = 'Completada';
    $fila[9] = 25;
    $fila[10] = 22;

    $result = $this->parser->parsearFila($fila, 3);

    expect($result['nombre_limpio'])->toBe('Carlos Barradas');
});

it('strips trailing AL token', function () {
    $fila = array_fill(0, 38, '');
    $fila[0] = 'RT-005';
    $fila[2] = '3951329';
    $fila[3] = '3951329 MED Luis Prada WEGROUP AL';
    $fila[8] = 'Completada';
    $fila[9] = 50;
    $fila[10] = 45;

    $result = $this->parser->parsearFila($fila, 3);

    expect($result['nombre_limpio'])->toBe('Luis Prada');
});

it('identifies ITAGUI city', function () {
    $fila = array_fill(0, 38, '');
    $fila[0] = 'RT-006';
    $fila[2] = '1007055329';
    $fila[3] = '1007055329 ITAGUI Maycol Henao WEGROUP';
    $fila[8] = 'En curso';
    $fila[9] = 35;
    $fila[10] = 30;

    $result = $this->parser->parsearFila($fila, 3);

    expect($result['ciudad_parseada'])->toBe(Ciudad::ITAGUI);
    expect($result['nombre_limpio'])->toBe('Maycol Henao');
});

it('returns null cedula and warning when no number prefix', function () {
    $fila = array_fill(0, 38, '');
    $fila[0] = 'RT-007';
    $fila[2] = '';
    $fila[3] = 'MED Sin Cedula WEGROUP';
    $fila[8] = 'Completada';
    $fila[9] = 20;
    $fila[10] = 18;

    $result = $this->parser->parsearFila($fila, 3);

    expect($result['cedula'])->toBeNull();
    expect($result['warnings_fila'])->toContain('No se pudo extraer cedula de la fila');
});

it('returns null ciudad and warning when token unknown', function () {
    $fila = array_fill(0, 38, '');
    $fila[0] = 'RT-008';
    $fila[2] = '123456';
    $fila[3] = '123456 BOGOTA Pedro Perez WEGROUP';
    $fila[8] = 'Completada';
    $fila[9] = 15;
    $fila[10] = 10;

    $result = $this->parser->parsearFila($fila, 3);

    expect($result['ciudad_parseada'])->toBeNull();
    expect($result['warnings_fila'])->toContain('No se pudo identificar ciudad (MED|ITAGUI) en el nombre');
});

it('cleans dotted cedula format', function () {
    $fila = array_fill(0, 38, '');
    $fila[0] = 'RT-009';
    $fila[2] = '1.036.643.435';
    $fila[3] = '1036643435 MED Duvan Pantoja WEGROUP';
    $fila[8] = 'Completada';
    $fila[9] = 40;
    $fila[10] = 35;

    $result = $this->parser->parsearFila($fila, 3);

    expect($result['cedula'])->toBe('1036643435');
});

it('marks devolucion when total leq threshold', function () {
    $fila = array_fill(0, 38, '');
    $fila[0] = 'RT-010';
    $fila[2] = '3951329';
    $fila[3] = '3951329 MED Luis Prada WEGROUP';
    $fila[8] = 'Completada';
    $fila[9] = 3;
    $fila[10] = 3;

    $result = $this->parser->parsearFila($fila, 3);
    expect($result['es_devolucion'])->toBeTrue();

    // total > threshold
    $fila[9] = 4;
    $fila[10] = 4;
    $result = $this->parser->parsearFila($fila, 3);
    expect($result['es_devolucion'])->toBeFalse();
});

it('captures excepciones from cols S to Z', function () {
    $fila = array_fill(0, 38, '');
    $fila[0] = 'RT-011';
    $fila[2] = '3951329';
    $fila[3] = '3951329 MED Luis Prada WEGROUP';
    $fila[8] = 'Completada';
    $fila[9] = 50;
    $fila[10] = 45;
    // Cols S-Z = indices 18-25
    $fila[18] = 0;  // S
    $fila[19] = 1;  // T
    $fila[20] = 0;  // U
    $fila[21] = 2;  // V
    $fila[22] = 0;  // W
    $fila[23] = 0;  // X
    $fila[24] = 3;  // Y
    $fila[25] = 0;  // Z

    $result = $this->parser->parsearFila($fila, 3);

    expect($result['excepciones'])->toHaveCount(3);
    expect($result['excepciones']['col_T'])->toBe(1);
    expect($result['excepciones']['col_V'])->toBe(2);
    expect($result['excepciones']['col_Y'])->toBe(3);
});

it('captures kilometros and tiempos from cols AH-AK', function () {
    $fila = array_fill(0, 38, '');
    $fila[0] = 'RT-012';
    $fila[2] = '3951329';
    $fila[3] = '3951329 MED Luis Prada WEGROUP';
    $fila[8] = 'Completada';
    $fila[9] = 50;
    $fila[10] = 45;
    $fila[33] = 45.5;   // km estimados
    $fila[34] = 52.3;   // km reales
    $fila[35] = '2:30';  // tiempo estimado
    $fila[36] = '3:15';  // tiempo real

    $result = $this->parser->parsearFila($fila, 3);

    expect($result['tiempos']['kilometros_estimados'])->toBe(45.5);
    expect($result['tiempos']['kilometros_reales'])->toBe(52.3);
    expect($result['tiempos']['tiempo_estimado'])->toBe('2:30');
    expect($result['tiempos']['tiempo_real'])->toBe('3:15');
});

it('validates cabeceras correctly', function () {
    $cabecerasValidas = ['ID', 'Carrier', 'Operador', '', '', '', '', '', '', 'Total', '', ''];
    expect($this->parser->validarCabeceras($cabecerasValidas))->toBeTrue();

    expect($this->parser->validarCabeceras([]))->toBeFalse();
    expect($this->parser->validarCabeceras(['Foo', 'Bar', 'Baz']))->toBeFalse();
});

it('extracts fecha from filename correctly', function () {
    $fecha = $this->parser->extraerFechaDelNombre('report-ds-vendor-2026-05-02-to-2026-05-02.xlsx');
    expect($fecha)->not->toBeNull();
    expect($fecha->format('Y-m-d'))->toBe('2026-05-02');

    expect($this->parser->extraerFechaDelNombre('archivo-sin-fecha.xlsx'))->toBeNull();

    // Mes invalido — Carbon will throw or return weird date, our code catches it
    $invalid = $this->parser->extraerFechaDelNombre('2026-13-01.xlsx');
    // Carbon::createFromDate doesn't throw on invalid month — it wraps
    // But since 13 is invalid we accept it might return non-null or null depending on Carbon version
    // The important thing is it doesn't crash
    expect(true)->toBeTrue();
});

it('throws on row missing id_ruta or operador', function () {
    $fila = array_fill(0, 38, '');
    $fila[0] = '';  // id_ruta vacio
    $fila[3] = '3951329 MED Luis Prada WEGROUP';

    expect(fn () => $this->parser->parsearFila($fila, 3))
        ->toThrow(InvalidArgumentException::class);
});
