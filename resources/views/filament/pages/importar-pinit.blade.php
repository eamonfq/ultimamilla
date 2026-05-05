<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white rounded-2xl border border-slate-200 p-6 dark:bg-gray-900 dark:border-gray-700">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white mb-1">Subir archivo</h2>
            <p class="text-sm text-slate-500 dark:text-gray-400 mb-4">El archivo se procesa en segundo plano. Puedes navegar a otras paginas mientras tanto.</p>

            @if (session('success'))
                <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-sm text-emerald-700 dark:bg-emerald-900/20 dark:border-emerald-800 dark:text-emerald-300">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
                    {{ session('error') }}
                </div>
            @endif

            <form action="{{ route('pinit-import.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="space-y-3">
                    <label for="archivo" class="block text-sm font-medium text-slate-700 dark:text-gray-300">
                        Archivo Pinit (.xlsx)
                    </label>
                    <div class="relative flex items-center justify-center w-full">
                        <label for="archivo" class="flex flex-col items-center justify-center w-full h-36 border-2 border-dashed rounded-xl cursor-pointer border-slate-300 bg-slate-50 hover:bg-slate-100 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700 transition">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                <svg class="w-8 h-8 mb-3 text-slate-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                                <p class="mb-1 text-sm text-slate-600 dark:text-gray-400">
                                    <span class="font-semibold">Haz clic para seleccionar</span> o arrastra el archivo
                                </p>
                                <p class="text-xs text-slate-400 dark:text-gray-500">.xlsx exportado de Pinit (max 25MB)</p>
                            </div>
                            <input
                                type="file"
                                id="archivo"
                                name="archivo"
                                accept=".xlsx,.xls"
                                required
                                class="hidden"
                                onchange="document.getElementById('file-name-display').textContent = this.files[0]?.name || ''"
                            />
                        </label>
                    </div>
                    <p id="file-name-display" class="text-sm font-medium text-indigo-600 dark:text-indigo-400"></p>
                    @error('archivo') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="mt-4 flex justify-end">
                    <button
                        type="submit"
                        @disabled($this->importIdEnProceso !== null)
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg text-sm font-semibold transition"
                    >
                        Importar
                    </button>
                </div>
            </form>
        </div>

        @if ($this->importEnProceso)
            <div
                wire:poll.2s="pollingTick"
                class="bg-indigo-50 border-2 border-indigo-200 rounded-2xl p-6 dark:bg-indigo-950 dark:border-indigo-800"
            >
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-3 h-3 bg-indigo-600 rounded-full animate-pulse"></div>
                    <h3 class="text-base font-semibold text-indigo-900 dark:text-indigo-100">
                        Procesando archivo
                    </h3>
                </div>
                <p class="text-sm text-indigo-800 dark:text-indigo-200">
                    Estado: <strong>{{ $this->importEnProceso->status->label() }}</strong> &middot;
                    Fecha: {{ $this->importEnProceso->fecha_archivo->locale('es')->isoFormat('D MMM YYYY') }}
                </p>
            </div>
        @endif

        <div class="bg-white rounded-2xl border border-slate-200 p-6 dark:bg-gray-900 dark:border-gray-700">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white mb-3">Imports recientes</h2>
            @php
                $recientes = \App\Models\PinitImport::query()
                    ->with('importadoPor')
                    ->latest()
                    ->limit(10)
                    ->get();
            @endphp
            @if ($recientes->isEmpty())
                <p class="text-sm text-slate-500 dark:text-gray-400">No hay imports todavia.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 dark:border-gray-700">
                            <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-gray-400">
                                <th class="py-2">Fecha archivo</th>
                                <th class="py-2">Estado</th>
                                <th class="py-2 text-right">Filas</th>
                                <th class="py-2">Subido por</th>
                                <th class="py-2">Subido el</th>
                                <th class="py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-gray-800">
                            @foreach ($recientes as $imp)
                                <tr>
                                    <td class="py-3 font-medium text-slate-900 dark:text-white">{{ $imp->fecha_archivo->locale('es')->isoFormat('ddd D MMM YYYY') }}</td>
                                    <td class="py-3">
                                        <span @class([
                                            'inline-block px-2 py-0.5 text-xs font-medium rounded-full',
                                            'bg-emerald-100 text-emerald-700' => $imp->status === \App\Enums\StatusImport::Done,
                                            'bg-rose-100 text-rose-700' => $imp->status === \App\Enums\StatusImport::Failed,
                                            'bg-amber-100 text-amber-700' => $imp->status === \App\Enums\StatusImport::Superseded,
                                            'bg-indigo-100 text-indigo-700' => in_array($imp->status, [\App\Enums\StatusImport::Pending, \App\Enums\StatusImport::Processing]),
                                        ])>{{ $imp->status->label() }}</span>
                                    </td>
                                    <td class="py-3 text-right font-mono text-slate-700 dark:text-gray-300">{{ number_format($imp->total_filas) }}</td>
                                    <td class="py-3 text-slate-600 dark:text-gray-400">{{ $imp->importadoPor?->name ?? '—' }}</td>
                                    <td class="py-3 text-slate-500 dark:text-gray-500 text-xs">{{ $imp->created_at->diffForHumans() }}</td>
                                    <td class="py-3 text-right">
                                        <a href="{{ \App\Filament\Resources\PinitImportResource::getUrl('view', ['record' => $imp]) }}"
                                           class="text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 text-xs font-semibold">Ver</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
