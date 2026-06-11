<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthController extends Controller
{
    /**
     * Mostrar formulario de login
     */
    public function showLogin()
    {
        // Si el usuario ya está logueado, redirigir según su rol
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->hasRole('admin')) {
                return redirect()->route('admin.dashboard');
            } else {
                return redirect()->route('admin.consulta-api.index');
            }
        }

        return Inertia::render('Auth/Login');
    }

    /**
     * Procesar login
     */
    public function login(Request $request)
    {
        // Rate limiting para prevenir brute force
        $key = 'login:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => "Demasiados intentos de inicio de sesión. Por favor, espera {$seconds} segundos."
            ]);
        }

        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ], [
            'email.required' => 'El correo electrónico es obligatorio',
            'email.email' => 'El correo electrónico debe ser válido',
            'password.required' => 'La contraseña es obligatoria',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            // Limpiar rate limiting en login exitoso
            RateLimiter::clear($key);

            // Redirigir según el rol del usuario
            $user = Auth::user();

            if ($user->hasRole('admin')) {
                return redirect()->intended(route('admin.dashboard'));
            } else {
                return redirect()->intended(route('admin.consulta-api.index'));
            }
        }

        // Incrementar rate limiting
        RateLimiter::hit($key, 60);

        throw ValidationException::withMessages([
            'email' => 'Las credenciales proporcionadas no coinciden con nuestros registros.',
        ]);
    }

    /**
     * Cerrar sesión
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
