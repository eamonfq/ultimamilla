<?php

namespace App\Http\Requests\Repartidor;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cedula' => ['required', 'string', 'regex:/^[a-zA-Z0-9]{6,15}$/'],
            'pin' => ['required', 'string', 'regex:/^\d{4}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'cedula.regex' => 'La cédula debe tener entre 6 y 15 caracteres alfanuméricos.',
            'pin.regex' => 'El PIN debe ser de 4 dígitos.',
        ];
    }

    public function authenticate(): void
    {
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
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'cedula' => "Demasiados intentos. Intente nuevamente en {$seconds} segundos.",
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->cedula).'|'.$this->ip());
    }
}
