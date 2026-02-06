<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // Obtener el periodo académico activo
        $currentPeriod = \App\Models\AcademicPeriod::active()->first();
        
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'currentPeriod' => $currentPeriod ? [
                'id' => $currentPeriod->id,
                'name' => $currentPeriod->name,
                'code' => $currentPeriod->code,
                'start_date' => $currentPeriod->start_date,
                'end_date' => $currentPeriod->end_date,
                'is_active' => $currentPeriod->is_active,
                'is_open_for_grades' => $currentPeriod->is_open_for_grades,
            ] : null,
        ];
    }
}
