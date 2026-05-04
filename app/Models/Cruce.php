<?php

namespace App\Models;

use App\Enums\PatronCruce;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cruce extends Model
{
    protected $fillable = [
        'fecha_operacion',
        'repartidor_id',
        'reservado',
        'asignado',
        'entregado',
        'cumplimiento_pct',
        'patron',
        'pinit_import_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_operacion' => 'date',
            'patron' => PatronCruce::class,
            'cumplimiento_pct' => 'decimal:2',
        ];
    }

    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(Repartidor::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(PinitImport::class, 'pinit_import_id');
    }
}
