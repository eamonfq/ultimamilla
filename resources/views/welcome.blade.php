<!DOCTYPE html>
<html lang="es" class="antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="theme-color" content="#0f172a">
    <title>UltimaMilla Express</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>
        body { font-family: 'Inter', sans-serif; }

        .landing-bg {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 35%, #312e81 70%, #1e1b4b 100%);
            position: relative;
            overflow: hidden;
        }

        .landing-bg::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 600px 400px at 20% 50%, rgba(99, 102, 241, 0.08), transparent),
                radial-gradient(ellipse 500px 300px at 80% 30%, rgba(139, 92, 246, 0.06), transparent);
        }

        .card-glow {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card-glow:hover {
            transform: translateY(-4px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4), 0 0 40px -8px rgba(99, 102, 241, 0.15);
        }

        .fade-in {
            animation: fadeInUp 0.6s ease-out both;
        }

        .fade-in-delay {
            animation: fadeInUp 0.6s ease-out 0.15s both;
        }

        .fade-in-delay-2 {
            animation: fadeInUp 0.6s ease-out 0.3s both;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .pulse-dot {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body class="landing-bg min-h-screen flex flex-col items-center justify-center px-4 py-12">
    <div class="relative z-10 w-full max-w-lg">

        {{-- Header --}}
        <div class="text-center mb-12 fade-in">
            <div class="inline-flex items-baseline font-extrabold text-4xl tracking-tight text-white mb-4">
                <span>Última Milla</span>
                <span class="ml-1 w-2.5 h-2.5 rounded-full bg-indigo-400 inline-block pulse-dot"></span>
            </div>
            <p class="text-lg font-medium text-indigo-200/80">Sistema de operación logística</p>
            <p class="text-sm text-slate-400 mt-2">Planeación y reserva de paquetes para repartidores</p>
        </div>

        {{-- Cards de acceso --}}
        <div class="grid gap-4 fade-in-delay">

            {{-- Admin --}}
            <a href="{{ route('filament.admin.auth.login') }}"
               class="card-glow group block bg-white/[0.07] backdrop-blur-md rounded-2xl p-6 ring-1 ring-white/10 hover:ring-indigo-400/30">
                <div class="flex items-center gap-5">
                    <div class="flex-shrink-0 w-14 h-14 rounded-xl bg-indigo-500/20 flex items-center justify-center group-hover:bg-indigo-500/30 transition-colors">
                        <svg class="w-7 h-7 text-indigo-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="text-lg font-semibold text-white group-hover:text-indigo-200 transition-colors">Ingresar como Administrador</h2>
                        <p class="text-sm text-slate-400 mt-0.5">Panel de gestión, configuración y reportes</p>
                    </div>
                    <svg class="w-5 h-5 text-slate-500 group-hover:text-indigo-300 group-hover:translate-x-1 transition-all" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                    </svg>
                </div>
            </a>

            {{-- Repartidor --}}
            <a href="{{ route('repartidor.login') }}"
               class="card-glow group block bg-white/[0.07] backdrop-blur-md rounded-2xl p-6 ring-1 ring-white/10 hover:ring-emerald-400/30">
                <div class="flex items-center gap-5">
                    <div class="flex-shrink-0 w-14 h-14 rounded-xl bg-emerald-500/20 flex items-center justify-center group-hover:bg-emerald-500/30 transition-colors">
                        <svg class="w-7 h-7 text-emerald-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0H6.375m11.25 0h3.375a1.125 1.125 0 001.125-1.125v-4.875m0 0a2.625 2.625 0 00-2.625-2.625H16.5m2.25 7.5V12m0 0L16.5 6.75H6.375"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="text-lg font-semibold text-white group-hover:text-emerald-200 transition-colors">Ingresar como Repartidor</h2>
                        <p class="text-sm text-slate-400 mt-0.5">Reserva semanal de paquetes con cédula y PIN</p>
                    </div>
                    <svg class="w-5 h-5 text-slate-500 group-hover:text-emerald-300 group-hover:translate-x-1 transition-all" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                    </svg>
                </div>
            </a>
        </div>

        {{-- Footer --}}
        <div class="text-center mt-12 fade-in-delay-2">
            <p class="text-xs text-slate-500">Última Milla Express &middot; Sistema de operación logística</p>
        </div>
    </div>
</body>
</html>
