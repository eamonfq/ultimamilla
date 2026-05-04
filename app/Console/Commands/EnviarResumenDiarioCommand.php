<?php

namespace App\Console\Commands;

use App\Mail\ResumenDiarioMail;
use App\Services\ResumenDiario;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnviarResumenDiarioCommand extends Command
{
    protected $signature = 'ultimamilla:resumen-diario {--fecha= : Fecha en formato YYYY-MM-DD (default: ayer)}';

    protected $description = 'Calcula stats del dia anterior y envia email de resumen al admin';

    public function handle(ResumenDiario $servicio): int
    {
        $fecha = $this->option('fecha')
            ? Carbon::parse($this->option('fecha'), 'America/Bogota')
            : today('America/Bogota')->subDay();

        $this->info("Calculando resumen para {$fecha->format('Y-m-d')}...");

        $datos = $servicio->calcular($fecha);

        if ($datos === null) {
            $this->warn('No hay cruces para esa fecha. Saltando envio.');
            Log::warning("Resumen diario: no hay cruces para {$fecha->format('Y-m-d')}");

            return self::SUCCESS;
        }

        $destinatario = config('mail.admin_resumen', env('ADMIN_EMAIL_RESUMEN', 'admin@ultimamilla.test'));

        Mail::to($destinatario)->send(new ResumenDiarioMail($datos));

        $this->info("Resumen enviado a {$destinatario}");
        Log::info('Resumen diario enviado', ['fecha' => $fecha->toDateString(), 'destinatario' => $destinatario]);

        return self::SUCCESS;
    }
}
