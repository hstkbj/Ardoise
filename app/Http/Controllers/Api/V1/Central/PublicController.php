<?php

namespace App\Http\Controllers\Api\V1\Central;

use App\Http\Controllers\Api\V1\Platform\PlanController;
use App\Http\Controllers\Controller;
use App\Models\Central\DemoRequest;
use App\Models\Central\NewsletterSubscriber;
use App\Models\Central\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Site public : formules, demande de démonstration, newsletter. */
class PublicController extends Controller
{
    public function plans(): JsonResponse
    {
        return response()->json(['data' => Plan::where('status', 'active')->orderBy('id')->get()->map(fn ($p) => PlanController::present($p))]);
    }

    public function demoRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'role' => ['nullable', 'string', 'max:100'],
            'school' => ['required', 'string', 'max:150'],
            'city' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'students' => ['nullable', 'string', 'max:50'],
            'sites' => ['nullable', 'integer', 'min:1', 'max:200'],
            'message' => ['nullable', 'string', 'max:3000'],
        ]);

        DemoRequest::create($data);

        return response()->json(['message' => 'Demande envoyée. Nous vous recontactons rapidement.'], 201);
    }

    public function newsletter(Request $request): JsonResponse
    {
        NewsletterSubscriber::firstOrCreate(['email' => strtolower($request->validate(['email' => ['required', 'email', 'max:150']])['email'])]);

        return response()->json(['message' => 'Inscription confirmée.']);
    }
}
