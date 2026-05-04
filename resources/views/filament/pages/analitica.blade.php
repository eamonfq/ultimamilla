<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Selector de repartidor --}}
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5">
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                Selecciona un repartidor
            </label>
            <div class="flex gap-3">
                <select wire:model.live="repartidorId" class="flex-1 rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— Selecciona —</option>
                    @foreach ($this->repartidores as $r)
                        <option value="{{ $r->id }}">{{ $r->nombre }} · {{ $r->cedula }}</option>
                    @endforeach
                </select>
                <select wire:model.live="diasMirar" class="rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="7">Ultimos 7 dias</option>
                    <option value="14">Ultimos 14 dias</option>
                    <option value="30">Ultimos 30 dias</option>
                    <option value="90">Ultimos 90 dias</option>
                </select>
            </div>
        </div>

        @if ($this->repartidorSeleccionado)
            @php $r = $this->resumen; @endphp

            {{-- Resumen --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Dias con data</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $r['total_dias'] }}</p>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Cumplimiento</p>
                    <p @class([
                        'text-2xl font-bold mt-1',
                        'text-emerald-600' => $r['cumplimiento_promedio'] !== null && $r['cumplimiento_promedio'] >= 90,
                        'text-amber-600' => $r['cumplimiento_promedio'] !== null && $r['cumplimiento_promedio'] >= 70 && $r['cumplimiento_promedio'] < 90,
                        'text-rose-600' => $r['cumplimiento_promedio'] !== null && $r['cumplimiento_promedio'] < 70,
                        'text-slate-400' => $r['cumplimiento_promedio'] === null,
                    ])>{{ $r['cumplimiento_promedio'] !== null ? $r['cumplimiento_promedio'] . '%' : '—' }}</p>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Entregado total</p>
                    <p class="text-2xl font-bold text-indigo-600 mt-1">{{ number_format($r['entregado_total']) }}</p>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">vs Asignado</p>
                    <p class="text-2xl font-bold text-slate-700 mt-1">{{ number_format($r['asignado_total']) }}</p>
                </div>
            </div>

            {{-- Distribucion de patrones --}}
            @if ($r['total_dias'] > 0)
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-3">Distribucion de patrones</h3>
                    <div class="flex h-8 rounded-lg overflow-hidden bg-slate-100 dark:bg-slate-800">
                        @if ($r['distribucion']['consistente'] > 0)
                            <div class="bg-emerald-500 flex items-center justify-center text-xs font-bold text-white"
                                 style="width: {{ ($r['distribucion']['consistente'] / $r['total_dias']) * 100 }}%"
                                 title="Consistente: {{ $r['distribucion']['consistente'] }} dias">
                                {{ $r['distribucion']['consistente'] }}
                            </div>
                        @endif
                        @if ($r['distribucion']['sub_reserva'] > 0)
                            <div class="bg-amber-500 flex items-center justify-center text-xs font-bold text-white"
                                 style="width: {{ ($r['distribucion']['sub_reserva'] / $r['total_dias']) * 100 }}%"
                                 title="Sub-reserva: {{ $r['distribucion']['sub_reserva'] }} dias">
                                {{ $r['distribucion']['sub_reserva'] }}
                            </div>
                        @endif
                        @if ($r['distribucion']['sobre_reserva'] > 0)
                            <div class="bg-rose-500 flex items-center justify-center text-xs font-bold text-white"
                                 style="width: {{ ($r['distribucion']['sobre_reserva'] / $r['total_dias']) * 100 }}%"
                                 title="Sobre-reserva: {{ $r['distribucion']['sobre_reserva'] }} dias">
                                {{ $r['distribucion']['sobre_reserva'] }}
                            </div>
                        @endif
                        @if ($r['distribucion']['sin_patron'] > 0)
                            <div class="bg-slate-400 flex items-center justify-center text-xs font-bold text-white"
                                 style="width: {{ ($r['distribucion']['sin_patron'] / $r['total_dias']) * 100 }}%"
                                 title="Sin patron: {{ $r['distribucion']['sin_patron'] }} dias">
                                {{ $r['distribucion']['sin_patron'] }}
                            </div>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-3 mt-3 text-xs">
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-sm bg-emerald-500"></span>Consistente</span>
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-sm bg-amber-500"></span>Sub-reserva</span>
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-sm bg-rose-500"></span>Sobre-reserva</span>
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-sm bg-slate-400"></span>Sin patron</span>
                    </div>
                </div>
            @endif

            {{-- Grafico de tendencia --}}
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5"
                 x-data="tendenciaChart()"
                 wire:key="tendencia-{{ $this->repartidorId }}-{{ $this->diasMirar }}">
                <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-3">Cumplimiento dia a dia</h3>
                <canvas x-ref="canvas" class="w-full" style="max-height: 300px;"></canvas>
            </div>

            {{-- Tabla de cruces --}}
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                <div class="p-5 border-b border-slate-200 dark:border-slate-800">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">Detalle dia a dia</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800/50">
                            <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-5 py-3">Fecha</th>
                                <th class="px-5 py-3 text-right">Reservado</th>
                                <th class="px-5 py-3 text-right">Asignado</th>
                                <th class="px-5 py-3 text-right">Entregado</th>
                                <th class="px-5 py-3 text-right">Cumpl.</th>
                                <th class="px-5 py-3">Patron</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($this->crucesRepartidor as $c)
                                <tr>
                                    <td class="px-5 py-3 font-medium">{{ $c->fecha_operacion->locale('es')->isoFormat('ddd D MMM') }}</td>
                                    <td class="px-5 py-3 text-right">{{ number_format($c->reservado) }}</td>
                                    <td class="px-5 py-3 text-right">{{ number_format($c->asignado) }}</td>
                                    <td class="px-5 py-3 text-right font-semibold">{{ number_format($c->entregado) }}</td>
                                    <td @class([
                                        'px-5 py-3 text-right font-bold',
                                        'text-emerald-600' => $c->cumplimiento_pct !== null && $c->cumplimiento_pct >= 90,
                                        'text-amber-600' => $c->cumplimiento_pct !== null && $c->cumplimiento_pct >= 70 && $c->cumplimiento_pct < 90,
                                        'text-rose-600' => $c->cumplimiento_pct !== null && $c->cumplimiento_pct < 70,
                                        'text-slate-400' => $c->cumplimiento_pct === null,
                                    ])>
                                        {{ $c->cumplimiento_pct !== null ? number_format($c->cumplimiento_pct, 1) . '%' : '—' }}
                                    </td>
                                    <td class="px-5 py-3">
                                        @if ($c->patron)
                                            <span @class([
                                                'inline-block px-2 py-0.5 text-xs font-medium rounded-full',
                                                'bg-emerald-100 text-emerald-700' => $c->patron === \App\Enums\PatronCruce::Consistente,
                                                'bg-amber-100 text-amber-700' => $c->patron === \App\Enums\PatronCruce::SubReserva,
                                                'bg-rose-100 text-rose-700' => $c->patron === \App\Enums\PatronCruce::SobreReserva,
                                            ])>{{ $c->patron->label() }}</span>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-12 text-center text-slate-500">
                                        Sin cruces para este rango.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-12 text-center">
                <svg class="w-12 h-12 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <p class="text-sm text-slate-500">Selecciona un repartidor para ver su historico de cumplimiento.</p>
            </div>
        @endif
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
        <script>
            function tendenciaChart() {
                return {
                    chart: null,
                    init() {
                        this.render();
                    },
                    render() {
                        if (this.chart) this.chart.destroy();

                        const datos = @js($this->datosGrafico);

                        this.chart = new Chart(this.$refs.canvas, {
                            type: 'line',
                            data: {
                                labels: datos.labels,
                                datasets: [{
                                    label: 'Cumplimiento %',
                                    data: datos.cumplimiento,
                                    borderColor: 'rgb(79, 70, 229)',
                                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                                    tension: 0.3,
                                    fill: true,
                                    pointRadius: 4,
                                    pointBackgroundColor: 'rgb(79, 70, 229)',
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { display: false },
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        max: 110,
                                        ticks: {
                                            callback: (v) => v + '%'
                                        }
                                    }
                                }
                            }
                        });
                    }
                };
            }
        </script>
    @endpush
</x-filament-panels::page>
