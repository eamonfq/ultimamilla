<?php

namespace App\Models;

use App\Enums\EstadoReserva;
use App\Services\DeadlineChecker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reserva extends Model
{
    use HasFactory;

    protected $fillable = [
        'repartidor_id',
        'fecha_operacion',
        'paquetes',
        'rutas',
        'estado',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha_operacion' => 'date',
            'estado' => EstadoReserva::class,
            'locked_at' => 'datetime',
        ];
    }

    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(Repartidor::class);
    }

    public function asignacion(): HasOne
    {
        return $this->hasOne(Asignacion::class);
    }

    public function isLocked(): bool
    {
        if ($this->locked_at !== null) {
            return true;
        }

        if ($this->relationLoaded('asignacion') && $this->asignacion?->entregado_a_repartidor_at !== null) {
            return true;
        }

        return app(DeadlineChecker::class)->isReservaLocked($this);
    }

    public function scopeActiva(Builder $query): Builder
    {
        return $query->where('estado', EstadoReserva::Activa);
    }

    public function scopeParaFecha(Builder $query, $fecha): Builder
    {
        return $query->where('fecha_operacion', $fecha);
    }
}
