@if ($pinRecienGenerado)
<div
    x-data="{ copiado: false }"
    style="margin-bottom: 1.5rem; border-radius: 0.75rem; border: 2px solid #fcd34d; background: #fffbeb; padding: 1.25rem;"
>
    <div style="display: flex; align-items: flex-start; gap: 1rem;">
        <div style="flex-shrink: 0;">
            <div style="width: 2.5rem; height: 2.5rem; border-radius: 9999px; background: #fde68a; display: flex; align-items: center; justify-content: center;">
                <svg width="20" height="20" fill="none" stroke="#b45309" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
        </div>
        <div style="flex: 1; min-width: 0;">
            <h3 style="font-size: 1rem; font-weight: 600; color: #78350f; margin: 0;">
                PIN generado — guárdalo ahora
            </h3>
            <p style="margin-top: 0.25rem; font-size: 0.875rem; color: #92400e;">
                Por seguridad, el PIN no se volverá a mostrar. Cópialo y envíalo al repartidor por WhatsApp ahora.
            </p>

            <div style="margin-top: 1rem; display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem;">
                <div style="background: #fff; border-radius: 0.5rem; padding: 0.75rem; border: 1px solid #fde68a;">
                    <p style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin: 0;">PIN</p>
                    <p style="margin-top: 0.25rem; font-size: 1.5rem; font-family: monospace; font-weight: 700; color: #0f172a; letter-spacing: 0.1em;">
                        {{ $pinRecienGenerado }}
                    </p>
                </div>
                <div style="background: #fff; border-radius: 0.5rem; padding: 0.75rem; border: 1px solid #fde68a;">
                    <p style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin: 0;">Cédula</p>
                    <p style="margin-top: 0.25rem; font-size: 1.5rem; font-family: monospace; font-weight: 700; color: #0f172a;">
                        {{ $this->record->cedula }}
                    </p>
                </div>
            </div>

            <div style="margin-top: 1rem; display: flex; flex-wrap: wrap; gap: 0.5rem;">
                <button
                    type="button"
                    x-on:click="
                        navigator.clipboard.writeText(@js($mensajeWhatsApp));
                        copiado = true;
                        setTimeout(() => copiado = false, 2500);
                    "
                    style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; border-radius: 0.5rem; background: #059669; color: #fff; font-weight: 500; font-size: 0.875rem; border: none; cursor: pointer;"
                >
                    <svg x-show="!copiado" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                    </svg>
                    <svg x-show="copiado" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span x-text="copiado ? 'Copiado' : 'Copiar mensaje WhatsApp'"></span>
                </button>

                <a
                    href="https://wa.me/?text={{ urlencode($mensajeWhatsApp) }}"
                    target="_blank"
                    rel="noopener"
                    style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; border-radius: 0.5rem; background: #fff; color: #374151; font-weight: 500; font-size: 0.875rem; border: 1px solid #d1d5db; text-decoration: none; cursor: pointer;"
                >
                    Abrir en WhatsApp
                </a>
            </div>

            <details style="margin-top: 1rem;">
                <summary style="font-size: 0.75rem; color: #b45309; cursor: pointer;">
                    Ver mensaje completo
                </summary>
                <pre style="margin-top: 0.5rem; font-size: 0.75rem; color: #374151; white-space: pre-wrap; background: #fff; padding: 0.75rem; border-radius: 0.5rem; border: 1px solid #e2e8f0; font-family: monospace;">{{ $mensajeWhatsApp }}</pre>
            </details>
        </div>
    </div>
</div>
@endif
