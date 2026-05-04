<?php

namespace App\Models;

use App\Enums\StatusImport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PinitImport extends Model
{
    protected $table = 'pinit_imports';

    protected $fillable = [
        'archivo_path',
        'fecha_archivo',
        'total_filas',
        'status',
        'errores',
        'warnings',
        'importado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_archivo' => 'date',
            'status' => StatusImport::class,
            'errores' => 'array',
            'warnings' => 'array',
        ];
    }

    public function rutas(): HasMany
    {
        return $this->hasMany(PinitRuta::class);
    }

    public function importadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'importado_por');
    }

    public function cruces(): HasMany
    {
        return $this->hasMany(Cruce::class);
    }
}
