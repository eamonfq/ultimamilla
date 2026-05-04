<x-filament-widgets::widget>
    <div class="bg-rose-50 dark:bg-rose-950/30 border-2 border-rose-200 dark:border-rose-900 rounded-2xl p-5">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-rose-100 dark:bg-rose-900 flex items-center justify-center">
                <svg class="w-5 h-5 text-rose-700 dark:text-rose-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <div>
                <h3 class="text-base font-semibold text-rose-900 dark:text-rose-100">
                    Repartidores que necesitan atencion
                </h3>
                <p class="text-xs text-rose-700 dark:text-rose-300">
                    {{ count($alertas) }} {{ count($alertas) === 1 ? 'caso' : 'casos' }} detectados en los ultimos 14 dias.
                </p>
            </div>
        </div>

        <div class="space-y-2">
            @foreach ($alertas as $caso)
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-rose-100 dark:border-rose-900/50 p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <a
                                href="{{ \App\Filament\Resources\RepartidorResource::getUrl('edit', ['record' => $caso['repartidor']]) }}"
                                class="font-semibold text-slate-900 dark:text-white hover:text-indigo-600 transition"
                            >
                                {{ $caso['repartidor']->nombre }}
                            </a>
                            <p class="text-xs text-slate-500 font-mono">{{ $caso['repartidor']->cedula }}</p>

                            <div class="mt-2 space-y-1">
                                @foreach ($caso['alertas'] as $alerta)
                                    <div class="flex items-start gap-2">
                                        <span @class([
                                            'inline-block w-1.5 h-1.5 rounded-full mt-1.5',
                                            'bg-amber-500' => $alerta['severidad'] === 2,
                                            'bg-rose-600' => $alerta['severidad'] === 3,
                                        ])></span>
                                        <div class="flex-1">
                                            <p class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $alerta['titulo'] }}</p>
                                            <p class="text-xs text-slate-500">{{ $alerta['detalle'] }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        @if ($caso['repartidor']->telefono)
                            <a
                                href="https://wa.me/57{{ preg_replace('/\D/', '', $caso['repartidor']->telefono) }}"
                                target="_blank"
                                rel="noopener"
                                class="flex-shrink-0 px-3 py-1.5 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition"
                            >
                                Contactar
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
