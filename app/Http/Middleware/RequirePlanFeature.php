<?php

namespace App\Http\Middleware;

use App\Support\PlanFeatures;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Refuse l'accès à un module non inclus dans le plan de l'école : `feature:finance`. */
class RequirePlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = tenant();

        if ($tenant && ! $tenant->hasFeature($feature)) {
            return response()->json([
                'message' => 'Le module « '.(PlanFeatures::MODULES[$feature] ?? $feature).' » n’est pas inclus dans votre abonnement.',
                'code' => 'feature_unavailable',
                'feature' => $feature,
            ], 403);
        }

        return $next($request);
    }
}
