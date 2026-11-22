<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Présence en ligne (demande du centre, 2026-11-22) : dernière activité d'un membre de l'équipe (garde web) ou d'un
 * apprenant (garde client), enregistrée au plus une fois par minute, sans audit (donnée technique).
 */
class TrackLastSeen
{
    public const ONLINE_MINUTES = 5;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (['web', 'client'] as $guard) {
            $user = $request->user($guard);
            if ($user && (! $user->last_seen_at || $user->last_seen_at->lt(now()->subMinute()))) {
                $user->forceFill(['last_seen_at' => now()])->saveQuietly();
            }
        }

        return $response;
    }
}
