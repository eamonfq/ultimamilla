<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header info --}}
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-700 p-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-gray-400">Fecha del archivo</p>
                    <p class="text-lg font-bold text-slate-900 dark:text-white mt-1">{{ $record->fecha_archivo->locale('es')->isoFormat('D MMM YYYY') }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-gray-400">Estado</p>
                    <p class="mt-1">
                        <span @class([
                            'inline-block px-2 py-0.5 text-xs font-medium rounded-full',
                            'bg-emerald-100 text-emerald-700' => $record->status === \App\Enums\StatusImport::Done,
                            'bg-rose-100 text-rose-700' => $record->status === \App\Enums\StatusImport::Failed,
                            'bg-amber-100 text-amber-700' => $record->status === \App\Enums\StatusImport::Superseded,
                            'bg-indigo-100 text-indigo-700' => in_array($record->status, [\App\Enums\StatusImport::Pending, \App\Enums\StatusImport::Processing]),
                        ])>{{ $record->status->label() }}</span>
                    </p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-gray-400">Total filas</p>
                    <p class="text-lg font-bold text-slate-900 dark:text-white mt-1">{{ number_format($record->total_filas) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-gray-400">Subido por</p>
                    <p class="text-sm text-slate-700 dark:text-gray-300 mt-1">{{ $record->importadoPor?->name ?? '—' }}</p>
                    <p class="text-xs text-slate-500 dark:text-gray-500">{{ $record->created_at->locale('es')->isoFormat('D MMM YYYY H:mm') }}</p>
                </div>
            </div>
        </div>

        {{-- Cedulas no encontradas --}}
        @if (count($this->cedulasNoEncontradas) > 0)
            <div class="bg-amber-50 dark:bg-amber-950 border-2 border-amber-200 dark:border-amber-800 rounded-2xl p-6">
                <h3 class="text-base font-semibold text-amber-900 dark:text-amber-100 mb-3">Cedulas no encontradas ({{ count($this->cedulasNoEncontradas) }})</h3>
                <p class="text-sm text-amber-700 dark:text-amber-300 mb-4">Estas cedulas aparecen en el archivo pero no existen en el sistema. Puedes crear los repartidores.</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->cedulasNoEncontradas as $cedula)
                        <a href="{{ $this->getCrearRepartidorUrl($cedula) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white dark:bg-gray-800 border border-amber-300 dark:border-amber-700 rounded-lg text-sm font-mono text-amber-800 dark:text-amber-200 hover:bg-amber-100 dark:hover:bg-amber-900 transition">
                            {{ $cedula }}
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="14" height="14">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Errores --}}
        @if (count($this->errores) > 0)
            <div class="bg-rose-50 dark:bg-rose-950 border-2 border-rose-200 dark:border-rose-800 rounded-2xl p-6">
                <h3 class="text-base font-semibold text-rose-900 dark:text-rose-100 mb-3">Errores ({{ count($this->errores) }})</h3>
                <ul class="space-y-1 text-sm text-rose-700 dark:text-rose-300">
                    @foreach ($this->errores as $error)
                        <li class="font-mono">{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Cruces calculados --}}
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-700 p-6">
            <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-2">Cruces calculados</h3>
            @if ($this->crucesCount > 0)
                <p class="text-sm text-slate-600 dark:text-gray-400">
                    Se calcularon <strong>{{ $this->crucesCount }}</strong> cruces para esta fecha.
                </p>
                <a href="{{ \App\Filament\Resources\CruceResource::getUrl('index', ['tableFilters' => ['fecha_operacion' => ['desde' => $record->fecha_archivo->toDateString(), 'hasta' => $record->fecha_archivo->toDateString()]]]) }}"
                   class="inline-flex items-center gap-1 mt-3 text-sm font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">
                    Ver cruces
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            @else
                <p class="text-sm text-slate-500 dark:text-gray-500">No hay cruces asociados a este import.</p>
            @endif
        </div>
    </div>
</x-filament-panels::page>
