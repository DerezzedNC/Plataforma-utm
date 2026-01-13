<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return redirect()->route('login');
        }

        // Verificar si el usuario tiene el rol correcto
        if ($role === 'admin' && !$user->isAdmin()) {
            abort(403, 'Acceso denegado. Se requieren privilegios de administrador.');
        }
        
        if ($role === 'maestro' && !$user->isMaestro()) {
            return redirect()->route('dashboard'); // Redirigir alumnos al dashboard de estudiantes
        }
        
        if ($role === 'alumno' && !$user->isAlumno()) {
            if ($user->isAdmin()) {
                return redirect()->route('dashboard-admin');
            }
            return redirect()->route('dashboard-maestro'); // Redirigir maestros al dashboard de maestros
        }

        return $next($request);
    }
}