<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\CurrentCompany;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = null;

        try {
            $user = Filament::auth()->user();
        } catch (\Throwable) {
            // Outside Filament or panel not ready.
        }

        $user ??= $request->user('client') ?? $request->user('web') ?? $request->user();

        if ($user !== null && $user->company_id !== null) {
            CurrentCompany::set((int) $user->company_id);
        } else {
            CurrentCompany::clear();
        }

        try {
            return $next($request);
        } finally {
            // Static state can leak across requests in persistent PHP workers.
            CurrentCompany::clear();
        }
    }
}
