<x-filament-panels::page>
    @if (count($filas) === 0)
        <div class="text-center py-16 bg-white dark:bg-slate-900 rounded-2xl">
            <svg class="w-12 h-12 text-slate-300 dark:text-slate-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/>
            </svg>
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Sin reservas para hoy</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Cuando los repartidores reserven para hoy, aparecerán aquí.</p>
        </div>
    @else
        {{-- Resumen --}}
        <div class="grid grid-cols-3 gap-3 mb-6">
            <div class="bg-white dark:bg-slate-900 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
                <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Reservado</p>
                <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ number_format($this->totalReservado) }}</p>
            </div>
            <div class="bg-white dark:bg-slate-900 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
                <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Asignado</p>
                <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ number_format($this->totalAsignado) }}</p>
            </div>
            <div class="bg-white dark:bg-slate-900 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
                <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Entregados</p>
                <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ $this->totalEntregados }}/{{ count($filas) }}</p>
            </div>
        </div>

        {{-- Acción global --}}
        <div class="flex justify-end mb-4">
            <button
                type="button"
                wire:click="guardarTodo"
                wire:loading.attr="disabled"
                wire:target="guardarTodo"
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-semibold transition disabled:opacity-50"
            >
                <span wire:loading.remove wire:target="guardarTodo">Guardar todo</span>
                <span wire:loading wire:target="guardarTodo">Guardando...</span>
            </button>
        </div>

        {{-- Tabla inline --}}
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide">Repartidor</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide">Reservó</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide">Asigna</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide">Motivo (si hay ajuste)</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide">Entregado</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide w-24">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach ($filas as $i => $fila)
                            @php $cambia = (int) $fila['asignado'] !== (int) $fila['reservado']; @endphp
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-slate-900 dark:text-white">{{ $fila['nombre'] }}</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400 font-mono">{{ $fila['cedula'] }} · {{ $fila['ciudad'] }}</div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $fila['reservado'] }}</span>
                                    <span class="text-xs text-slate-400 block">{{ $fila['rutas'] }} {{ $fila['rutas'] === 1 ? 'ruta' : 'rutas' }}</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <input
                                        type="number"
                                        min="0"
                                        max="500"
                                        wire:model.live.debounce.300ms="filas.{{ $i }}.asignado"
                                        @class([
                                            'w-20 text-center font-bold rounded-lg border-2 py-1.5 transition',
                                            'border-amber-400 bg-amber-50 text-amber-900 dark:bg-amber-900/30 dark:text-amber-200' => $cambia,
                                            'border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white' => ! $cambia,
                                        ])
                                    >
                                </td>
                                <td class="px-4 py-3">
                                    @if ($cambia)
                                        <input
                                            type="text"
                                            wire:model.blur="filas.{{ $i }}.motivo"
                                            placeholder="Ej: Llegó menos mercancía"
                                            class="w-full text-sm rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 py-1.5"
                                        >
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input
                                            type="checkbox"
                                            wire:model.live="filas.{{ $i }}.entregado"
                                            class="sr-only peer"
                                        >
                                        <div class="relative w-11 h-6 bg-slate-200 dark:bg-slate-700 peer-focus:ring-2 peer-focus:ring-indigo-300 rounded-full peer peer-checked:bg-emerald-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                                    </label>
                                    @if ($fila['entregado_at'])
                                        <div class="text-xs text-emerald-600 dark:text-emerald-400 mt-1">{{ $fila['entregado_at'] }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        type="button"
                                        wire:click="guardarFila({{ $i }})"
                                        wire:loading.attr="disabled"
                                        wire:target="guardarFila({{ $i }})"
                                        class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 dark:bg-slate-700 hover:bg-indigo-600 hover:text-white text-slate-700 dark:text-slate-300 transition"
                                    >
                                        Guardar
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-filament-panels::page>
