<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asignacion extends Model
{
    protected $table = 'asignaciones';

    protected $fillable = [
        'reserva_id',
        'paquetes_asignados',
        'ajuste_motivo',
        'entregado_a_repartidor_at',
        'ajustado_por',
    ];

    protected function casts(): array
    {
        return [
            'entregado_a_repartidor_at' => 'datetime',
        ];
    }

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }

    public function ajustadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ajustado_por');
    }
}
