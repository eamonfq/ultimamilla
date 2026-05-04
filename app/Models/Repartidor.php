<?php

namespace App\Models;

use App\Enums\Ciudad;
use App\Settings\UltimamillaSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Repartidor extends Authenticatable
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'repartidores';

    protected $fillable = [
        'cedula',
        'nombre',
        'telefono',
        'ciudad',
        'placa',
        'pin',
        'cupo_personalizado',
        'activo',
        'last_login_at',
    ];

    protected $hidden = [
        'pin',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'ciudad' => Ciudad::class,
            'activo' => 'boolean',
            'last_login_at' => 'datetime',
            'cupo_personalizado' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept(['pin', 'remember_token', 'last_login_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function setPinAttribute(string $value): void
    {
        $this->attributes['pin'] = str_starts_with($value, '$2y$')
            ? $value
            : Hash::make($value);
    }

    public function getAuthPassword(): string
    {
        return $this->pin;
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class);
    }

    public function cruces(): HasMany
    {
        return $this->hasMany(Cruce::class);
    }

    public function cupoEfectivo(UltimamillaSettings $settings): int
    {
        return $this->cupo_personalizado ?? $settings->cupo_global_default;
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
