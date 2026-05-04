<?php

namespace App\Livewire\Repartidor;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Login extends Component
{
    public string $cedula = '';

    public string $pin = '';

    #[Layout('components.layouts.repartidor')]
    public function render()
    {
        return view('livewire.repartidor.login');
    }

    public function submit(): void
    {
        $this->validate([
            'cedula' => ['required', 'string', 'regex:/^[a-zA-Z0-9]{6,15}$/'],
            'pin' => ['required', 'string', 'regex:/^\d{4}$/'],
        ], [
            'cedula.regex' => 'La cédula debe tener entre 6 y 15 caracteres alfanuméricos.',
            'pin.regex' => 'El PIN debe ser de 4 dígitos.',
        ]);

        $this->ensureIsNotRateLimited();

        $credentials = [
            'cedula' => $this->cedula,
            'password' => $this->pin,
            'activo' => true,
        ];

        if (! Auth::guard('repartidor')->attempt($credentials, true)) {
            RateLimiter::hit($this->throttleKey(), 60 * 15);

            throw ValidationException::withMessages([
                'cedula' => 'Cédula o PIN incorrectos.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        Auth::guard('repartidor')->user()->forceFill([
            'last_login_at' => now('America/Bogota'),
        ])->save();

        session()->regenerate();

        $this->redirect(route('repartidor.home'), navigate: false);
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'cedula' => "Demasiados intentos. Intente nuevamente en {$seconds} segundos.",
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->cedula).'|'.request()->ip());
    }
}
