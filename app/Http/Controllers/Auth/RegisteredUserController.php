<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                'unique:'.User::class,
                function ($attribute, $value, $fail) {
                    // Validar que sea correo de alumno, maestro o admin de UTM
                    $isAlumno = str_ends_with($value, '@alumno.utmetropolitana.edu.mx');
                    $isAdmin = str_ends_with($value, '@admin.utmetropolitana.edu.mx');
                    $isMaestro = str_ends_with($value, '@utmetropolitana.edu.mx') && 
                                !str_contains($value, '@alumno.utmetropolitana.edu.mx') &&
                                !str_contains($value, '@admin.utmetropolitana.edu.mx');
                    
                    if (!$isAlumno && !$isMaestro && !$isAdmin) {
                        $fail('The email field must end with one of the following: @alumno.utmetropolitana.edu.mx, @admin.utmetropolitana.edu.mx, @utmetropolitana.edu.mx.');
                    }
                }
            ],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
