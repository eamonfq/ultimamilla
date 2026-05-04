<?php

namespace App\Enums;

enum StatusImport: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Done = 'done';
    case Failed = 'failed';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Processing => 'Procesando',
            self::Done => 'Completado',
            self::Failed => 'Fallido',
            self::Superseded => 'Reemplazado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Processing => 'info',
            self::Done => 'success',
            self::Failed => 'danger',
            self::Superseded => 'warning',
        };
    }
}
