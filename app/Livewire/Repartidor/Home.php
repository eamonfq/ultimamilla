<?php

namespace App\Livewire\Repartidor;

use App\Enums\EstadoReserva;
use App\Models\Repartidor;
use App\Models\Reserva;
use App\Services\DeadlineChecker;
use App\Settings\UltimamillaSettings;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Home extends Component
{
    public ?int $diaSeleccionado = null;

    public bool $modalAbierto = false;

    public ?int $reservaIdEditando = null;

    public int $paquetes = 0;

    public int $rutas = 1;

    #[Layout('components.layouts.repartidor')]
    public function render()
    {
        return view('livewire.repartidor.home', [
            'repartidor' => $this->repartidor,
            'cards' => $this->cards,
            'cupoEfectivo' => $this->cupoEfectivo,
        ]);
    }

    #[Computed]
    public function repartidor(): Repartidor
    {
        return Auth::guard('repartidor')->user();
    }

    #[Computed]
    public function settings(): UltimamillaSettings
    {
        return app(UltimamillaSettings::class);
    }

    #[Computed]
    public function cupoEfectivo(): int
    {
        return $this->repartidor->cupoEfectivo($this->settings);
    }

    #[Computed]
    public function cards(): array
    {
        $checker = app(DeadlineChecker::class);
        $hoy = now('America/Bogota')->startOfDay();
        $diasOperativos = $this->settings->dias_operativos;

        $cards = [];
        $fecha = $hoy->copy();
        $diasGenerados = 0;
        $maxIteraciones = 30;

        while ($diasGenerados < 7 && $maxIteraciones > 0) {
            $fecha->addDay();
            $maxIteraciones--;

            if (! in_array($fecha->dayOfWeek, $diasOperativos, true)) {
                continue;
            }

            $reserva = Reserva::query()
                ->where('repartidor_id', $this->repartidor->id)
                ->whereDate('fecha_operacion', $fecha)
                ->where('estado', EstadoReserva::Activa)
                ->first();

            $deadline = $checker->deadlineFor($fecha->copy());
            $bloqueadaPorDeadline = now('America/Bogota')->gte($deadline);
            $bloqueada = $reserva ? ($reserva->locked_at !== null || $bloqueadaPorDeadline) : $bloqueadaPorDeadline;

            $cards[] = [
                'fecha' => $fecha->copy(),
                'fecha_iso' => $fecha->toDateString(),
                'fecha_timestamp' => $fecha->timestamp,
                'dia_semana' => $this->nombreDia($fecha->dayOfWeek),
                'dia_numero' => $fecha->day,
                'mes_corto' => $this->nombreMes($fecha->month),
                'es_mañana' => $fecha->isSameDay($hoy->copy()->addDay()),
                'reserva' => $reserva ? [
                    'id' => $reserva->id,
                    'paquetes' => $reserva->paquetes,
                    'rutas' => $reserva->rutas,
                ] : null,
                'bloqueada' => $bloqueada,
                'deadline' => $deadline,
                'deadline_humano' => $deadline->locale('es')->isoFormat('dddd D [a las] H:mm'),
            ];

            $diasGenerados++;
        }

        return $cards;
    }

    public function abrirModal(int $timestamp): void
    {
        $fecha = Carbon::createFromTimestamp($timestamp, 'America/Bogota');
        $checker = app(DeadlineChecker::class);

        if (! $checker->esDiaOperativo($fecha)) {
            $this->dispatch('notify', tipo: 'error', mensaje: 'Ese día no es operativo.');

            return;
        }

        if (now('America/Bogota')->gte($checker->deadlineFor($fecha))) {
            $this->dispatch('notify', tipo: 'error', mensaje: 'Ya pasó la hora de cierre para este día.');

            return;
        }

        $this->diaSeleccionado = $timestamp;

        $reserva = Reserva::query()
            ->where('repartidor_id', $this->repartidor->id)
            ->whereDate('fecha_operacion', $fecha)
            ->where('estado', EstadoReserva::Activa)
            ->first();

        if ($reserva) {
            $this->reservaIdEditando = $reserva->id;
            $this->paquetes = $reserva->paquetes;
            $this->rutas = $reserva->rutas;
        } else {
            $this->reservaIdEditando = null;
            $this->paquetes = $this->cupoEfectivo;
            $this->rutas = 1;
        }

        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->diaSeleccionado = null;
        $this->reservaIdEditando = null;
        $this->paquetes = 0;
        $this->rutas = 1;
        $this->resetErrorBag();
    }

    public function guardarReserva(): void
    {
        $this->validate([
            'paquetes' => ['required', 'integer', 'min:1', 'max:500'],
            'rutas' => ['required', 'integer', 'in:1,2,3'],
        ], [
            'paquetes.required' => 'Indica cuántos paquetes vas a llevar.',
            'paquetes.min' => 'Mínimo 1 paquete.',
            'paquetes.max' => 'Máximo 500 paquetes.',
            'rutas.in' => 'Selecciona 1, 2 o 3 rutas.',
        ]);

        if ($this->diaSeleccionado === null) {
            return;
        }

        $fecha = Carbon::createFromTimestamp($this->diaSeleccionado, 'America/Bogota');
        $checker = app(DeadlineChecker::class);

        if (now('America/Bogota')->gte($checker->deadlineFor($fecha))) {
            $this->dispatch('notify', tipo: 'error', mensaje: 'Ya pasó la hora de cierre, no se puede guardar.');
            $this->cerrarModal();

            return;
        }

        if ($this->reservaIdEditando) {
            $reserva = Reserva::find($this->reservaIdEditando);
            if (! $reserva || $reserva->repartidor_id !== $this->repartidor->id) {
                $this->dispatch('notify', tipo: 'error', mensaje: 'Reserva no encontrada.');
                $this->cerrarModal();

                return;
            }
            $reserva->update([
                'paquetes' => $this->paquetes,
                'rutas' => $this->rutas,
            ]);
            $mensaje = 'Reserva actualizada.';
        } else {
            try {
                Reserva::create([
                    'repartidor_id' => $this->repartidor->id,
                    'fecha_operacion' => $fecha->toDateString(),
                    'paquetes' => $this->paquetes,
                    'rutas' => $this->rutas,
                    'estado' => EstadoReserva::Activa,
                ]);
                $mensaje = '¡Reserva confirmada!';
            } catch (QueryException $e) {
                if (str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), 'UNIQUE')) {
                    $existente = Reserva::query()
                        ->where('repartidor_id', $this->repartidor->id)
                        ->whereDate('fecha_operacion', $fecha)
                        ->first();
                    if ($existente) {
                        $existente->update([
                            'paquetes' => $this->paquetes,
                            'rutas' => $this->rutas,
                            'estado' => EstadoReserva::Activa,
                        ]);
                        $mensaje = 'Reserva actualizada.';
                    } else {
                        throw $e;
                    }
                } else {
                    throw $e;
                }
            }
        }

        $this->cerrarModal();
        unset($this->cards);
        $this->dispatch('notify', tipo: 'success', mensaje: $mensaje);
    }

    public function cancelarReserva(): void
    {
        if (! $this->reservaIdEditando) {
            return;
        }

        $reserva = Reserva::find($this->reservaIdEditando);
        if (! $reserva || $reserva->repartidor_id !== $this->repartidor->id) {
            $this->cerrarModal();

            return;
        }

        $checker = app(DeadlineChecker::class);
        if ($checker->isReservaLocked($reserva)) {
            $this->dispatch('notify', tipo: 'error', mensaje: 'No se puede cancelar, ya pasó la hora de cierre.');
            $this->cerrarModal();

            return;
        }

        $reserva->update(['estado' => EstadoReserva::Cancelada]);

        $this->cerrarModal();
        unset($this->cards);
        $this->dispatch('notify', tipo: 'info', mensaje: 'Reserva cancelada.');
    }

    public function logout(): void
    {
        Auth::guard('repartidor')->logout();
        session()->invalidate();
        session()->regenerateToken();
        $this->redirect(route('repartidor.login'), navigate: false);
    }

    private function nombreDia(int $dayOfWeek): string
    {
        return ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'][$dayOfWeek];
    }

    private function nombreMes(int $month): string
    {
        return ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'][$month];
    }
}
