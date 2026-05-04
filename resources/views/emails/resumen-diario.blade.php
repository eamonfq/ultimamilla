<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Resumen de operacion</title>
</head>
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">

<table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" width="100%" style="max-width:600px; margin:0 auto; padding:24px 16px;">
    <tr>
        <td>
            {{-- Header --}}
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:linear-gradient(135deg, #1e293b 0%, #4338ca 100%); border-radius:16px 16px 0 0; padding:24px;">
                <tr>
                    <td>
                        <h1 style="margin:0; color:#ffffff; font-size:18px; font-weight:600;">
                            Ultima Milla<span style="color:#a5b4fc;">.</span>
                        </h1>
                        <p style="margin:8px 0 0; color:#cbd5e1; font-size:13px;">
                            Resumen de operacion · {{ $d['fecha']->locale('es')->isoFormat('dddd, D [de] MMMM') }}
                        </p>
                    </td>
                </tr>
            </table>

            {{-- Body --}}
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#ffffff; border-radius:0 0 16px 16px; padding:24px;">

                {{-- Resumen ejecutivo --}}
                <tr>
                    <td>
                        <h2 style="margin:0 0 12px; color:#0f172a; font-size:15px; font-weight:600;">Resumen ejecutivo</h2>
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:24px;">
                            <tr>
                                <td width="50%" valign="top" style="padding:8px;">
                                    <div style="background:#eef2ff; border-radius:8px; padding:14px;">
                                        <p style="margin:0; font-size:11px; color:#6366f1; text-transform:uppercase; letter-spacing:0.5px; font-weight:600;">Repartidores que operaron</p>
                                        <p style="margin:4px 0 0; font-size:24px; font-weight:700; color:#1e1b4b;">{{ $d['total_repartidores'] }}</p>
                                    </div>
                                </td>
                                <td width="50%" valign="top" style="padding:8px;">
                                    <div style="background:#ecfdf5; border-radius:8px; padding:14px;">
                                        <p style="margin:0; font-size:11px; color:#059669; text-transform:uppercase; letter-spacing:0.5px; font-weight:600;">Cumplimiento global</p>
                                        <p style="margin:4px 0 0; font-size:24px; font-weight:700; color:#064e3b;">{{ $d['cumplimiento_global'] }}%</p>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2" style="padding:8px;">
                                    <div style="background:#f8fafc; border-radius:8px; padding:14px;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td width="33%" align="center" style="padding:0 8px;">
                                                    <p style="margin:0; font-size:11px; color:#64748b; text-transform:uppercase;">Reservado</p>
                                                    <p style="margin:4px 0 0; font-size:18px; font-weight:600; color:#0f172a;">{{ number_format($d['total_reservado']) }}</p>
                                                </td>
                                                <td width="33%" align="center" style="padding:0 8px; border-left:1px solid #e2e8f0;">
                                                    <p style="margin:0; font-size:11px; color:#64748b; text-transform:uppercase;">Asignado</p>
                                                    <p style="margin:4px 0 0; font-size:18px; font-weight:600; color:#4f46e5;">{{ number_format($d['total_asignado']) }}</p>
                                                </td>
                                                <td width="33%" align="center" style="padding:0 8px; border-left:1px solid #e2e8f0;">
                                                    <p style="margin:0; font-size:11px; color:#64748b; text-transform:uppercase;">Entregado</p>
                                                    <p style="margin:4px 0 0; font-size:18px; font-weight:600; color:#059669;">{{ number_format($d['total_entregado']) }}</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Top 3 --}}
                @if (count($d['top3']) > 0)
                <tr>
                    <td style="padding-bottom:24px;">
                        <h2 style="margin:0 0 12px; color:#0f172a; font-size:15px; font-weight:600;">
                            <span style="color:#10b981;">&#9650;</span> Mejor desempeno
                        </h2>
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border:1px solid #e2e8f0; border-radius:8px;">
                            @foreach ($d['top3'] as $i => $r)
                                <tr>
                                    <td style="padding:12px 16px; {{ $i < count($d['top3']) - 1 ? 'border-bottom:1px solid #f1f5f9;' : '' }}">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td>
                                                    <span style="display:inline-block; width:20px; color:#94a3b8; font-size:12px; font-weight:600;">{{ $i + 1 }}</span>
                                                    <span style="color:#0f172a; font-weight:500;">{{ $r['nombre'] }}</span>
                                                </td>
                                                <td align="right">
                                                    <span style="color:#10b981; font-weight:700;">{{ $r['cumplimiento'] }}%</span>
                                                    <span style="color:#94a3b8; font-size:12px; margin-left:8px;">{{ $r['entregado'] }} entregados</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>
                @endif

                {{-- Bottom 3 --}}
                @if (count($d['bottom3']) > 0)
                <tr>
                    <td style="padding-bottom:24px;">
                        <h2 style="margin:0 0 12px; color:#0f172a; font-size:15px; font-weight:600;">
                            <span style="color:#e11d48;">&#9660;</span> Necesitan atencion
                        </h2>
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border:1px solid #e2e8f0; border-radius:8px;">
                            @foreach ($d['bottom3'] as $i => $r)
                                <tr>
                                    <td style="padding:12px 16px; {{ $i < count($d['bottom3']) - 1 ? 'border-bottom:1px solid #f1f5f9;' : '' }}">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td>
                                                    <span style="display:inline-block; width:20px; color:#94a3b8; font-size:12px; font-weight:600;">{{ $i + 1 }}</span>
                                                    <span style="color:#0f172a; font-weight:500;">{{ $r['nombre'] }}</span>
                                                </td>
                                                <td align="right">
                                                    <span style="color:{{ $r['cumplimiento'] < 70 ? '#e11d48' : '#d97706' }}; font-weight:700;">{{ $r['cumplimiento'] }}%</span>
                                                    <span style="color:#94a3b8; font-size:12px; margin-left:8px;">{{ $r['entregado'] }} entregados</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>
                @endif

                {{-- Alertas --}}
                @if ($d['alertas_count'] > 0)
                <tr>
                    <td style="padding-bottom:24px;">
                        <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:16px;">
                            <h3 style="margin:0 0 8px; color:#991b1b; font-size:14px; font-weight:600;">
                                {{ $d['alertas_count'] }} {{ $d['alertas_count'] === 1 ? 'repartidor' : 'repartidores' }} con patron preocupante
                            </h3>
                            <ul style="margin:0; padding-left:20px; color:#7f1d1d; font-size:13px;">
                                @foreach ($d['alertas'] as $caso)
                                    <li style="margin-bottom:4px;">
                                        <strong>{{ $caso['repartidor']->nombre }}:</strong>
                                        {{ collect($caso['alertas'])->pluck('titulo')->join(' · ') }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </td>
                </tr>
                @endif

                {{-- Footer link --}}
                <tr>
                    <td align="center" style="padding-top:8px;">
                        <a href="{{ config('app.url') }}/admin"
                           style="display:inline-block; background:#4f46e5; color:#ffffff; text-decoration:none; padding:12px 24px; border-radius:8px; font-weight:600; font-size:14px;">
                            Abrir el panel
                        </a>
                    </td>
                </tr>
            </table>

            {{-- Footer --}}
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top:16px; padding:0 16px;">
                <tr>
                    <td align="center">
                        <p style="margin:0; color:#94a3b8; font-size:11px;">
                            Ultima Milla Express · Sistema de operacion logistica
                        </p>
                        <p style="margin:4px 0 0; color:#cbd5e1; font-size:11px;">
                            Email automatico generado a las 6:00 AM hora Colombia.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

</body>
</html>
