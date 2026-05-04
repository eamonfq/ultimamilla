<?php

namespace App\Models;

use App\Enums\Ciudad;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PinitRuta extends Model
{
    protected $table = 'pinit_rutas';

    protected $fillable = [
        'pinit_import_id',
        'id_ruta_pinit',
        'cedula',
        'nombre_operador',
        'ciudad_parseada',
        'placa',
        'repartidor_id',
        'status',
        'total',
        'entregados',
        'porcentaje',
        'excepciones',
        'tiempos',
        'performance',
        'es_devolucion',
    ];

    protected function casts(): array
    {
        return [
            'ciudad_parseada' => Ciudad::class,
            'excepciones' => 'array',
            'tiempos' => 'array',
            'performance' => 'array',
            'es_devolucion' => 'boolean',
            'porcentaje' => 'decimal:2',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(PinitImport::class, 'pinit_import_id');
    }

    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(Repartidor::class);
    }
}
