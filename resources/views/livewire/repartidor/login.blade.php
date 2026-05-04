<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-900 px-4 py-8">
    <div class="w-full max-w-sm">
        {{-- Logo + tagline --}}
        <div class="text-center mb-10">
            <x-logo class="mx-auto text-white" />
            <p class="mt-3 text-sm font-medium text-indigo-200/80 tracking-wide uppercase">Reserva de paquetes</p>
        </div>

        {{-- Card principal --}}
        <div class="bg-white/95 backdrop-blur-sm rounded-2xl shadow-[0_20px_60px_-15px_rgba(0,0,0,0.5)] p-8 ring-1 ring-white/10">
            <div class="mb-6">
                <h1 class="text-xl font-bold text-slate-900">Ingresar</h1>
                <p class="text-sm text-slate-500 mt-1">Ingresa tu cédula y PIN de 4 dígitos.</p>
            </div>

            <form wire:submit="submit" class="space-y-5">
                {{-- Campo cedula --}}
                <div>
                    <label for="cedula" class="block text-sm font-semibold text-slate-700 mb-1.5">Cédula</label>
                    <input
                        type="text"
                        id="cedula"
                        wire:model="cedula"
                        inputmode="numeric"
                        autocomplete="username"
                        autofocus
                        placeholder="Ej: 1036643435"
                        class="w-full h-11 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 placeholder-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:bg-white text-base px-4 transition-all duration-200"
                    >
                    @error('cedula')
                        <span class="text-xs font-medium text-rose-600 mt-1.5 block flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                            {{ $message }}
                        </span>
                    @enderror
                </div>

                {{-- Campo PIN --}}
                <div>
                    <label for="pin" class="block text-sm font-semibold text-slate-700 mb-1.5">PIN</label>
                    <input
                        type="password"
                        id="pin"
                        wire:model="pin"
                        inputmode="numeric"
                        maxlength="4"
                        autocomplete="current-password"
                        placeholder="••••"
                        class="w-full h-11 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 placeholder-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:bg-white text-base tracking-[0.3em] text-center px-4 transition-all duration-200"
                    >
                    @error('pin')
                        <span class="text-xs font-medium text-rose-600 mt-1.5 block flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                            {{ $message }}
                        </span>
                    @enderror
                </div>

                {{-- Boton submit --}}
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="w-full h-12 bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white font-semibold rounded-xl transition-all duration-200 shadow-lg shadow-indigo-600/30 hover:shadow-indigo-500/40 disabled:opacity-50 disabled:cursor-not-allowed mt-2"
                >
                    <span wire:loading.remove class="flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                        Ingresar
                    </span>
                    <span wire:loading class="flex items-center justify-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Validando...
                    </span>
                </button>
            </form>
        </div>

        {{-- Footer --}}
        <p class="text-center text-xs text-slate-400/70 mt-8">
            ¿Problemas para ingresar? Contacta a tu coordinador.
        </p>
    </div>
</div>
