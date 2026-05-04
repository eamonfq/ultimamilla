<?php

return [
    'pinit' => [
        'sufijos_marca' => [
            'WEGROUP AL',
            'CENTRO DE LA MODA',
            'WE GROUP',
            'WEGROUP',
        ],
        'columnas_esperadas' => 38,
        'cabeceras_validacion' => [
            'ID',
            'Carrier',
            'Operador',
            'Total',
        ],
        'chunk_size' => 500,
        'mapeo_columnas' => [
            'id_ruta' => 0,
            'carrier' => 1,
            'cedula' => 2,
            'nombre_operador' => 3,
            'almacen' => 6,
            'status' => 8,
            'total' => 9,
            'entregados' => 10,
            'excepciones_inicio' => 18,
            'excepciones_fin' => 25,
            'total_excepciones' => 26,
            'reintentos_inicio' => 28,
            'reintentos_fin' => 31,
            'kilometros_estimados' => 33,
            'kilometros_reales' => 34,
            'tiempo_estimado' => 35,
            'tiempo_real' => 36,
        ],
    ],
];
