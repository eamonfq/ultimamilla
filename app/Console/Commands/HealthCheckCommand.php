<?php

namespace App\Console\Commands;

use App\Settings\UltimamillaSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthCheckCommand extends Command
{
    protected $signature = 'ultimamilla:health';

    protected $description = 'Verifica que la aplicación esté operativa';

    public function handle(): int
    {
        $checks = [
            'DB' => fn () => DB::connection()->getPdo() !== null,
            'Redis' => fn () => Redis::ping() === 'PONG' || Redis::ping() === '+PONG',
            'Settings' => fn () => app(UltimamillaSettings::class)->cupo_global_default > 0,
            'Storage writable' => fn () => is_writable(storage_path('logs')),
            'Cache' => fn () => cache()->put('health_check', 'ok', 5) && cache()->get('health_check') === 'ok',
        ];

        $todoOk = true;
        foreach ($checks as $nombre => $check) {
            try {
                $resultado = $check();
                if ($resultado) {
                    $this->info("OK {$nombre}");
                } else {
                    $this->error("FAIL {$nombre}");
                    $todoOk = false;
                }
            } catch (\Throwable $e) {
                $this->error("FAIL {$nombre}: ".$e->getMessage());
                $todoOk = false;
            }
        }

        return $todoOk ? self::SUCCESS : self::FAILURE;
    }
}
