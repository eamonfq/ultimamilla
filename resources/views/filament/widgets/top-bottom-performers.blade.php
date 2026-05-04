<x-filament-widgets::widget>
    <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800">
        <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-4">Performance ultimos 14 dias</h3>

        @if (! $tieneData)
            <p class="text-sm text-slate-500 text-center py-8">
                Aun no hay suficientes datos. Se necesitan al menos 3 dias de cruces por repartidor.
            </p>
        @else
            <div class="space-y-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-emerald-600 font-semibold mb-2">Mejores</p>
                    @forelse ($top as $i => $item)
                        <div class="flex items-center gap-3 py-2 border-b border-slate-100 dark:border-slate-800 last:border-0">
                            <span class="w-5 text-xs font-bold text-slate-400">{{ $i + 1 }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-slate-900 dark:text-white truncate">{{ $item['repartidor']->nombre }}</p>
                                <p class="text-xs text-slate-500">{{ $item['dias'] }} dias · {{ number_format($item['total_entregado']) }} entregados</p>
                            </div>
                            <span class="text-sm font-bold text-emerald-600">{{ $item['promedio'] }}%</span>
                        </div>
                    @empty
                        <p class="text-xs text-slate-500">Sin datos.</p>
                    @endforelse
                </div>

                <div>
                    <p class="text-xs uppercase tracking-wide text-rose-600 font-semibold mb-2">Necesitan atencion</p>
                    @forelse ($bottom as $i => $item)
                        <div class="flex items-center gap-3 py-2 border-b border-slate-100 dark:border-slate-800 last:border-0">
                            <span class="w-5 text-xs font-bold text-slate-400">{{ $i + 1 }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-slate-900 dark:text-white truncate">{{ $item['repartidor']->nombre }}</p>
                                <p class="text-xs text-slate-500">{{ $item['dias'] }} dias · {{ number_format($item['total_entregado']) }} entregados</p>
                            </div>
                            <span @class([
                                'text-sm font-bold',
                                'text-rose-600' => $item['promedio'] < 70,
                                'text-amber-600' => $item['promedio'] >= 70 && $item['promedio'] < 85,
                                'text-slate-600' => $item['promedio'] >= 85,
                            ])>{{ $item['promedio'] }}%</span>
                        </div>
                    @empty
                        <p class="text-xs text-slate-500">Sin datos.</p>
                    @endforelse
                </div>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
