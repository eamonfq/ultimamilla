<?php

namespace App\Filament\Pages;

use App\Enums\EstadoReserva;
use App\Models\Asignacion;
use App\Models\Reserva;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class RecepcionHoy extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|\UnitEnum|null $navigationGroup = 'Operación';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Recepción de hoy';

    protected static ?string $navigationLabel = 'Recepción de hoy';

    protected string $view = 'filament.pages.recepcion-hoy';

    public array $filas = [];

    public function mount(): void
    {
        $this->cargarReservasDeHoy();
    }

    public function cargarReservasDeHoy(): void
    {
        $hoy = today('America/Bogota');

        $reservas = Reserva::query()
            ->with(['repartidor', 'asignacion'])
            ->whereDate('fecha_operacion', $hoy)
            ->where('estado', EstadoReserva::Activa)
            ->whereHas('repartidor', fn ($q) => $q->activos())
            ->get()
            ->sortBy('repartidor.nombre');

        $this->filas = $reservas->map(function (Reserva $r) {
            $asig = $r->asignacion;

            return [
                'reserva_id' => $r->id,
                'cedula' => $r->repartidor->cedula,
                'nombre' => $r->repartidor->nombre,
                'ciudad' => $r->repartidor->ciudad->label(),
                'reservado' => $r->paquetes,
                'rutas' => $r->rutas,
                'asignado' => $asig?->paquetes_asignados ?? $r->paquetes,
                'motivo' => $asig?->ajuste_motivo ?? '',
                'entregado' => $asig?->entregado_a_repartidor_at !== null,
                'entregado_at' => $asig?->entregado_a_repartidor_at?->locale('es')->isoFormat('H:mm'),
                'asignacion_id' => $asig?->id,
                'tiene_ajuste' => $asig && $asig->paquetes_asignados !== $r->paquetes,
            ];
        })->values()->toArray();
    }

    public function guardarFila(int $index): void
    {
        $fila = $this->filas[$index] ?? null;
        if (! $fila) {
            return;
        }

        $reserva = Reserva::find($fila['reserva_id']);
        if (! $reserva) {
            return;
        }

        $tieneAjuste = (int) $fila['asignado'] !== (int) $fila['reservado'];

        if ($tieneAjuste && empty(trim($fila['motivo'] ?? ''))) {
            Notification::make()
                ->warning()
                ->title('Motivo requerido')
                ->body("El repartidor {$fila['nombre']} tiene ajuste sin motivo.")
                ->send();

            return;
        }

        $asig = Asignacion::updateOrCreate(
            ['reserva_id' => $reserva->id],
            [
                'paquetes_asignados' => (int) $fila['asignado'],
                'ajuste_motivo' => $tieneAjuste ? trim($fila['motivo']) : null,
                'entregado_a_repartidor_at' => $fila['entregado'] ? now('America/Bogota') : null,
                'ajustado_por' => auth()->id(),
            ]
        );

        activity()
            ->performedOn($asig)
            ->causedBy(auth()->user())
            ->withProperties([
                'reserva_id' => $reserva->id,
                'repartidor' => $fila['nombre'],
                'reservado' => $fila['reservado'],
                'asignado' => $fila['asignado'],
                'motivo' => $fila['motivo'] ?? '',
            ])
            ->log('asignacion_guardada');

        $this->cargarReservasDeHoy();

        Notification::make()
            ->success()
            ->title("Guardado: {$fila['nombre']}")
            ->send();
    }

    public function guardarTodo(): void
    {
        $cuenta = 0;
        $errores = 0;

        foreach ($this->filas as $i => $fila) {
            $tieneAjuste = (int) $fila['asignado'] !== (int) $fila['reservado'];
            if ($tieneAjuste && empty(trim($fila['motivo'] ?? ''))) {
                $errores++;

                continue;
            }
            $this->guardarFilaSilencioso($i);
            $cuenta++;
        }

        Notification::make()
            ->{$errores > 0 ? 'warning' : 'success'}()
            ->title("Guardadas {$cuenta} asignaciones")
            ->body($errores > 0 ? "{$errores} filas con ajuste sin motivo no se guardaron." : null)
            ->send();

        $this->cargarReservasDeHoy();
    }

    private function guardarFilaSilencioso(int $index): void
    {
        $fila = $this->filas[$index] ?? null;
        if (! $fila) {
            return;
        }

        $reserva = Reserva::find($fila['reserva_id']);
        if (! $reserva) {
            return;
        }

        $tieneAjuste = (int) $fila['asignado'] !== (int) $fila['reservado'];

        Asignacion::updateOrCreate(
            ['reserva_id' => $reserva->id],
            [
                'paquetes_asignados' => (int) $fila['asignado'],
                'ajuste_motivo' => $tieneAjuste ? trim($fila['motivo']) : null,
                'entregado_a_repartidor_at' => $fila['entregado'] ? now('America/Bogota') : null,
                'ajustado_por' => auth()->id(),
            ]
        );

        activity()
            ->performedOn($reserva)
            ->causedBy(auth()->user())
            ->withProperties([
                'reserva_id' => $reserva->id,
                'repartidor' => $fila['nombre'],
                'reservado' => $fila['reservado'],
                'asignado' => $fila['asignado'],
                'motivo' => $fila['motivo'] ?? '',
            ])
            ->log('asignacion_guardada');
    }

    public function getTotalReservadoProperty(): int
    {
        return (int) collect($this->filas)->sum('reservado');
    }

    public function getTotalAsignadoProperty(): int
    {
        return (int) collect($this->filas)->sum('asignado');
    }

    public function getTotalEntregadosProperty(): int
    {
        return collect($this->filas)->where('entregado', true)->count();
    }
}
