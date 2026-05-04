<div class="min-h-screen bg-slate-50 pb-12" x-data="{ toast: null }"
     @notify.window="toast = $event.detail; setTimeout(() => toast = null, 3000)">

    {{-- Header sticky --}}
    <header class="sticky top-0 z-30 bg-white border-b border-slate-200">
        <div class="max-w-md mx-auto px-4 py-3 flex items-center justify-between">
            <x-logo class="h-7 w-auto text-slate-900" />
            <div class="flex items-center gap-3">
                <span class="text-xs text-slate-500 truncate max-w-[140px]">{{ $repartidor->nombre }}</span>
                <button
                    wire:click="logout"
                    wire:confirm="¿Cerrar sesión?"
                    class="text-xs text-slate-500 hover:text-rose-600 transition px-2 py-1 rounded"
                >
                    Salir
                </button>
            </div>
        </div>
    </header>

    {{-- Resumen / cupo --}}
    <section class="max-w-md mx-auto px-4 pt-5 pb-3">
        <div class="bg-gradient-to-br from-indigo-600 to-indigo-700 rounded-2xl p-5 text-white shadow-sm">
            <p class="text-xs uppercase tracking-wider text-indigo-200">Tu cupo diario</p>
            <p class="mt-1 text-3xl font-bold">{{ $cupoEfectivo }} <span class="text-base font-normal text-indigo-200">paquetes</span></p>
            <p class="mt-2 text-xs text-indigo-200">Reserva con anticipación. Las reservas cierran el día anterior según horario configurado.</p>
        </div>
    </section>

    {{-- Cards de días --}}
    <section class="max-w-md mx-auto px-4 pt-2">
        <h2 class="text-sm font-semibold text-slate-700 mb-3 px-1">Próximos días</h2>

        <div class="space-y-2.5">
            @foreach ($cards as $card)
                @php
                    $r = $card['reserva'];
                    $bloq = $card['bloqueada'];
                @endphp

                <button
                    type="button"
                    @if (! $bloq) wire:click="abrirModal({{ $card['fecha_timestamp'] }})" @endif
                    @class([
                        'w-full text-left rounded-2xl border transition-all duration-150 p-4 flex items-center gap-4',
                        'bg-white border-slate-200 hover:border-indigo-300 hover:shadow-md active:scale-[0.99]' => ! $bloq,
                        'bg-slate-100 border-slate-200 cursor-not-allowed opacity-70' => $bloq,
                        'ring-2 ring-indigo-500 ring-offset-2' => $card['es_mañana'] && ! $bloq,
                    ])
                    @if ($bloq) disabled @endif
                >
                    {{-- Bloque de fecha --}}
                    <div @class([
                        'flex-shrink-0 w-14 h-14 rounded-xl flex flex-col items-center justify-center',
                        'bg-indigo-50 text-indigo-700' => ! $bloq && ! $r,
                        'bg-emerald-50 text-emerald-700' => ! $bloq && $r,
                        'bg-slate-200 text-slate-500' => $bloq,
                    ])>
                        <span class="text-[10px] font-medium uppercase tracking-wide">{{ $card['mes_corto'] }}</span>
                        <span class="text-xl font-bold leading-none">{{ $card['dia_numero'] }}</span>
                    </div>

                    {{-- Info --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="font-semibold text-slate-900">{{ $card['dia_semana'] }}</p>
                            @if ($card['es_mañana'])
                                <span class="text-[10px] font-bold uppercase bg-indigo-600 text-white px-1.5 py-0.5 rounded">Mañana</span>
                            @endif
                        </div>

                        @if ($r)
                            <p class="text-sm text-emerald-700 font-medium mt-0.5">
                                Reservado: {{ $r['paquetes'] }} paq. · {{ $r['rutas'] }} {{ $r['rutas'] === 1 ? 'ruta' : 'rutas' }}
                            </p>
                        @elseif ($bloq)
                            <p class="text-sm text-slate-500 mt-0.5">Cerrado</p>
                        @else
                            <p class="text-sm text-slate-500 mt-0.5">Sin reservar</p>
                        @endif
                    </div>

                    {{-- Indicador --}}
                    <div class="flex-shrink-0">
                        @if ($bloq)
                            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        @elseif ($r)
                            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                            </svg>
                        @else
                            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                        @endif
                    </div>
                </button>
            @endforeach
        </div>
    </section>

    {{-- Bottom-sheet modal --}}
    @if ($modalAbierto)
        @php
            $cardSeleccionada = collect($cards)->firstWhere('fecha_timestamp', $diaSeleccionado);
            $excedeCupo = $paquetes > $cupoEfectivo;
        @endphp
        <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center"
             x-data="{ show: false }" x-init="setTimeout(() => show = true, 10)"
             x-on:keydown.escape.window="$wire.cerrarModal()">

            {{-- Backdrop --}}
            <div
                class="fixed inset-0 bg-black/50 transition-opacity"
                x-bind:class="show ? 'opacity-100' : 'opacity-0'"
                wire:click="cerrarModal"
            ></div>

            {{-- Sheet --}}
            <div
                class="relative w-full sm:max-w-md bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl transition-transform"
                x-bind:class="show ? 'translate-y-0' : 'translate-y-full sm:translate-y-4'"
            >
                <div class="flex justify-center pt-3 sm:hidden">
                    <div class="w-10 h-1 bg-slate-300 rounded-full"></div>
                </div>

                <div class="p-6">
                    <div class="flex items-start justify-between mb-1">
                        <div>
                            <p class="text-xs uppercase tracking-wider text-indigo-600 font-semibold">
                                {{ $reservaIdEditando ? 'Editar reserva' : 'Nueva reserva' }}
                            </p>
                            <h2 class="text-xl font-bold text-slate-900 mt-0.5">
                                {{ $cardSeleccionada['dia_semana'] ?? '' }} {{ $cardSeleccionada['dia_numero'] ?? '' }} {{ $cardSeleccionada['mes_corto'] ?? '' }}
                            </h2>
                        </div>
                        <button wire:click="cerrarModal" class="p-1 text-slate-400 hover:text-slate-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <p class="text-xs text-slate-500 mb-5">
                        Cierra el {{ $cardSeleccionada['deadline_humano'] ?? '' }} hora Colombia.
                    </p>

                    <form wire:submit="guardarReserva" class="space-y-5">
                        {{-- Paquetes --}}
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">¿Cuántos paquetes?</label>
                            <div class="flex items-center gap-3">
                                <button type="button"
                                        x-on:click="$wire.set('paquetes', Math.max(1, {{ (int) $paquetes }} - 5))"
                                        class="w-12 h-12 rounded-xl bg-slate-100 hover:bg-slate-200 active:bg-slate-300 text-slate-700 font-semibold text-lg transition">
                                    −
                                </button>
                                <input
                                    type="number"
                                    wire:model.live="paquetes"
                                    min="1" max="500"
                                    inputmode="numeric"
                                    class="flex-1 text-center text-3xl font-bold text-slate-900 rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 h-16"
                                >
                                <button type="button"
                                        x-on:click="$wire.set('paquetes', Math.min(500, {{ (int) $paquetes }} + 5))"
                                        class="w-12 h-12 rounded-xl bg-slate-100 hover:bg-slate-200 active:bg-slate-300 text-slate-700 font-semibold text-lg transition">
                                    +
                                </button>
                            </div>
                            @error('paquetes') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror

                            @if ($excedeCupo)
                                <div class="mt-3 p-3 rounded-lg bg-amber-50 border border-amber-200">
                                    <p class="text-xs text-amber-800">
                                        <strong>Atención:</strong> {{ $paquetes }} paquetes excede tu cupo sugerido de {{ $cupoEfectivo }}. Puedes confirmar igual, pero coordina con tu agencia.
                                    </p>
                                </div>
                            @endif
                        </div>

                        {{-- Rutas --}}
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Rutas</label>
                            <div class="grid grid-cols-3 gap-2">
                                @foreach ([1, 2, 3] as $r)
                                    <button type="button" wire:click="$set('rutas', {{ $r }})"
                                            @class([
                                                'h-14 rounded-xl border-2 font-semibold text-lg transition',
                                                'bg-indigo-600 border-indigo-600 text-white' => $rutas === $r,
                                                'bg-white border-slate-300 text-slate-700 hover:border-indigo-300' => $rutas !== $r,
                                            ])>
                                        {{ $r }}
                                    </button>
                                @endforeach
                            </div>
                            @error('rutas') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Acciones --}}
                        <div class="pt-2 space-y-2">
                            <button type="submit"
                                    wire:loading.attr="disabled"
                                    class="w-full py-3.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-semibold transition disabled:opacity-60">
                                <span wire:loading.remove wire:target="guardarReserva">
                                    {{ $reservaIdEditando ? 'Guardar cambios' : 'Confirmar reserva' }}
                                </span>
                                <span wire:loading wire:target="guardarReserva">Guardando...</span>
                            </button>

                            @if ($reservaIdEditando)
                                <button type="button"
                                        wire:click="cancelarReserva"
                                        wire:confirm="¿Seguro que quieres cancelar esta reserva?"
                                        class="w-full py-3 rounded-xl bg-white border border-rose-300 text-rose-600 font-medium hover:bg-rose-50 transition">
                                    Cancelar reserva
                                </button>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Toast --}}
    <div x-show="toast" x-transition.opacity
         class="fixed bottom-4 left-1/2 -translate-x-1/2 z-[60] max-w-sm w-[calc(100%-2rem)]" style="display: none;">
        <div :class="{
                'bg-emerald-600': toast?.tipo === 'success',
                'bg-rose-600': toast?.tipo === 'error',
                'bg-slate-800': toast?.tipo === 'info'
             }"
             class="rounded-xl px-4 py-3 shadow-lg text-white text-sm font-medium text-center">
            <span x-text="toast?.mensaje"></span>
        </div>
    </div>
</div>
