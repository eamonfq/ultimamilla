@php
    $livewire ??= null;
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    @props([
        'after' => null,
        'heading' => null,
        'subheading' => null,
    ])

    <div class="min-h-screen flex flex-col items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-900 px-4 py-8">
        <div class="w-full max-w-sm">
            <div class="text-center mb-8">
                <div class="inline-flex items-baseline font-bold text-2xl tracking-tight text-white">
                    <span>Última Milla</span>
                    <span class="ml-0.5 w-1.5 h-1.5 rounded-full bg-indigo-400 inline-block"></span>
                </div>
                <p class="mt-2 text-sm text-slate-300">Panel administrativo</p>
            </div>

            <div class="bg-white rounded-2xl shadow-2xl p-8 text-slate-900 fi-simple-page" data-theme="light">
                {{ $slot }}
            </div>

            <p class="text-center text-xs text-slate-400 mt-6">
                Última Milla Express &middot; Sistema de operación logística
            </p>
        </div>
    </div>
</x-filament-panels::layout.base>
